<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Authentification par token du backoffice (session React), distincte des tokens device. */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'identifier' => ['required_without:email', 'nullable', 'string'],
            'email' => ['required_without:identifier', 'nullable', 'email'],
            'password' => ['required', 'string'],
        ]);

        $identifier = $data['identifier'] ?? $data['email'];
        $user = User::where('email', $identifier)->first();

        if ($user && Hash::check($data['password'], $user->password)) {
            $token = $user->createToken('backoffice')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $user->load('accreditation'),
            ]);
        }

        $enseignant = Enseignant::where('tel', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if (! $enseignant || ! $enseignant->password || ! Hash::check($data['password'], $enseignant->password)) {
            throw ValidationException::withMessages([
                'identifier' => ['Identifiants invalides.'],
            ]);
        }

        $token = $enseignant->createToken('web-teacher')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $enseignant,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    /** Whoami générique — utilisé par le backoffice (User) et l'app mobile (Enseignant). */
    public function me(Request $request)
    {
        $principal = $request->user();

        return response()->json(
            $principal instanceof User ? $principal->load('accreditation') : $principal
        );
    }

    /**
     * POST /api/me/fcm-token — enregistre le token FCM du navigateur backoffice
     * (notifications de validation OTP, §otp-approval). Équivalent, côté admin,
     * de DeviceController::updateFcmToken côté enseignant/device.
     */
    public function updateFcmToken(Request $request)
    {
        $data = $request->validate(['fcm_token' => ['required', 'string']]);

        abort_unless($request->user() instanceof User, 403, 'Réservé au backoffice.');

        $request->user()->update(['fcm_token' => $data['fcm_token']]);

        return response()->json(['updated' => true]);
    }

    /**
     * PUT /api/me/password — changement de mot de passe self-service, commun
     * à l'app mobile (Enseignant) et au backoffice (User). Contrairement à
     * EnseignantController::update (gestion RH par un tiers), exige le mot de
     * passe actuel.
     */
    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $principal = $request->user();

        if (! Hash::check($data['current_password'], $principal->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mot de passe actuel incorrect.'],
            ]);
        }

        $principal->update(['password' => $data['password']]);

        return response()->json(['updated' => true]);
    }
}
