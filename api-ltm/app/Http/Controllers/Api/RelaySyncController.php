<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Enseignant;
use App\Services\AttendanceRecorder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Passerelle offline (§hardware) : la borne ESP32-S3 (WIFI_AP_STA + caméra
 * OV5640) reçoit le pointage du téléphone en local (WiFi + HTTP), le met en
 * file sur flash, et — dès que sa connexion au modem est disponible — pousse
 * les paquets en attente ici par lots. Chaque paquet accusé de réception
 * (`ok`) peut être supprimé de sa file locale ; les autres (`retry`) y
 * restent pour une prochaine tentative.
 *
 * Authentification : uniquement le device relais (Device, device_type
 * `relay_gateway`) — jamais le téléphone directement. L'identité de
 * l'enseignant à l'intérieur de chaque paquet est résolue à partir du token
 * Sanctum que son app a émis au moment du scan (`teacher_token`), exactement
 * comme si la requête avait atteint l'API directement : le relais ne fait que
 * rejouer, en différé, une requête que le téléphone avait déjà authentifiée.
 *
 * `payload.photo_base64` (optionnelle) : photo JPEG prise par la caméra de la
 * borne au moment du scan, encodée en base64 — preuve visuelle anti-fraude
 * (§hardware), décodée et stockée par AttendanceRecorder.
 */
class RelaySyncController extends Controller
{
    public function __construct(private readonly AttendanceRecorder $recorder) {}

    /** POST /api/relay/sync */
    public function sync(Request $request)
    {
        $device = $request->user();

        if (! $device instanceof Device || $device->device_type !== 'relay_gateway' || $device->isRevoked()) {
            abort(403, 'Authentification passerelle relais requise.');
        }

        // Chaque sous-champ de `payload` DOIT avoir sa propre règle explicite :
        // dès qu'UN sous-champ a une règle (ex. photo_base64 ci-dessous),
        // Validator::validated() élague silencieusement tous les autres
        // sous-champs sans règle propre — sans ça, qr_code/bssid disparaissent
        // du tableau validé et tout paquet relayé échoue avec "QR code non
        // reconnu" (qr_code devient toujours une chaîne vide côté AttendanceRecorder).
        $data = $request->validate([
            'packets' => ['required', 'array', 'min:1', 'max:100'],
            'packets.*.local_id' => ['required', 'string'],
            'packets.*.type' => ['required', 'in:scan,admin_proxy'],
            'packets.*.captured_at' => ['required', 'date'],
            'packets.*.teacher_token' => ['required', 'string'],
            'packets.*.payload' => ['required', 'array'],
            'packets.*.payload.qr_code' => ['required', 'string'],
            'packets.*.payload.bssid' => ['required', 'string'],
            'packets.*.payload.enseignant_id' => ['sometimes', 'nullable', 'integer'],
            'packets.*.payload.motif' => ['sometimes', 'nullable', 'string'],
            'packets.*.payload.photo_base64' => ['sometimes', 'nullable', 'string'],
        ]);

        $results = array_map(
            fn (array $packet) => $this->processPacket($device, $packet),
            $data['packets'],
        );

        return response()->json(['results' => $results]);
    }

    private function processPacket(Device $relay, array $packet): array
    {
        $localId = $packet['local_id'];

        try {
            $acteur = $this->resolveTeacher($packet['teacher_token']);
            $capturedAt = Carbon::parse($packet['captured_at']);
            $payload = $packet['payload'];
            $photoBase64 = $payload['photo_base64'] ?? null;

            $presence = $packet['type'] === 'scan'
                ? $this->recorder->recordSelfScan(
                    $acteur,
                    null,
                    (string) ($payload['qr_code'] ?? ''),
                    (string) ($payload['bssid'] ?? ''),
                    $capturedAt,
                    source: 'app_mobile',
                    deviceCaptureAt: $capturedAt,
                    photoBase64: $photoBase64,
                )
                : $this->recordProxyPacket($acteur, $payload, $capturedAt, $relay, $photoBase64);

            return ['local_id' => $localId, 'status' => 'ok', 'presence_id' => $presence->id];
        } catch (ValidationException $e) {
            // Rejeté définitivement (QR/BSSID invalide) : inutile de réessayer, ESP2 peut purger.
            return ['local_id' => $localId, 'status' => 'rejected', 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            Log::warning('relay.sync: échec de traitement d\'un paquet', ['local_id' => $localId, 'error' => $e->getMessage()]);

            // Erreur transitoire (token expiré au mauvais moment, enseignant introuvable, etc.)
            // : on ne renvoie pas "ok", ESP2 le retentera au prochain cycle.
            return ['local_id' => $localId, 'status' => 'retry', 'message' => 'Traitement impossible pour le moment.'];
        }
    }

    private function recordProxyPacket(Enseignant $acteur, array $payload, Carbon $capturedAt, Device $relay, ?string $photoBase64)
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

    /** Résout l'enseignant à partir du token Sanctum émis à son app lors de l'activation. */
    private function resolveTeacher(string $plainTextToken): Enseignant
    {
        $accessToken = PersonalAccessToken::findToken($plainTextToken);

        if (! $accessToken || ! $accessToken->tokenable instanceof Enseignant) {
            throw ValidationException::withMessages(['teacher_token' => ['Token enseignant invalide.']]);
        }

        return $accessToken->tokenable;
    }
}
