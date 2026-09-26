<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceActivationRequest;
use App\Models\Enseignant;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DeviceController extends Controller
{
    public function __construct(private PushNotificationService $push) {}

    /**
     * POST /api/devices/request-activation — identification par téléphone + mot
     * de passe (§4.1 revu). Un enseignant admin (`est_admin`) est activé
     * immédiatement ; sinon une demande d'activation est créée pour validation
     * par l'administration. Après approbation, l'enseignant termine l'activation
     * en confirmant ses identifiants et le même appareil.
     */
    public function requestActivation(Request $request): JsonResponse
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

        if ($enseignant->est_admin) {
            $session = $this->createTeacherDeviceSession(
                $enseignant,
                $data['device_uuid'],
                $data['device_type'] ?? 'mobile',
            );

            return response()->json([
                'activated' => true,
                'token' => $session['token'],
                'device' => $session['device'],
            ], 201);
        }

        $activationRequest = DeviceActivationRequest::query()
            ->where('enseignant_id', $enseignant->id)
            ->where('device_uuid', $data['device_uuid'])
            ->latest('requested_at')
            ->first();

        if (
            $activationRequest?->fulfilled_at
            && ! $activationRequest->rejected_at
            && ! $activationRequest->completed_at
        ) {
            $session = $this->createTeacherDeviceSession(
                $enseignant,
                $data['device_uuid'],
                $data['device_type'] ?? 'mobile',
            );
            $activationRequest->update(['completed_at' => now()]);

            return response()->json([
                'activated' => true,
                'token' => $session['token'],
                'device' => $session['device'],
            ], 201);
        }

        if (
            ! $activationRequest
            || $activationRequest->rejected_at
            || $activationRequest->completed_at
        ) {
            $activationRequest = DeviceActivationRequest::create([
                'enseignant_id' => $enseignant->id,
                'device_uuid' => $data['device_uuid'],
                'device_type' => $data['device_type'] ?? 'mobile',
                'requested_at' => now(),
            ]);
            $this->notifyAdminsOfActivationRequest($activationRequest, $enseignant);
        }

        return response()->json([
            'activated' => false,
            'activation_request_id' => $activationRequest->id,
            'message' => "Demande transmise à l'administration pour validation.",
        ], 202);
    }

    public function completeApprovedActivation(Request $request, DeviceActivationRequest $activationRequest): JsonResponse
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

        abort_unless(
            $activationRequest->enseignant_id === $enseignant->id
                && $activationRequest->device_uuid === $data['device_uuid'],
            403,
            'Cette demande ne correspond pas à cet appareil.',
        );
        abort_if($activationRequest->rejected_at, 403, 'La demande d’activation a été refusée.');
        abort_if($activationRequest->completed_at, 409, 'Cette demande a déjà été utilisée.');

        if (! $activationRequest->fulfilled_at) {
            return response()->json(['activated' => false], 202);
        }

        $session = $this->createTeacherDeviceSession(
            $enseignant,
            $data['device_uuid'],
            $data['device_type'] ?? 'mobile',
        );
        $activationRequest->update(['completed_at' => now()]);

        return response()->json([
            'activated' => true,
            'token' => $session['token'],
            'device' => $session['device'],
        ], 201);
    }

    private function notifyAdminsOfActivationRequest(DeviceActivationRequest $activationRequest, Enseignant $enseignant): void
    {
        $this->push->sendToAdmins(
            'Demande d’activation',
            "{$enseignant->nom} demande l’activation d’un téléphone.",
            [
                'type' => 'activation_request',
                'activation_request_id' => (string) $activationRequest->id,
                'enseignant_nom' => $enseignant->nom,
            ]
        );
    }

    /** @return array{device: Device, token: string} */
    private function createTeacherDeviceSession(Enseignant $enseignant, string $deviceUuid, string $deviceType): array
    {
        $this->ensureTeacherDeviceAvailable($enseignant, $deviceUuid);

        $device = Device::updateOrCreate(
            ['device_uuid' => $deviceUuid],
            [
                'teacher_id' => $enseignant->id,
                'device_type' => $deviceType,
                'activated_at' => now(),
                'otp_id' => null,
                'revoked_at' => null,
            ]
        );
        $enseignant->tokens()->where('name', $deviceUuid)->delete();

        return [
            'device' => $device,
            'token' => $enseignant->createToken($deviceUuid)->plainTextToken,
        ];
    }

    /** GET /api/devices — vue des appareils (§4.2 — administration des appareils). */
    public function index(Request $request)
    {
        $devices = Device::with('teacher')
            ->when($request->query('device_type'), fn($q, $v) => $q->where('device_type', $v))
            ->when($request->query('revoked') !== null, fn($q) => $request->boolean('revoked')
                ? $q->whereNotNull('revoked_at')
                : $q->whereNull('revoked_at'))
            ->orderByDesc('activated_at')
            ->paginate(30);

        return response()->json($devices);
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
