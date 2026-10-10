<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Firmware;
use App\Services\FirmwareService;
use Illuminate\Http\Request;

/**
 * Mises à jour OTA des bornes relais (§hardware).
 *
 * Backoffice : upload / activation / suppression des firmwares par borne.
 * Borne (token relay_gateway) : manifest de la version active + téléchargement.
 */
class FirmwareController extends Controller
{
    public function __construct(private readonly FirmwareService $service) {}

    /** GET /api/firmwares?device_id= */
    public function index(Request $request)
    {
        $firmwares = Firmware::with(['device:id,device_uuid', 'uploader:id,name'])
            ->when($request->query('device_id'), fn($q, $id) => $q->where('device_id', $id))
            ->orderByDesc('created_at')
            ->get();

        return response()->json($firmwares);
    }

    /** POST /api/firmwares (multipart : device_id, version, release_notes?, firmware) */
    public function store(Request $request)
    {
        $data = $request->validate([
            'device_id' => ['required', 'integer', 'exists:devices,id'],
            'version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+$/'],
            'release_notes' => ['nullable', 'string', 'max:2000'],
            // Emplacement OTA de 1,5 Mo (hardware/esp32dev_borne/partitions.csv) :
            // 4 Mo laisse de la marge si la table évolue.
            'firmware' => ['required', 'file', 'extensions:bin', 'max:4096'],
        ], [
            'version.regex' => 'La version doit être au format x.y.z (ex : 1.2.0).',
            'firmware.extensions' => 'Le fichier doit avoir l\'extension .bin.',
        ]);

        $device = Device::findOrFail($data['device_id']);
        abort_unless($device->device_type === 'relay_gateway', 422, 'Seule une borne relais peut recevoir un firmware.');

        $firmware = $this->service->store($device, $request->file('firmware'), $data['version'], $data['release_notes'] ?? null, $request->user());

        return response()->json($firmware, 201);
    }

    /** POST /api/firmwares/{firmware}/activate */
    public function activate(Firmware $firmware)
    {
        $this->service->activate($firmware);

        return response()->json($firmware->fresh());
    }

    /** POST /api/firmwares/{firmware}/deactivate */
    public function deactivate(Firmware $firmware)
    {
        $this->service->deactivate($firmware);

        return response()->json($firmware->fresh());
    }

    /** DELETE /api/firmwares/{firmware} */
    public function destroy(Firmware $firmware)
    {
        $this->service->delete($firmware);

        return response()->json(['deleted' => true]);
    }

    /**
     * GET /api/relay/firmware/manifest — appelé périodiquement par la borne.
     *
     * L'en-tête `X-Firmware-Version` (version compilée de la borne) est
     * enregistré pour l'affichage backoffice. 204 si aucune version active :
     * la borne garde son firmware. La borne flashe dès que `version` diffère
     * de la sienne — activer une version plus ancienne fait donc un rollback.
     */
    public function manifest(Request $request)
    {
        $device = $this->relayDevice($request);

        $device->forceFill([
            'firmware_version' => $request->header('X-Firmware-Version') ?: $device->firmware_version,
            'firmware_checked_at' => now(),
        ])->save();

        $firmware = Firmware::where('device_id', $device->id)->where('is_active', true)->first();
        if (! $firmware) {
            return response()->noContent();
        }

        return response()->json([
            'version' => $firmware->version,
            'url' => url("/api/relay/firmware/{$firmware->id}/download"),
            'sha256' => $firmware->sha256,
            'size_bytes' => $firmware->size_bytes,
        ]);
    }

    /** GET /api/relay/firmware/{firmware}/download — réservé à la borne destinataire. */
    public function download(Request $request, Firmware $firmware)
    {
        $device = $this->relayDevice($request);
        abort_unless($firmware->device_id === $device->id, 404);

        return response()->file($this->service->absolutePath($firmware), [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    private function relayDevice(Request $request): Device
    {
        $device = $request->user();

        if (! $device instanceof Device || $device->device_type !== 'relay_gateway' || $device->isRevoked()) {
            abort(403, 'Authentification passerelle relais requise.');
        }

        return $device;
    }
}
