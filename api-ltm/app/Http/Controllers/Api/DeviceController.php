<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Enseignant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DeviceController extends Controller
{
    /**
     * POST /api/devices/request-activation — identification par téléphone et
     * mot de passe, puis activation immédiate du téléphone.
     */
    public function requestActivation(Request $request)
    {
        $data = $request->validate([
            'tel' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_uuid' => ['required', 'string'],
            'device_type' => ['sometimes', 'in:mobile'],
        ]);

        $enseignant = Enseignant::where('tel', $data['tel'])->first();

        if (! $enseignant || ! $enseignant->password || ! Hash::check($data['password'], $enseignant->password)) {
            throw ValidationException::withMessages([
                'tel' => ['Identifiants invalides.'],
            ]);
        }

        $this->ensureTeacherDeviceAvailable($enseignant, $data['device_uuid']);

        $device = Device::updateOrCreate(
            ['device_uuid' => $data['device_uuid']],
            [
                'teacher_id' => $enseignant->id,
                'device_type' => $data['device_type'] ?? 'mobile',
                'activated_at' => now(),
                'otp_id' => null,
                'revoked_at' => null,
            ]
        );
        $enseignant->tokens()->where('name', $data['device_uuid'])->delete();

        $token = $enseignant->createToken($data['device_uuid'])->plainTextToken;

        return response()->json([
            'activated' => true,
            'token' => $token,
            'device' => $device,
        ], 201);
    }

    /** GET /api/devices — administration des appareils (§4.2). */
    public function index(Request $request)
    {
        $devices = Device::with('teacher')
            ->when($request->query('device_type'), fn($q, $v) => $q->where('device_type', $v))
            ->when($request->query('revoked') !== null, fn($q) => $request->boolean('revoked')
                ? $q->whereNotNull('revoked_at')
                : $q->whereNull('revoked_at'))
            ->orderByDesc('activated_at')
            ->get();

        // Liste complète, sans pagination ; l'enveloppe `data` est conservée
        // pour les clients web et mobile.
        return response()->json(['data' => $devices]);
    }

    /** Un enseignant ne peut conserver qu'un seul téléphone actif à la fois. */
    private function ensureTeacherDeviceAvailable(Enseignant $enseignant, string $deviceUuid): void
    {
        $activeDevice = Device::where('teacher_id', $enseignant->id)
            ->whereNull('revoked_at')
            ->first();

        if ($activeDevice && $activeDevice->device_uuid !== $deviceUuid) {
            throw ValidationException::withMessages([
                'device_uuid' => ['Cet enseignant est déjà lié à un autre téléphone. Révoquez d’abord l’ancien téléphone.'],
            ]);
        }

        $knownDevice = Device::where('device_uuid', $deviceUuid)
            ->whereNotNull('teacher_id')
            ->where('teacher_id', '!=', $enseignant->id)
            ->exists();

        if ($knownDevice) {
            throw ValidationException::withMessages([
                'device_uuid' => ['Ce téléphone est déjà lié à un autre enseignant.'],
            ]);
        }
    }

    /** POST /api/devices/{device}/revoke — révocation d'un device (administration des appareils, §4.2). */
    public function revoke(Device $device)
    {
        $device->update(['revoked_at' => now()]);
        $device->tokens()->delete();
        $device->teacher?->tokens()->where('name', $device->device_uuid)->delete();

        return response()->json($device);
    }

    /** POST /api/devices/{device}/rotate-token — renouvelle le token d'une borne relais. */
    public function rotateToken(Device $device)
    {
        abort_unless($device->device_type === 'relay_gateway', 422, 'Seule une borne relais peut recevoir ce type de token.');

        $device->tokens()->delete();
        $device->update([
            'activated_at' => now(),
            'revoked_at' => null,
        ]);

        $token = $device->createToken($device->device_uuid)->plainTextToken;

        return response()->json([
            'token' => $token,
            'device' => $device,
        ]);
    }

    /**
     * POST /api/devices/provision-relay — provisionne la passerelle offline ESP2
     * (§hardware, seul le relais s'authentifie auprès de l'API ; ESP1 ne parle
     * qu'en local à ESP2 via ESP-NOW). Action admin, même schéma que le kiosk :
     * le device s'authentifie ensuite lui-même via son propre token Sanctum.
     */
    public function provisionRelay(Request $request)
    {
        $data = $request->validate([
            'device_uuid' => ['required', 'string', 'unique:devices,device_uuid'],
            'label' => ['nullable', 'string'],
        ]);

        $device = Device::create([
            'device_uuid' => $data['device_uuid'],
            'device_type' => 'relay_gateway',
            'activated_at' => now(),
        ]);

        $token = $device->createToken($data['device_uuid'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'device' => $device,
        ], 201);
    }

    /**
     * POST /api/devices/fcm-token — enregistre/rafraîchit le token FCM du device
     * courant (push notifications). Appelé par l'app mobile au démarrage et à
     * chaque rotation de token Firebase.
     */
    public function updateFcmToken(Request $request)
    {
        $data = $request->validate(['fcm_token' => ['required', 'string']]);

        $principal = $request->user();

        $device = $principal instanceof Device
            ? $principal
            : Device::where('teacher_id', $principal->id)
            ->where('device_uuid', $principal->currentAccessToken()?->name)
            ->whereNull('revoked_at')
            ->first();

        abort_unless($device, 404, 'Device introuvable pour cette session.');

        $device->update(['fcm_token' => $data['fcm_token']]);

        return response()->json(['updated' => true]);
    }
}
