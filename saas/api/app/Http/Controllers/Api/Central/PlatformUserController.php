<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\PlatformUser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Gestion des comptes du personnel Auditron, réservée au super-administrateur. */
class PlatformUserController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => PlatformUser::query()
                ->withMax('tokens', 'last_used_at')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:central.platform_users,email'],
            'role' => ['required', Rule::in(PlatformUser::ROLES)],
            'password' => ['nullable', 'string', 'min:12'],
            'actif' => ['sometimes', 'boolean'],
        ]);
        $password = $data['password'] ?? Str::password(16, symbols: false);

        $utilisateur = PlatformUser::create([
            ...$data,
            'password' => $password,
            'actif' => $data['actif'] ?? true,
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'data' => $utilisateur,
            'password_initial' => $password,
        ], 201);
    }

    public function update(Request $request, PlatformUser $platformUser)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('central.platform_users', 'email')->ignore($platformUser->id)],
            'role' => ['sometimes', 'required', Rule::in(PlatformUser::ROLES)],
            'password' => ['sometimes', 'nullable', 'string', 'min:12'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $role = $data['role'] ?? $platformUser->role;
        $actif = $data['actif'] ?? $platformUser->actif;

        if ((! $actif || $role !== PlatformUser::ROLE_SUPER_ADMIN)
            && $platformUser->role === PlatformUser::ROLE_SUPER_ADMIN
            && $platformUser->actif
            && PlatformUser::query()
                ->where('role', PlatformUser::ROLE_SUPER_ADMIN)
                ->where('actif', true)
                ->count() <= 1) {
            return response()->json([
                'message' => 'Le dernier super-administrateur actif ne peut pas être désactivé ni rétrogradé.',
            ], 422);
        }

        if ($platformUser->is(auth()->user()) && (! $actif || $role !== PlatformUser::ROLE_SUPER_ADMIN)) {
            return response()->json([
                'message' => 'Vous ne pouvez pas désactiver ni rétrograder votre propre compte.',
            ], 422);
        }

        if (array_key_exists('password', $data) && $data['password'] === null) {
            unset($data['password']);
        }

        $platformUser->update($data);

        if ((array_key_exists('actif', $data) && ! $data['actif']) || array_key_exists('password', $data)) {
            $platformUser->tokens()->delete();
        }

        return response()->json(['data' => $platformUser->fresh()]);
    }
}
