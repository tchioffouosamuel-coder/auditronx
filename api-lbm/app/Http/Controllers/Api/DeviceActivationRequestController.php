<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceActivationRequest;
use App\Models\Otp;
use App\Models\TeacherNotification;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;

/**
 * Traitement, côté administration, des demandes d'activation créées par les
 * enseignants non-admin lors de l'identification (§4.1 revu, §otp-approval).
 *
 * L'OTP est désormais généré dès la demande (DeviceController::requestActivation)
 * plutôt qu'ici : l'admin ne fait plus que valider/refuser (notification de
 * validation façon "activité suspecte" Google, Valider/Refuser) — sur
 * validation, le code est poussé à l'enseignant par notification push.
 */
class DeviceActivationRequestController extends Controller
{
    public function __construct(private PushNotificationService $push) {}

    /** GET /api/devices/activation-requests?statut=en_attente|toutes */
    public function index(Request $request)
    {
        $query = DeviceActivationRequest::with(['enseignant', 'otp'])->orderByDesc('requested_at');

        if ($request->query('statut', 'en_attente') === 'en_attente') {
            $query->whereNull('fulfilled_at')->whereNull('rejected_at');
        }

        $requests = $query->paginate(30);

        $requests->getCollection()->transform(function (DeviceActivationRequest $r) {
            $r->code = $r->otp?->isValid() ? $r->otp->code : null;
            $r->unsetRelation('otp');

            return $r;
        });

        return response()->json($requests);
    }

    /** POST /api/devices/activation-requests/{activationRequest}/approve — envoie l'OTP à l'enseignant. */
    public function approve(DeviceActivationRequest $activationRequest)
    {
        abort_if($activationRequest->fulfilled_at || $activationRequest->rejected_at, 409, 'Demande déjà traitée.');

        $otp = $activationRequest->otp;
        $code = $otp?->isValid() ? $otp->code : null;
        abort_unless($code, 410, "Code expiré, l'enseignant doit refaire une demande.");

        // sendToTeacher() (via Device.fcm_token) ne peut pas servir ici :
        // l'enseignant n'a pas encore de device activé à ce stade. On pousse
        // donc directement au token capturé côté app lors de la demande
        // (§otp-approval, DeviceController::requestActivation).
        if ($activationRequest->fcm_token) {
            $this->push->sendToToken(
                $activationRequest->fcm_token,
                "Code d'activation",
                "Votre code d'activation : {$code}",
                ['type' => 'otp_delivery', 'code' => $code]
            );
        }

        TeacherNotification::create([
            'enseignant_id' => $activationRequest->enseignant_id,
            'type' => 'otp_delivery',
            'message' => "Code d'activation envoyé : {$code}",
        ]);

        $activationRequest->update(['fulfilled_at' => now()]);

        return response()->json($activationRequest->fresh());
    }

    /** POST /api/devices/activation-requests/{activationRequest}/reject */
    public function reject(DeviceActivationRequest $activationRequest)
    {
        abort_if($activationRequest->fulfilled_at || $activationRequest->rejected_at, 409, 'Demande déjà traitée.');

        if ($activationRequest->otp_id) {
            Otp::whereKey($activationRequest->otp_id)->update(['used_at' => now(), 'code' => null]);
        }

        $activationRequest->update(['rejected_at' => now()]);

        return response()->json($activationRequest->fresh());
    }
}
