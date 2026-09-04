<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\Device;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Services\AttendanceRecorder;
use Illuminate\Http\Request;

/**
 * Réception des scans de présence, quel que soit le canal (§4.3, §5.3, §7).
 *
 * L'horodatage est toujours généré côté serveur (now()) — jamais accepté depuis
 * la requête du client — sauf pour les paquets relayés par la passerelle
 * offline (ESP1/ESP2, §hardware), traités par RelaySyncController, où l'on
 * fait exceptionnellement confiance à l'horodatage de capture du matériel de
 * la borne (device authentifié, pas le téléphone de l'enseignant).
 */
class AttendanceController extends Controller
{
    use AccessibleEnseignants;

    public function __construct(private readonly AttendanceRecorder $recorder) {}

    /** POST /api/attendance/scan — scan par l'enseignant lui-même (app + QR + BSSID). */
    public function scan(Request $request)
    {
        $data = $request->validate([
            'qr_code' => ['required', 'string'],
            'bssid' => ['required', 'string'],
        ]);

        $enseignant = $this->authenticatedEnseignant($request);
        $device = $this->authenticatedDevice($request, $enseignant);

        $presence = $this->recorder->recordSelfScan(
            $enseignant,
            $device?->id,
            $data['qr_code'],
            $data['bssid'],
            now(),
        );

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

        $presence = $this->recorder->recordProxyScan(
            $acteur,
            $device?->id,
            $cible,
            $data['qr_code'],
            $data['bssid'],
            $data['motif'],
            now(),
        );

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

        // Le scan facial n'a pas de QR/BSSID à valider : le kiosk fait lui-même office de point d'accès.
        $presence = Presence::firstOrNew([
            'enseignant_id' => $data['enseignant_id'],
            'date' => now()->toDateString(),
        ]);

        if (! $presence->exists || $presence->heure_arrivee === null) {
            $presence->heure_arrivee = now();
        } else {
            $presence->heure_depart = now();
        }

        $presence->fill([
            'source' => 'reconnaissance_faciale',
            'device_id' => $device->id,
            'reason' => "score_confiance={$data['score_confiance']}",
        ]);
        $presence->save();

        return response()->json($presence, 201);
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
