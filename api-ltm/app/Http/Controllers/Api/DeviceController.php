<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceActivationRequest;
use App\Models\Enseignant;
use App\Models\Otp;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DeviceController extends Controller
{
    public function __construct(private PushNotificationService $push) {}

    /**
     * POST /api/devices/request-activation — identification par téléphone + mot
     * de passe (§4.1 revu). Un enseignant admin (`est_admin`) est activé
     * immédiatement ; sinon une demande d'activation est créée et l'OTP généré
     * dès maintenant (§otp-approval) — une notification de validation
     * (Valider/Refuser) est poussée aux admins ; sur validation, le code est
     * envoyé par notification push à ce téléphone, plus besoin de le remettre
     * en personne.
     */
    public function requestActivation(Request $request)
    {
        $data = $request->validate([
            'tel' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_uuid' => ['required', 'string'],
            'device_type' => ['sometimes', 'in:mobile,kiosk_facial'],
            // Capturé avant toute authentification (pas encore de device
            // Sanctum) : seul moyen de pousser l'OTP par notification une fois
            // l'admin d'accord (§otp-approval) plutôt que de le remettre en
            // personne.
            'fcm_token' => ['sometimes', 'nullable', 'string'],
        ]);

        $enseignant = Enseignant::where('tel', $data['tel'])->first();

        if (! $enseignant || ! $enseignant->password || ! Hash::check($data['password'], $enseignant->password)) {
            throw ValidationException::withMessages([
                'tel' => ['Identifiants invalides.'],
            ]);
        }

        $this->ensureTeacherDeviceAvailable($enseignant, $data['device_uuid']);

        if ($enseignant->est_admin) {
            // updateOrCreate plutôt que create() : le device_uuid (généré une
            // fois côté app et persisté sur le téléphone) peut déjà exister en
            // base si ce même appareil a été révoqué puis se ré-active — sans
            // ça, l'unicité de device_uuid fait planter l'activation avec une
            // erreur SQL brute au lieu de la réactiver proprement.
            $device = Device::updateOrCreate(
                ['device_uuid' => $data['device_uuid']],
                [
                    'teacher_id' => $enseignant->id,
                    'device_type' => $data['device_type'] ?? 'mobile',
                    'activated_at' => now(),
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

        $activationRequest = DeviceActivationRequest::updateOrCreate(
            ['enseignant_id' => $enseignant->id, 'device_uuid' => $data['device_uuid'], 'fulfilled_at' => null, 'rejected_at' => null],
            [
                'device_type' => $data['device_type'] ?? 'mobile',
                'fcm_token' => $data['fcm_token'] ?? null,
                'requested_at' => now(),
            ]
        );

        // Uniquement sur une demande réellement nouvelle : une requête déjà en
        // attente (retry/poll côté app) ne doit pas re-générer un OTP ni
        // spammer les admins d'une notification de validation à chaque appel.
        if ($activationRequest->wasRecentlyCreated) {
            $this->notifyAdminsOfActivationRequest($activationRequest, $enseignant);
        }

        return response()->json([
            'activated' => false,
            'activation_request_id' => $activationRequest->id,
            'message' => "Demande transmise à l'administration pour validation.",
        ], 202);
    }

    /**
     * Génère l'OTP dès la demande (plutôt qu'à la validation admin) et notifie
     * les admins pour approbation (§otp-approval — notification de type
     * "activité suspecte" avec Valider/Refuser). Le code en clair n'est jamais
     * persisté (même principe que Otp::code_hash) : il est mis en cache le
     * temps de la validation, lu par DeviceActivationRequestController::approve()
     * pour être poussé à l'enseignant une fois l'admin d'accord.
     */
    private function notifyAdminsOfActivationRequest(DeviceActivationRequest $activationRequest, Enseignant $enseignant): void
    {
        $code = (string) random_int(100000, 999999);

        $otp = Otp::create([
            'teacher_id' => $enseignant->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(15),
        ]);

        Cache::put("otp-plain:{$otp->id}", $code, now()->addMinutes(15));

        $activationRequest->update(['otp_id' => $otp->id]);

        $this->push->sendToAdmins(
            "Demande d'activation",
            "{$enseignant->nom} demande un code d'accès — code : {$code}",
            [
                'type' => 'otp_approval',
                'activation_request_id' => (string) $activationRequest->id,
                'enseignant_nom' => $enseignant->nom,
                'code' => $code,
            ]
        );
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

        $this->ensureTeacherDeviceAvailable($otp->teacher, $data['device_uuid']);

        $otp->update(['used_at' => now()]);

        // updateOrCreate : ce device_uuid (généré une fois côté app et
        // persisté sur le téléphone) peut déjà exister en base si cet
        // appareil a été révoqué puis se ré-active avec un nouvel OTP — sans
        // ça, l'unicité de device_uuid fait planter l'activation avec une
        // erreur SQL brute au lieu de la réactiver proprement.
        $device = Device::updateOrCreate(
            ['device_uuid' => $data['device_uuid']],
            [
                'teacher_id' => $otp->teacher_id,
                'device_type' => $data['device_type'] ?? 'mobile',
                'activated_at' => now(),
                'otp_id' => $otp->id,
                'revoked_at' => null,
            ]
        );
        $otp->teacher->tokens()->where('name', $data['device_uuid'])->delete();

        $token = $otp->teacher->createToken($data['device_uuid'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'device' => $device,
        ], 201);
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
