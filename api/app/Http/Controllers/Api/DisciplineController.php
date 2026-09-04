<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discipline;
use Illuminate\Http\Request;

class DisciplineController extends Controller
{
    public function index()
    {
        return response()->json(Discipline::orderBy('nom')->paginate(50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:disciplines,code'],
            'coefficient' => ['nullable', 'integer', 'min:1', 'max:20'],
            'departement' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(Discipline::create($data), 201);
    }

    public function show(Discipline $discipline)
    {
        return response()->json($discipline);
    }

    public function update(Request $request, Discipline $discipline)
    {
        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', 'unique:disciplines,code,'.$discipline->id],
            'coefficient' => ['nullable', 'integer', 'min:1', 'max:20'],
            'departement' => ['nullable', 'string', 'max:255'],
        ]);

        $discipline->update($data);

        return response()->json($discipline);
    }

    public function destroy(Discipline $discipline)
    {
        $discipline->delete();

        return response()->json(status: 204);
    }
}
