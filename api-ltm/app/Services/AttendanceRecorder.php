<?php

namespace App\Services;

use App\Models\AccessPoint;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Models\QrPoint;
use App\Models\TeacherNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Logique métier partagée pour l'enregistrement d'un pointage (§4.3, §7),
 * utilisée aussi bien par les endpoints /attendance/* en direct que par la
 * synchronisation différée de la passerelle offline (ESP1 borne + ESP2
 * relais, §hardware).
 *
 * L'horodatage n'est jamais "now()" en dur ici : l'appelant le fournit
 * explicitement — au moment de la requête pour un scan direct, ou tel que
 * capturé par la borne pour un paquet relayé (voir RelaySyncController).
 */
class AttendanceRecorder
{
    public function __construct(private readonly PushNotificationService $push) {}

    public function recordSelfScan(
        Enseignant $enseignant,
        ?int $deviceId,
        string $qrCode,
        string $bssid,
        Carbon $timestamp,
        string $source = 'app_mobile',
        ?Carbon $deviceCaptureAt = null,
        ?string $photoBase64 = null,
    ): Presence {
        $accessPoint = $this->resolveAccessPoint($qrCode, $bssid);

        return $this->record($enseignant->id, $timestamp, [
            'source' => $source,
            'access_point_id' => $accessPoint->id,
            'device_id' => $deviceId,
            'device_capture_at' => $deviceCaptureAt,
        ], $photoBase64);
    }

    /**
     * $acteur est le plus souvent un enseignant à rôle restreint, mais peut
     * aussi être un admin du backoffice (`User`, §admin-mobile) : seul son nom
     * sert ici (message de notification), jamais son id — il n'est pas la
     * cible du pointage.
     */
    public function recordProxyScan(
        Enseignant|User $acteur,
        ?int $deviceId,
        Enseignant $cible,
        string $qrCode,
        string $bssid,
        string $motif,
        Carbon $timestamp,
        string $source = 'admin_proxy',
        ?Carbon $deviceCaptureAt = null,
        ?string $photoBase64 = null,
    ): Presence {
        $accessPoint = $this->resolveAccessPoint($qrCode, $bssid);

        $presence = $this->record($cible->id, $timestamp, [
            'source' => $source,
            'access_point_id' => $accessPoint->id,
            'device_id' => $deviceId,
            'reason' => $motif,
            'device_capture_at' => $deviceCaptureAt,
        ], $photoBase64);

        // §4.1 : l'enseignant concerné est notifié qu'un tiers a scanné en son nom.
        $acteurNom = $acteur instanceof Enseignant ? $acteur->nom : $acteur->name;
        $message = "{$acteurNom} a scanné votre présence en votre nom ({$motif}).";

        TeacherNotification::create([
            'enseignant_id' => $cible->id,
            'type' => 'scan_procuration',
            'message' => $message,
        ]);

        $this->push->sendToTeacher($cible->id, 'Pointage par procuration', $message, [
            'type' => 'scan_procuration',
            'presence_id' => $presence->id,
        ]);

        return $presence;
    }

    private function resolveAccessPoint(string $qrCode, string $bssid): AccessPoint
    {
        // ValidationException (pas firstOrFail/ModelNotFoundException) : un QR
        // code inconnu ne se "réparera" jamais tout seul, RelaySyncController
        // doit le classer "rejected" (rejet définitif, purgé de la file de la
        // borne) plutôt que "retry" (sinon boucle de réessai infinie, §hardware).
        if (! QrPoint::where('code', $qrCode)->exists()) {
            throw ValidationException::withMessages([
                'qr_code' => ['QR code non reconnu.'],
            ]);
        }

        $accessPoint = AccessPoint::where('bssid', $bssid)->first();

        if (! $accessPoint) {
            throw ValidationException::withMessages([
                'bssid' => ['Borne WiFi non reconnue.'],
            ]);
        }

        return $accessPoint;
    }

    /** Alterne heure_arrivee / heure_depart sur la ligne du jour, en la créant si besoin. */
    private function record(int $enseignantId, Carbon $timestamp, array $attributs, ?string $photoBase64 = null): Presence
    {
        $presence = Presence::firstOrNew([
            'enseignant_id' => $enseignantId,
            'date' => $timestamp->toDateString(),
        ]);

        $estArrivee = ! $presence->exists || $presence->heure_arrivee === null;

        if ($estArrivee) {
            $presence->heure_arrivee = $timestamp;
        } else {
            $presence->heure_depart = $timestamp;
        }

        if ($photoBase64) {
            $path = $this->storePhoto($photoBase64);
            if ($path) {
                $attributs[$estArrivee ? 'photo_path_arrivee' : 'photo_path_depart'] = $path;
            }
        }

        $presence->fill($attributs);
        $presence->save();

        return $presence;
    }

    /**
     * Décode la photo JPEG en base64 remontée par la borne ESP32-S3 + OV5640
     * (§hardware) et la stocke directement dans `public/` (disque
     * `public_direct` — `symlink()`/`exec()` désactivés en prod, donc
     * `storage:link` est inutilisable sur cet hébergement). Best-effort : une
     * photo illisible ne doit jamais faire échouer l'enregistrement du pointage.
     */
    private function storePhoto(string $photoBase64): ?string
    {
        try {
            $binary = base64_decode($photoBase64, strict: true);
            if ($binary === false || $binary === '') {
                return null;
            }

            $path = 'scan-photos/'.date('Y/m/d').'/'.Str::uuid().'.jpg';
            Storage::disk('public_direct')->put($path, $binary);

            return $path;
        } catch (\Throwable $e) {
            Log::warning('attendance.storePhoto: échec décodage/stockage', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
