<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\AccessPoint;
use App\Models\Device;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Models\QrPoint;
use App\Models\TeacherNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Réception des scans de présence, quel que soit le canal (§4.3, §5.3, §7).
 *
 * L'horodatage est toujours généré côté serveur (now()) — jamais accepté depuis
 * la requête du client, conformément aux exigences non fonctionnelles (§7).
 */
class AttendanceController extends Controller
{
    use AccessibleEnseignants;

    /** POST /api/attendance/scan — scan par l'enseignant lui-même (app + QR + BSSID). */
    public function scan(Request $request)
    {
        $data = $request->validate([
            'qr_code' => ['required', 'string'],
            'bssid' => ['required', 'string'],
        ]);

        $enseignant = $this->authenticatedEnseignant($request);
        $device = $this->authenticatedDevice($request, $enseignant);

        QrPoint::where('code', $data['qr_code'])->firstOrFail();
        $accessPoint = AccessPoint::where('bssid', $data['bssid'])->first();

        if (! $accessPoint) {
            throw ValidationException::withMessages([
                'bssid' => ['Borne WiFi non reconnue.'],
            ]);
        }

        $presence = $this->enregistrerPointage($enseignant->id, [
            'source' => 'app_mobile',
            'access_point_id' => $accessPoint->id,
            'device_id' => $device?->id,
        ]);

        return response()->json($presence, 201);
    }

    /** POST /api/attendance/admin-proxy — scan par procuration, rôle restreint (§4.1). */
    public function adminProxy(Request $request)
    {
        $data = $request->validate([
            'enseignant_id' => ['required', 'exists:enseignants,id'],
            'qr_code' => ['required', 'string'],
            'bssid' => ['required', 'string'],
            'motif' => ['required', 'string', 'max:255'],
        ]);

        $acteur = $this->authenticatedEnseignant($request);
        $device = $this->authenticatedDevice($request, $acteur);
        $cible = Enseignant::findOrFail($data['enseignant_id']);

        QrPoint::where('code', $data['qr_code'])->firstOrFail();
        $accessPoint = AccessPoint::where('bssid', $data['bssid'])->first();

        if (! $accessPoint) {
            throw ValidationException::withMessages([
                'bssid' => ['Borne WiFi non reconnue.'],
            ]);
        }

        $presence = $this->enregistrerPointage($cible->id, [
            'source' => 'admin_proxy',
            'access_point_id' => $accessPoint->id,
            'device_id' => $device?->id,
            'reason' => $data['motif'],
        ]);

        // §4.1 : l'enseignant concerné est notifié qu'un tiers a scanné en son nom.
        TeacherNotification::create([
            'enseignant_id' => $cible->id,
            'type' => 'scan_procuration',
            'message' => "{$acteur->nom} a scanné votre présence en votre nom ({$data['motif']}).",
        ]);

        return response()->json($presence, 201);
    }

    /** POST /api/attendance/facial-scan — poste de reconnaissance faciale, authentifié comme lui-même (§5.3, §5.4). */
    public function facialScan(Request $request)
    {
        $data = $request->validate([
            'enseignant_id' => ['required', 'exists:enseignants,id'],
            'score_confiance' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        $device = $request->user();

        if (! $device instanceof Device || $device->device_type !== 'kiosk_facial' || $device->isRevoked()) {
            abort(403, 'Authentification poste de reconnaissance faciale requise.');
        }

        $presence = $this->enregistrerPointage($data['enseignant_id'], [
            'source' => 'reconnaissance_faciale',
            'device_id' => $device->id,
            'reason' => "score_confiance={$data['score_confiance']}",
        ]);

        return response()->json($presence, 201);
    }

    /**
     * Alterne heure_arrivee / heure_depart sur la ligne du jour, en la créant si besoin.
     * L'horodatage (now()) est toujours généré ici, côté serveur.
     */
    private function enregistrerPointage(int $enseignantId, array $attributs): Presence
    {
        $presence = Presence::firstOrNew([
            'enseignant_id' => $enseignantId,
            'date' => now()->toDateString(),
        ]);

        if (! $presence->exists || $presence->heure_arrivee === null) {
            $presence->heure_arrivee = now();
        } else {
            $presence->heure_depart = now();
        }

        $presence->fill($attributs);
        $presence->save();

        return $presence;
    }

    private function authenticatedEnseignant(Request $request): Enseignant
    {
        $user = $request->user();

        if (! $user instanceof Enseignant) {
            abort(403, 'Authentification device (enseignant) requise.');
        }

        return $user;
    }

    private function authenticatedDevice(Request $request, Enseignant $enseignant): ?Device
    {
        $tokenName = $request->user()->currentAccessToken()?->name;

        return Device::where('teacher_id', $enseignant->id)
            ->where('device_uuid', $tokenName)
            ->whereNull('revoked_at')
            ->first();
    }
}
