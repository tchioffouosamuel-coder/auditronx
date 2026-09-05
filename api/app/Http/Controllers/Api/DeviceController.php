<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceActivationRequest;
use App\Models\Enseignant;
use App\Models\Otp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DeviceController extends Controller
{
    /**
     * POST /api/devices/request-activation — identification par téléphone + mot
     * de passe (§4.1 revu). Un enseignant admin (`est_admin`) est activé
     * immédiatement ; sinon une demande d'activation est créée pour
     * l'administration, qui remettra un OTP en personne (§4.1, §4.3).
     */
    public function requestActivation(Request $request)
    {
        $data = $request->validate([
            'tel' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_uuid' => ['required', 'string'],
            'device_type' => ['sometimes', 'in:mobile,kiosk_facial'],
        ]);

        $enseignant = Enseignant::where('tel', $data['tel'])->first();

        if (! $enseignant || ! $enseignant->password || ! Hash::check($data['password'], $enseignant->password)) {
            throw ValidationException::withMessages([
                'tel' => ['Identifiants invalides.'],
            ]);
        }

        if ($enseignant->est_admin) {
            $device = Device::create([
                'teacher_id' => $enseignant->id,
                'device_uuid' => $data['device_uuid'],
                'device_type' => $data['device_type'] ?? 'mobile',
                'activated_at' => now(),
            ]);

            $token = $enseignant->createToken($data['device_uuid'])->plainTextToken;

            return response()->json([
                'activated' => true,
                'token' => $token,
                'device' => $device,
            ], 201);
        }

        $activationRequest = DeviceActivationRequest::updateOrCreate(
            ['enseignant_id' => $enseignant->id, 'device_uuid' => $data['device_uuid'], 'fulfilled_at' => null],
            ['device_type' => $data['device_type'] ?? 'mobile', 'requested_at' => now()]
        );

        return response()->json([
            'activated' => false,
            'activation_request_id' => $activationRequest->id,
            'message' => "Demande transmise à l'administration. Un code d'activation vous sera remis en personne.",
        ], 202);
    }

    /** GET /api/devices — vue des activations OTP / devices (§4.2 — administration des appareils). */
    public function index(Request $request)
    {
        $devices = Device::with('teacher')
            ->when($request->query('device_type'), fn ($q, $v) => $q->where('device_type', $v))
            ->when($request->query('revoked') !== null, fn ($q) => $request->boolean('revoked')
                ? $q->whereNotNull('revoked_at')
                : $q->whereNull('revoked_at'))
            ->orderByDesc('activated_at')
            ->paginate(30);

        return response()->json($devices);
    }

    /** POST /api/devices/activate — active un device par OTP (§4.3). */
    public function activate(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'device_uuid' => ['required', 'string'],
            'device_type' => ['sometimes', 'in:mobile,kiosk_facial'],
        ]);

        $otp = Otp::whereNull('used_at')
            ->where('expires_at', '>', now())
            ->get()
            ->first(fn (Otp $candidate) => Hash::check($data['code'], $candidate->code_hash));

        if (! $otp) {
            throw ValidationException::withMessages([
                'code' => ['Code OTP invalide ou expiré.'],
            ]);
        }

        $otp->update(['used_at' => now()]);

        $device = Device::create([
            'teacher_id' => $otp->teacher_id,
            'device_uuid' => $data['device_uuid'],
            'device_type' => $data['device_type'] ?? 'mobile',
            'activated_at' => now(),
            'otp_id' => $otp->id,
        ]);

        $token = $otp->teacher->createToken($data['device_uuid'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'device' => $device,
        ], 201);
    }

    /** POST /api/devices/{device}/revoke — révocation d'un device (administration des appareils, §4.2). */
    public function revoke(Device $device)
    {
        $device->update(['revoked_at' => now()]);
        $device->tokens()->delete();
        $device->teacher?->tokens()->where('name', $device->device_uuid)->delete();

        return response()->json($device);
    }

    /**
     * POST /api/devices/provision-kiosk — provisionne un poste de reconnaissance faciale
     * (§5, administration des appareils, §4.2). Action admin : le device s'authentifie
     * ensuite lui-même via son propre token, sans passer par un enseignant.
     */
    public function provisionKiosk(Request $request)
    {
        $data = $request->validate([
            'device_uuid' => ['required', 'string', 'unique:devices,device_uuid'],
            'label' => ['nullable', 'string'],
        ]);

        $device = Device::create([
            'device_uuid' => $data['device_uuid'],
            'device_type' => 'kiosk_facial',
            'activated_at' => now(),
        ]);

        $token = $device->createToken($data['device_uuid'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'device' => $device,
        ], 201);
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
