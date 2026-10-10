<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Moniteur série à distance des bornes relais (§hardware) : la borne pousse
 * les lignes qu'elle imprime sur son port série, le backoffice les consulte
 * en quasi temps réel (polling incrémental sur `after_id`).
 */
class DeviceLogController extends Controller
{
    /**
     * POST /api/relay/logs — appelé par la borne elle-même (token relay_gateway).
     *
     * `uptime_ms` (racine) = uptime de la borne au moment de l'envoi ; chaque
     * ligne porte son propre `uptime_ms` d'impression. L'heure réelle d'une
     * ligne est reconstituée à partir de l'écart entre les deux, rapporté à
     * l'heure du serveur : la borne n'a pas besoin d'être synchronisée NTP
     * (lignes du boot, WiFi pas encore connecté...).
     */
    public function store(Request $request)
    {
        $device = $request->user();

        if (! $device instanceof Device || $device->device_type !== 'relay_gateway' || $device->isRevoked()) {
            abort(403, 'Authentification passerelle relais requise.');
        }

        $data = $request->validate([
            'uptime_ms' => ['required', 'integer', 'min:0'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.uptime_ms' => ['required', 'integer', 'min:0'],
            'lines.*.message' => ['required', 'string', 'max:1000'],
        ]);

        $now = now();
        $rows = array_map(function (array $line) use ($data, $device, $now) {
            // Borné à 0 : ne jamais dater une ligne dans le futur si la borne
            // envoie des valeurs incohérentes.
            $ageMs = max(0, $data['uptime_ms'] - $line['uptime_ms']);

            return [
                'device_id' => $device->id,
                'message' => $line['message'],
                'uptime_ms' => $line['uptime_ms'],
                'logged_at' => $now->copy()->subMilliseconds($ageMs),
                'created_at' => $now,
            ];
        }, $data['lines']);

        DeviceLog::insert($rows);

        // Purge paresseuse, ~1 appel sur 50 : évite une tâche planifiée dédiée
        // sans payer un DELETE à chaque envoi (la borne pousse toutes les ~5 s).
        if (random_int(1, 50) === 1) {
            DeviceLog::where('logged_at', '<', $now->copy()->subDays(DeviceLog::RETENTION_DAYS))->delete();
        }

        return response()->json(['stored' => count($rows)], 201);
    }

    /**
     * GET /api/devices/{device}/logs?after_id=&limit= — backoffice.
     *
     * Sans `after_id` : les `limit` dernières lignes (chargement initial).
     * Avec `after_id` : uniquement les lignes plus récentes (polling).
     * Toujours renvoyées dans l'ordre chronologique.
     */
    public function index(Request $request, Device $device)
    {
        $data = $request->validate([
            'after_id' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);
        $limit = $data['limit'] ?? 500;

        $query = DeviceLog::where('device_id', $device->id)
            ->select(['id', 'message', 'logged_at', 'uptime_ms']);

        if (isset($data['after_id'])) {
            $logs = $query->where('id', '>', $data['after_id'])
                ->orderBy('id')
                ->limit($limit)
                ->get();
        } else {
            $logs = $query->orderByDesc('id')->limit($limit)->get()->reverse()->values();
        }

        // max() renvoie la valeur brute (heure locale sans fuseau) : convertie
        // en ISO 8601 pour que le navigateur ne l'interprète pas dans son propre fuseau.
        $lastSeen = DeviceLog::where('device_id', $device->id)->max('created_at');

        return response()->json([
            'data' => $logs,
            'last_seen_at' => $lastSeen ? Carbon::parse($lastSeen)->toIso8601String() : null,
        ]);
    }

    /** DELETE /api/devices/{device}/logs — vide la console de cette borne. */
    public function destroy(Device $device)
    {
        $deleted = DeviceLog::where('device_id', $device->id)->delete();

        return response()->json(['deleted' => $deleted]);
    }
}
