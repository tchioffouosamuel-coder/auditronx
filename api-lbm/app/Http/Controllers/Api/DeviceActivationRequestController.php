<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceActivationRequest;
use App\Models\Otp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Traitement, côté administration, des demandes d'activation créées par les
 * enseignants non-admin lors de l'identification. L'administration approuve
 * ou refuse la demande avant que le téléphone puisse finaliser l'activation.
 */
class DeviceActivationRequestController extends Controller
{
    /** GET /api/devices/activation-requests?statut=en_attente|toutes */
    public function index(Request $request): JsonResponse
    {
        $query = DeviceActivationRequest::with('enseignant')->orderByDesc('requested_at');

        if ($request->query('statut', 'en_attente') === 'en_attente') {
            $query->whereNull('fulfilled_at')->whereNull('rejected_at');
        }

        return response()->json($query->paginate(30));
    }

    /** POST /api/devices/activation-requests/{activationRequest}/approve */
    public function approve(DeviceActivationRequest $activationRequest): JsonResponse
    {
        abort_if($activationRequest->fulfilled_at || $activationRequest->rejected_at, 409, 'Demande déjà traitée.');

        $activationRequest->update(['fulfilled_at' => now()]);

        return response()->json($activationRequest->fresh());
    }

    /** POST /api/devices/activation-requests/{activationRequest}/reject */
    public function reject(DeviceActivationRequest $activationRequest): JsonResponse
    {
        abort_if($activationRequest->fulfilled_at || $activationRequest->rejected_at, 409, 'Demande déjà traitée.');

        if ($activationRequest->otp_id) {
            Otp::whereKey($activationRequest->otp_id)->update(['used_at' => now(), 'code' => null]);
        }

        $activationRequest->update(['rejected_at' => now()]);

        return response()->json($activationRequest->fresh());
    }
}
