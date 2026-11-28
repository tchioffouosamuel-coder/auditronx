<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Departement;
use Illuminate\Http\Request;

/**
 * Départements d'un établissement technique (§bulletins APC) : Génie
 * Électrique, Industrie de l'Habillement, Enseignement Général…
 */
class DepartementController extends Controller
{
    public function index()
    {
        return response()->json(
            Departement::withCount('specialites')->orderBy('ordre')->orderBy('nom')->paginate(50)
        );
    }

    public function store(Request $request)
    {
        return response()->json(Departement::create($this->valide($request)), 201);
    }

    public function show(Departement $departement)
    {
        return response()->json($departement->loadCount('specialites'));
    }

    public function update(Request $request, Departement $departement)
    {
        $departement->update($this->valide($request, $departement));

        return response()->json($departement);
    }

    /**
     * La suppression détache les spécialités rattachées (`nullOnDelete`)
     * plutôt que de les emporter : un département supprimé par erreur ne doit
     * pas faire disparaître des référentiels entiers.
     */
    public function destroy(Departement $departement)
    {
        $departement->delete();

        return response()->json(status: 204);
    }

    /** @return array<string, mixed> */
    private function valide(Request $request, ?Departement $departement = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:departements,code'.($departement ? ','.$departement->id : '')],
            'nom' => ['required', 'string', 'max:255'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        // La colonne n'est pas nullable : un champ laissé vide au formulaire
        // vaut « pas de rang choisi », donc 0, et non un NULL rejeté par MySQL.
        $data['ordre'] = $data['ordre'] ?? 0;

        return $data;
    }
}
