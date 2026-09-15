<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accreditation;
use Illuminate\Http\Request;

/** Contrôle d'accès par rôle (§4.2) : gestion des accréditations (groupe, niveau). */
class AccreditationController extends Controller
{
    public function index()
    {
        return response()->json(Accreditation::orderBy('label')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'groupe' => ['nullable', 'string', 'max:255'],
            'niveau' => ['nullable', 'integer', 'between:1,4'],
        ]);

        return response()->json(Accreditation::create($data), 201);
    }

    public function show(Accreditation $accreditation)
    {
        return response()->json($accreditation);
    }

    public function update(Request $request, Accreditation $accreditation)
    {
        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'groupe' => ['nullable', 'string', 'max:255'],
            'niveau' => ['nullable', 'integer', 'between:1,4'],
        ]);

        $accreditation->update($data);

        return response()->json($accreditation);
    }

    public function destroy(Accreditation $accreditation)
    {
        $accreditation->delete();

        return response()->json(status: 204);
    }
}
