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
 * Passerelle offline (§hardware) : ESP1 (borne) reçoit le pointage du
 * téléphone en local (WiFi + HTTP, sans internet) et le transmet à ESP2
 * (relais) via ESP-NOW. ESP2 met en file, puis — dès que sa connexion au
 * modem est disponible — pousse les paquets en attente ici par lots. Chaque
 * paquet accusé de réception (`ok`) peut être supprimé de la file d'ESP2 ;
 * les autres restent en file pour une prochaine tentative.
 *
 * Authentification : uniquement le device relais (Device, device_type
 * `relay_gateway`) — jamais le téléphone directement. L'identité de
 * l'enseignant à l'intérieur de chaque paquet est résolue à partir du token
 * Sanctum que son app a émis au moment du scan (`teacher_token`), exactement
 * comme si la requête avait atteint l'API directement : le relais ne fait que
 * rejouer, en différé, une requête que le téléphone avait déjà authentifiée.
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

        $data = $request->validate([
            'packets' => ['required', 'array', 'min:1', 'max:100'],
            'packets.*.local_id' => ['required', 'string'],
            'packets.*.type' => ['required', 'in:scan,admin_proxy'],
            'packets.*.captured_at' => ['required', 'date'],
            'packets.*.teacher_token' => ['required', 'string'],
            'packets.*.payload' => ['required', 'array'],
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

            $presence = $packet['type'] === 'scan'
                ? $this->recorder->recordSelfScan(
                    $acteur,
                    null,
                    (string) ($payload['qr_code'] ?? ''),
                    (string) ($payload['bssid'] ?? ''),
                    $capturedAt,
                    source: 'app_mobile',
                    deviceCaptureAt: $capturedAt,
                )
                : $this->recordProxyPacket($acteur, $payload, $capturedAt, $relay);

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

    private function recordProxyPacket(Enseignant $acteur, array $payload, Carbon $capturedAt, Device $relay)
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
