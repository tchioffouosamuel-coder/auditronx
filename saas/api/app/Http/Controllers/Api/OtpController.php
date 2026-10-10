<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enseignant;
use App\Models\Otp;
use Illuminate\Http\Request;

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
            'code' => $code,
            'expires_at' => now()->addMinutes(15),
        ]);

        return response()->json([
            'otp_id' => $otp->id,
            'code' => $code,
            'expires_at' => $otp->expires_at,
        ], 201);
    }
}
