<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\PlatformUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Authentification du personnel Auditron (portail éditeur).
 *
 * Volontairement séparée de l'authentification des établissements : un compte
 * éditeur ne doit jamais pouvoir se présenter sur les routes métier d'un
 * client, et les tokens des deux mondes vivent dans deux bases différentes.
 */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $utilisateur = PlatformUser::where('email', $data['email'])->first();

        if (! $utilisateur || ! Hash::check($data['password'], $utilisateur->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        if (! $utilisateur->actif) {
            throw ValidationException::withMessages([
                'email' => ['Ce compte est désactivé.'],
            ]);
        }

        return response()->json([
            'token' => $utilisateur->createToken('plateforme')->plainTextToken,
            'user' => $utilisateur,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
