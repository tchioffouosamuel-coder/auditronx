<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use Illuminate\Http\Request;

class ClasseController extends Controller
{
    public function index()
    {
        return response()->json(Classe::orderBy('nom')->paginate(50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:classes,code'],
            'niveau' => ['nullable', 'string', 'max:50'],
            'specialite' => ['nullable', 'string', 'max:255'],
            'effectif' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json(Classe::create($data), 201);
    }

    public function show(Classe $classe)
    {
        return response()->json($classe);
    }

    public function update(Request $request, Classe $classe)
    {
        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', 'unique:classes,code,'.$classe->id],
            'niveau' => ['nullable', 'string', 'max:50'],
            'specialite' => ['nullable', 'string', 'max:255'],
            'effectif' => ['nullable', 'integer', 'min:0'],
        ]);

        $classe->update($data);

        return response()->json($classe);
    }

    public function destroy(Classe $classe)
    {
        $classe->delete();

        return response()->json(status: 204);
    }
}
