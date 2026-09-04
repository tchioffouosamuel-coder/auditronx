<?php

namespace App\Services;

use App\Models\AccessPoint;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Models\QrPoint;
use App\Models\TeacherNotification;
use Carbon\Carbon;
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
    public function recordSelfScan(
        Enseignant $enseignant,
        ?int $deviceId,
        string $qrCode,
        string $bssid,
        Carbon $timestamp,
        string $source = 'app_mobile',
        ?Carbon $deviceCaptureAt = null,
    ): Presence {
        $accessPoint = $this->resolveAccessPoint($qrCode, $bssid);

        return $this->record($enseignant->id, $timestamp, [
            'source' => $source,
            'access_point_id' => $accessPoint->id,
            'device_id' => $deviceId,
            'device_capture_at' => $deviceCaptureAt,
        ]);
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
    ): Presence {
        $accessPoint = $this->resolveAccessPoint($qrCode, $bssid);

        $presence = $this->record($cible->id, $timestamp, [
            'source' => $source,
            'access_point_id' => $accessPoint->id,
            'device_id' => $deviceId,
            'reason' => $motif,
            'device_capture_at' => $deviceCaptureAt,
        ]);

        // §4.1 : l'enseignant concerné est notifié qu'un tiers a scanné en son nom.
        TeacherNotification::create([
            'enseignant_id' => $cible->id,
            'type' => 'scan_procuration',
            'message' => "{$acteur->nom} a scanné votre présence en votre nom ({$motif}).",
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
    private function record(int $enseignantId, Carbon $timestamp, array $attributs): Presence
    {
        $presence = Presence::firstOrNew([
            'enseignant_id' => $enseignantId,
            'date' => $timestamp->toDateString(),
        ]);

        if (! $presence->exists || $presence->heure_arrivee === null) {
            $presence->heure_arrivee = $timestamp;
        } else {
            $presence->heure_depart = $timestamp;
        }

        $presence->fill($attributs);
        $presence->save();

        return $presence;
    }
}
