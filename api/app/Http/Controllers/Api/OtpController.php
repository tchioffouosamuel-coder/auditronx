<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enseignant;
use App\Models\Otp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OtpController extends Controller
{
    /** POST /api/otp/generate — génère un OTP pour un enseignant (action admin, §4.3). */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'enseignant_id' => ['required', 'exists:enseignants,id'],
        ]);

        $enseignant = Enseignant::findOrFail($data['enseignant_id']);

        $code = (string) random_int(100000, 999999);

        $otp = Otp::create([
            'teacher_id' => $enseignant->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(15),
        ]);

        return response()->json([
            'otp_id' => $otp->id,
            // Le code n'est jamais stocké en clair : il n'est visible qu'ici, à transmettre à l'enseignant.
            'code' => $code,
            'expires_at' => $otp->expires_at,
        ], 201);
    }
}
