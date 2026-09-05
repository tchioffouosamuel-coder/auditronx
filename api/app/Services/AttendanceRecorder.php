<?php

namespace App\Services;

use App\Models\AccessPoint;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Models\QrPoint;
use App\Models\TeacherNotification;
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

    public function recordProxyScan(
        Enseignant $acteur,
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
        $message = "{$acteur->nom} a scanné votre présence en votre nom ({$motif}).";

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
        QrPoint::where('code', $qrCode)->firstOrFail();
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
     * (§hardware) et la stocke sur le disque public. Best-effort : une photo
     * illisible ne doit jamais faire échouer l'enregistrement du pointage.
     */
    private function storePhoto(string $photoBase64): ?string
    {
        try {
            $binary = base64_decode($photoBase64, strict: true);
            if ($binary === false || $binary === '') {
                return null;
            }

            $path = 'scan-photos/'.date('Y/m/d').'/'.Str::uuid().'.jpg';
            Storage::disk('public')->put($path, $binary);

            return $path;
        } catch (\Throwable $e) {
            Log::warning('attendance.storePhoto: échec décodage/stockage', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
