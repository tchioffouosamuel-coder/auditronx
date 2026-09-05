<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceActivationRequest;
use App\Models\Otp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Traitement, côté administration, des demandes d'activation créées par les
 * enseignants non-admin lors de l'identification (§4.1 revu).
 */
class DeviceActivationRequestController extends Controller
{
    /** GET /api/devices/activation-requests?statut=en_attente|toutes */
    public function index(Request $request)
    {
        $query = DeviceActivationRequest::with('enseignant')->orderByDesc('requested_at');

        if ($request->query('statut', 'en_attente') === 'en_attente') {
            $query->whereNull('fulfilled_at');
        }

        return response()->json($query->paginate(30));
    }

    /**
     * POST /api/devices/activation-requests/{activationRequest}/generate-otp
     * Génère l'OTP à remettre en personne à l'enseignant et marque la demande traitée.
     */
    public function generateOtp(DeviceActivationRequest $activationRequest)
    {
        $code = (string) random_int(100000, 999999);

        $otp = Otp::create([
            'teacher_id' => $activationRequest->enseignant_id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(15),
        ]);

        $activationRequest->update(['fulfilled_at' => now(), 'otp_id' => $otp->id]);

        return response()->json([
            'otp_id' => $otp->id,
            // Comme pour /otp/generate : le code n'est jamais stocké en clair, visible une seule fois ici.
            'code' => $code,
            'expires_at' => $otp->expires_at,
        ], 201);
    }
}
