<?php

namespace App\Services;

use App\Models\Enseignant;
use App\Models\Presence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Rejoue un paquet de la file d'une borne relais (§hardware) — une ligne de
 * son `queue.jsonl` — comme si la requête du téléphone avait atteint l'API
 * directement. Partagé par la synchro en ligne de la borne
 * (RelaySyncController) et l'import manuel du fichier par un admin
 * (RelayImportController), pour qu'un même paquet soit traité à l'identique
 * quel que soit le chemin par lequel il arrive.
 */
class RelayPacketProcessor
{
    public function __construct(private readonly AttendanceRecorder $recorder) {}

    /**
     * Règles de validation d'un paquet. Chaque sous-champ de `payload` DOIT
     * avoir sa propre règle explicite : dès qu'UN sous-champ a une règle (ex.
     * photo_base64), Validator::validated() élague silencieusement tous les
     * autres sous-champs sans règle propre — sans ça, qr_code/bssid
     * disparaissent du tableau validé et tout paquet échoue avec "QR code non
     * reconnu" (qr_code devient toujours une chaîne vide côté AttendanceRecorder).
     */
    public static function rules(string $prefix = ''): array
    {
        return [
            "{$prefix}local_id" => ['required', 'string'],
            "{$prefix}type" => ['required', 'in:scan,admin_proxy'],
            "{$prefix}captured_at" => ['required', 'date'],
            "{$prefix}teacher_token" => ['required', 'string'],
            "{$prefix}payload" => ['required', 'array'],
            "{$prefix}payload.qr_code" => ['required', 'string'],
            "{$prefix}payload.bssid" => ['required', 'string'],
            "{$prefix}payload.enseignant_id" => ['sometimes', 'nullable', 'integer'],
            "{$prefix}payload.motif" => ['sometimes', 'nullable', 'string'],
            "{$prefix}payload.photo_base64" => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * Statuts renvoyés : `ok` (enregistré), `rejected` (invalide, inutile de
     * réessayer : la borne peut purger), `retry` (erreur transitoire).
     *
     * $assignment (import manuel uniquement, voir assignTeacher()) : scan dont
     * le token a disparu, attribué à la main par un admin.
     */
    public function process(array $packet, ?array $assignment = null): array
    {
        $localId = $packet['local_id'];

        try {
            $capturedAt = $this->capturedAt($packet);
            $payload = $packet['payload'];
            $photoBase64 = $payload['photo_base64'] ?? null;

            $acteur = match (true) {
                $assignment !== null => $assignment['enseignant'],
                $packet['type'] === 'scan' => $this->resolveTeacher($packet['teacher_token']),
                default => $this->resolveProxyActor($packet['teacher_token']),
            };

            $presence = $packet['type'] === 'scan'
                ? $this->recorder->recordSelfScan(
                    $acteur,
                    null,
                    (string) ($payload['qr_code'] ?? ''),
                    (string) ($payload['bssid'] ?? ''),
                    $capturedAt,
                    source: $assignment ? 'manuel' : 'app_mobile',
                    deviceCaptureAt: $capturedAt,
                    photoBase64: $photoBase64,
                    extraAttributes: $assignment ? [
                        'recorded_by' => $assignment['by']->id,
                        'reason' => "Import file borne : token n°{$this->tokenId($packet)} introuvable, attribué manuellement",
                    ] : [],
                )
                : $this->recordProxyPacket($acteur, $payload, $capturedAt, $photoBase64);

            return ['local_id' => $localId, 'status' => 'ok', 'presence_id' => $presence->id];
        } catch (ValidationException $e) {
            return ['local_id' => $localId, 'status' => 'rejected', 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            Log::warning('relay.sync: échec de traitement d\'un paquet', ['local_id' => $localId, 'error' => $e->getMessage()]);

            // Erreur transitoire (token expiré au mauvais moment, enseignant introuvable, etc.)
            // : on ne renvoie pas "ok", la borne le retentera au prochain cycle.
            return ['local_id' => $localId, 'status' => 'retry', 'message' => 'Traitement impossible pour le moment.'];
        }
    }

    /**
     * La borne/l'app envoient l'heure en UTC ("...Z") : on la ramène au fuseau
     * de l'application (GMT+1), sinon l'heure UTC serait stockée telle quelle.
     */
    public function capturedAt(array $packet): Carbon
    {
        return Carbon::parse($packet['captured_at'])->setTimezone(config('app.timezone'));
    }

    /**
     * Enseignant dont la présence serait pointée par ce paquet (lui-même pour
     * un scan, la cible pour une procuration), sans rien enregistrer.
     *
     * @throws ValidationException token invalide / enseignant ciblé introuvable
     */
    public function targetTeacher(array $packet): Enseignant
    {
        if ($packet['type'] === 'scan') {
            return $this->resolveTeacher($packet['teacher_token']);
        }

        $this->resolveProxyActor($packet['teacher_token']);
        $cible = Enseignant::find($packet['payload']['enseignant_id'] ?? null);
        if (! $cible) {
            throw ValidationException::withMessages(['payload' => ['Enseignant ciblé introuvable.']]);
        }

        return $cible;
    }

    /** Partie publique `id` d'un token Sanctum `id|secret` (null si mal formé). */
    public function tokenId(array $packet): ?int
    {
        $id = strstr((string) ($packet['teacher_token'] ?? ''), '|', true);

        return ctype_digit((string) $id) ? (int) $id : null;
    }

    /**
     * Vrai si le token du paquet n'existe plus en base (supprimé par une
     * reconnexion de l'enseignant, une révocation...) : seul cas où un admin
     * peut attribuer le scan à la main. Un token existant n'est jamais
     * remplaçable — l'attribution ne peut pas réécrire l'identité d'un scan valide.
     */
    public function hasUnknownToken(array $packet): bool
    {
        return $packet['type'] === 'scan' && PersonalAccessToken::findToken($packet['teacher_token']) === null;
    }

    /**
     * Vrai si ce pointage précis (même enseignant, même seconde) figure déjà
     * en arrivée ou en départ : le rejouer basculerait sinon la présence du
     * jour (arrivée -> départ) ou serait rejeté avec un message trompeur.
     */
    public function isAlreadyRecorded(Enseignant $enseignant, Carbon $capturedAt): bool
    {
        $instant = $capturedAt->format('Y-m-d H:i:s');

        return Presence::where('enseignant_id', $enseignant->id)
            ->where('date', $capturedAt->toDateString())
            ->where(fn($q) => $q->where('heure_arrivee', $instant)->orWhere('heure_depart', $instant))
            ->exists();
    }

    private function recordProxyPacket(Enseignant|User $acteur, array $payload, Carbon $capturedAt, ?string $photoBase64): Presence
    {
        $cibleId = $payload['enseignant_id'] ?? null;
        $motif = $payload['motif'] ?? null;

        if (! $cibleId || ! $motif) {
            throw ValidationException::withMessages(['payload' => ['enseignant_id et motif requis pour une procuration.']]);
        }

        $cible = Enseignant::findOrFail($cibleId);

        return $this->recorder->recordProxyScan(
            $acteur,
            null,
            $cible,
            (string) ($payload['qr_code'] ?? ''),
            (string) ($payload['bssid'] ?? ''),
            (string) $motif,
            $capturedAt,
            source: 'admin_proxy',
            deviceCaptureAt: $capturedAt,
            photoBase64: $photoBase64,
        );
    }

    /**
     * Résout l'enseignant dont la présence doit être pointée pour un scan
     * personnel : directement le tokenable si c'est un Enseignant, ou
     * l'enseignant lié si c'est un admin backoffice (`User`, §admin-mobile) —
     * un `User` sans lien n'a aucune présence où écrire, rejet définitif.
     */
    private function resolveTeacher(string $plainTextToken): Enseignant
    {
        $accessToken = PersonalAccessToken::findToken($plainTextToken);
        $tokenable = $accessToken?->tokenable;

        if ($tokenable instanceof Enseignant) {
            return $tokenable;
        }

        if ($tokenable instanceof User && $tokenable->enseignant) {
            return $tokenable->enseignant;
        }

        throw ValidationException::withMessages(['teacher_token' => ['Token enseignant invalide.']]);
    }

    /**
     * Résout l'acteur d'un scan par procuration : soit un enseignant à rôle
     * restreint (`est_admin`), soit un admin du backoffice (`User`,
     * §admin-mobile) — les deux peuvent scanner au nom d'un enseignant, seul
     * le libellé de la notification en dépend (voir AttendanceRecorder).
     */
    private function resolveProxyActor(string $plainTextToken): Enseignant|User
    {
        $accessToken = PersonalAccessToken::findToken($plainTextToken);
        $tokenable = $accessToken?->tokenable;

        if (! $tokenable instanceof Enseignant && ! $tokenable instanceof User) {
            throw ValidationException::withMessages(['teacher_token' => ['Token invalide.']]);
        }

        return $tokenable;
    }
}
