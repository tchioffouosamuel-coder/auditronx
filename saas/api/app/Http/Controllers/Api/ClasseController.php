<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Specialite;
use Illuminate\Http\Request;

class ClasseController extends Controller
{
    public function index()
    {
        return response()->json(
            Classe::with('specialiteApc:id,code,nom')->withCount('maquette')->orderBy('nom')->paginate(50)
        );
    }

    public function store(Request $request)
    {
        $data = $this->valide($request);

        return response()->json(Classe::create($data)->load('specialiteApc:id,code,nom'), 201);
    }

    public function show(Classe $classe)
    {
        return response()->json($classe->load('specialiteApc:id,code,nom'));
    }

    public function update(Request $request, Classe $classe)
    {
        $classe->update($this->valide($request, modification: true));

        return response()->json($classe->load('specialiteApc:id,code,nom'));
    }

    public function destroy(Classe $classe)
    {
        $classe->delete();

        return response()->json(status: 204);
    }

    /**
     * Règles communes à la création et à la modification.
     *
     * La colonne texte `specialite` est tenue en miroir du code de la
     * spécialité rattachée : elle reste lue par des écrans et des exports
     * antérieurs au référentiel APC, et les laisser diverger produirait deux
     * spécialités différentes pour une même classe selon l'endroit où on la
     * regarde. Une classe sans rattachement garde le texte saisi à la main.
     *
     * @return array<string, mixed>
     */
    private function valide(Request $request, bool $modification = false): array
    {
        $presence = $modification ? 'sometimes' : 'required';
        $classeId = $request->route('classe')?->id;

        $data = $request->validate([
            'nom' => [$presence, 'string', 'max:255'],
            'code' => [$presence, 'string', 'max:50', 'unique:classes,code'.($classeId ? ','.$classeId : '')],
            'niveau' => ['nullable', 'string', 'max:50'],
            'specialite' => ['nullable', 'string', 'max:255'],
            'specialite_id' => ['nullable', 'integer', 'exists:specialites,id'],
            'effectif' => ['nullable', 'integer', 'min:0'],
        ]);

        if (array_key_exists('specialite_id', $data) && $data['specialite_id'] !== null) {
            $data['specialite'] = Specialite::whereKey($data['specialite_id'])->value('code') ?? ($data['specialite'] ?? null);
        }

        return $data;
    }
}
