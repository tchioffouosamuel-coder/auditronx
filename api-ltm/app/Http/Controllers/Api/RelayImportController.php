<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enseignant;
use App\Models\User;
use App\Services\RelayPacketProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use SplFileObject;

/**
 * Import manuel de la file d'une borne relais (§hardware) : l'admin récupère
 * le fichier `queue.jsonl` sur la carte micro-SD (ou la flash) d'une borne
 * qui ne parvient pas à synchroniser — pas d'internet, token révoqué, borne
 * en panne — et le dépose ici. Chaque ligne est rejouée exactement comme si
 * la borne l'avait poussée via /api/relay/sync (RelayPacketProcessor).
 *
 * Contrairement à la synchro de la borne, l'import est idempotent : un
 * pointage déjà enregistré (même enseignant, même seconde) est signalé
 * `duplicate` et ignoré, pour qu'un fichier importé deux fois — ou dont une
 * partie a déjà été synchronisée par la borne — ne fausse pas les présences.
 */
class RelayImportController extends Controller
{
    public function __construct(private readonly RelayPacketProcessor $processor) {}

    /**
     * POST /api/relay/import (multipart : file, dry_run?)
     *
     * `dry_run=1` : analyse seule (aucun enregistrement) — chaque ligne
     * valide et non dupliquée ressort `pending`. Sinon, statut final par
     * ligne : ok / rejected / retry / duplicate / invalid.
     */
    public function import(Request $request)
    {
        $request->validate([
            // ~25 Ko par paquet avec selfie : 50 Mo couvre largement une file
            // SD de 2000 paquets (MAX_QUEUE_SIZE_SD côté firmware).
            'file' => ['required', 'file', 'max:51200'],
            'dry_run' => ['sometimes', 'boolean'],
            // JSON {"<n° de token>": <enseignant_id>} — voir assignmentFor().
            'assignments' => ['sometimes', 'nullable', 'json'],
        ]);
        $dryRun = $request->boolean('dry_run');
        $this->assignments = $this->loadAssignments($request);

        // Chaque paquet peut stocker une photo : quelques centaines de lignes
        // dépassent vite le max_execution_time par défaut (30 s).
        @set_time_limit(300);

        $file = new SplFileObject($request->file('file')->getRealPath());
        $lines = [];
        $seenLocalIds = [];
        $lineNumber = 0;

        while (! $file->eof()) {
            $raw = trim((string) $file->fgets());
            $lineNumber++;
            if ($raw === '') {
                continue;
            }

            $lines[] = $this->handleLine($lineNumber, $raw, $seenLocalIds, $dryRun);
        }

        $summary = array_count_values(array_column($lines, 'status'));

        return response()->json([
            'dry_run' => $dryRun,
            'total' => count($lines),
            'summary' => $summary,
            'lines' => $lines,
        ]);
    }

    /** @var array<int, array{enseignant: Enseignant, by: User}> n° de token => attribution */
    private array $assignments = [];

    /**
     * Attributions manuelles des scans dont le token a disparu (enseignant
     * reconnecté entre le scan et la synchro : DeviceController supprime
     * alors l'ancien token). Les paquets d'un même token viennent forcément
     * du même téléphone, donc du même enseignant : l'admin attribue un token
     * entier, pas ligne par ligne.
     */
    private function loadAssignments(Request $request): array
    {
        $raw = json_decode((string) $request->input('assignments', ''), true);
        if (! is_array($raw)) {
            return [];
        }

        $enseignants = Enseignant::whereIn('id', array_filter(array_map('intval', $raw)))->get()->keyBy('id');
        $assignments = [];
        foreach ($raw as $tokenId => $enseignantId) {
            $enseignant = $enseignants->get((int) $enseignantId);
            if (ctype_digit((string) $tokenId) && $enseignant) {
                $assignments[(int) $tokenId] = ['enseignant' => $enseignant, 'by' => $request->user()];
            }
        }

        return $assignments;
    }

    private function handleLine(int $lineNumber, string $raw, array &$seenLocalIds, bool $dryRun): array
    {
        $row = ['line' => $lineNumber, 'local_id' => null, 'type' => null, 'captured_at' => null, 'enseignant' => null, 'has_photo' => false, 'token_id' => null, 'unknown_token' => false, 'assigned' => false];

        $packet = json_decode($raw, true);
        if (! is_array($packet)) {
            return $row + ['status' => 'invalid', 'message' => 'Ligne JSON illisible.'];
        }

        $row['local_id'] = is_string($packet['local_id'] ?? null) ? $packet['local_id'] : null;
        $row['type'] = is_string($packet['type'] ?? null) ? $packet['type'] : null;
        $row['has_photo'] = ! empty($packet['payload']['photo_base64'] ?? null);

        $validator = Validator::make($packet, RelayPacketProcessor::rules());
        if ($validator->fails()) {
            return $row + ['status' => 'invalid', 'message' => $validator->errors()->first()];
        }
        $packet = $validator->validated();

        $capturedAt = $this->processor->capturedAt($packet);
        $row['captured_at'] = $capturedAt->toIso8601String();

        if (isset($seenLocalIds[$packet['local_id']])) {
            return $row + ['status' => 'duplicate', 'message' => "Doublon de la ligne {$seenLocalIds[$packet['local_id']]}."];
        }
        $seenLocalIds[$packet['local_id']] = $lineNumber;

        $row['token_id'] = $this->processor->tokenId($packet);
        $assignment = null;

        if ($this->processor->hasUnknownToken($packet)) {
            $row['unknown_token'] = true;
            $assignment = $this->assignments[$row['token_id']] ?? null;
            if (! $assignment) {
                return $row + ['status' => 'rejected', 'message' => 'Token enseignant invalide.'];
            }
            $row['assigned'] = true;
            $enseignant = $assignment['enseignant'];
        } else {
            try {
                $enseignant = $this->processor->targetTeacher($packet);
            } catch (ValidationException $e) {
                return $row + ['status' => 'rejected', 'message' => $e->getMessage()];
            }
        }
        $row['enseignant'] = $enseignant->nom;

        if ($this->processor->isAlreadyRecorded($enseignant, $capturedAt)) {
            return $row + ['status' => 'duplicate', 'message' => 'Pointage déjà enregistré.'];
        }

        if ($dryRun) {
            return $row + ['status' => 'pending', 'message' => $row['assigned'] ? 'Attribué manuellement.' : null];
        }

        $result = $this->processor->process($packet, $assignment);

        return $row + ['status' => $result['status'], 'message' => $result['message'] ?? null];
    }
}
