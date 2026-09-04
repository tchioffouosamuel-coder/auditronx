<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Authentification par token du backoffice (session React), distincte des tokens device. */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        $token = $user->createToken('backoffice')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('accreditation'),
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
}
