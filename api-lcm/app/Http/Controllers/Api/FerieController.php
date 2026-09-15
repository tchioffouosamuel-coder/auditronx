<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ferie;
use Illuminate\Http\Request;

class FerieController extends Controller
{
    public function index()
    {
        return response()->json(Ferie::orderBy('date')->paginate(50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'unique:feries,date'],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        return response()->json(Ferie::create($data), 201);
    }

    public function show(Ferie $ferie)
    {
        return response()->json($ferie);
    }

    public function update(Request $request, Ferie $ferie)
    {
        $data = $request->validate([
            'date' => ['sometimes', 'date', 'unique:feries,date,'.$ferie->id],
            'libelle' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $ferie->update($data);

        return response()->json($ferie);
    }

    public function destroy(Ferie $ferie)
    {
        $ferie->delete();

        return response()->json(status: 204);
    }
}
