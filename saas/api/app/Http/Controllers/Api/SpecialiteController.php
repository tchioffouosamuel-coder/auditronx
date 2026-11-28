<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Specialite;
use Illuminate\Http\Request;

/**
 * Spécialités de formation (§bulletins APC). Une spécialité naît le plus
 * souvent de l'import du référentiel — l'onglet du classeur porte son code —
 * et reçoit ici son libellé long et son département.
 */
class SpecialiteController extends Controller
{
    public function index(Request $request)
    {
        $specialites = Specialite::with('departement:id,code,nom')
            ->withCount('modules')
            ->when($request->filled('departement_id'), fn ($q) => $q->where('departement_id', $request->integer('departement_id')))
            ->when($request->boolean('actives'), fn ($q) => $q->where('actif', true))
            ->orderBy('ordre')
            ->orderBy('code')
            ->paginate(50);

        return response()->json($specialites);
    }

    public function store(Request $request)
    {
        return response()->json(Specialite::create($this->valide($request)), 201);
    }

    public function show(Specialite $specialite)
    {
        return response()->json(
            $specialite->load('departement:id,code,nom')->setAttribute('niveaux', $specialite->niveaux())
        );
    }

    public function update(Request $request, Specialite $specialite)
    {
        $specialite->update($this->valide($request, $specialite));

        return response()->json($specialite->load('departement:id,code,nom'));
    }

    /**
     * Supprimer une spécialité emporte son référentiel (`cascadeOnDelete` sur
     * `modules`) : c'est voulu, un module n'a pas d'existence hors de sa
     * spécialité. Désactiver (`actif = false`) est le geste à préférer quand
     * la formation s'arrête mais que ses bulletins doivent rester lisibles.
     */
    public function destroy(Specialite $specialite)
    {
        $specialite->delete();

        return response()->json(status: 204);
    }

    /** @return array<string, mixed> */
    private function valide(Request $request, ?Specialite $specialite = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:specialites,code'.($specialite ? ','.$specialite->id : '')],
            'nom' => ['required', 'string', 'max:255'],
            'departement_id' => ['nullable', 'integer', 'exists:departements,id'],
            'cycle' => ['nullable', 'string', 'max:50'],
            'actif' => ['nullable', 'boolean'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $data['ordre'] = $data['ordre'] ?? 0;
        $data['actif'] = $data['actif'] ?? true;

        return $data;
    }
}
