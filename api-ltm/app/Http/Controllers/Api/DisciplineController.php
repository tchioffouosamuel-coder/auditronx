<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessiblesDisciplines;
use App\Models\Discipline;
use App\Models\User;
use Illuminate\Http\Request;

class DisciplineController extends Controller
{
    use AccessiblesDisciplines;

    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 500);

        return response()->json(
            $this->disciplinesAccessibles($this->auteur($request))
                ->orderBy('nom')
                ->paginate($perPage),
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:disciplines,code'],
            'coefficient' => ['nullable', 'integer', 'min:1', 'max:20'],
            'departement' => ['nullable', 'string', 'max:255'],
        ]);

        $this->assertPeutModifierDiscipline($this->auteurModification($request), $data['departement'] ?? null);

        return response()->json(Discipline::create($data), 201);
    }

    public function show(Request $request, Discipline $discipline)
    {
        abort_unless($this->peutUtiliserDiscipline($this->auteur($request), $discipline), 403);

        return response()->json($discipline);
    }

    public function update(Request $request, Discipline $discipline)
    {
        $auteur = $this->auteurModification($request);

        // Les deux départements sont contrôlés : celui d'où part la discipline
        // et celui où elle atterrit. Ne vérifier que le second laisserait un
        // censeur s'approprier la matière d'une autre section ; ne vérifier
        // que le premier le laisserait s'en défaire chez le voisin.
        $this->assertPeutModifierDiscipline($auteur, $discipline->departement);

        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', 'unique:disciplines,code,' . $discipline->id],
            'coefficient' => ['nullable', 'integer', 'min:1', 'max:20'],
            'departement' => ['nullable', 'string', 'max:255'],
        ]);

        if (array_key_exists('departement', $data)) {
            $this->assertPeutModifierDiscipline($auteur, $data['departement']);
        }

        $discipline->update($data);

        return response()->json($discipline);
    }

    public function destroy(Request $request, Discipline $discipline)
    {
        $this->assertPeutModifierDiscipline($this->auteurModification($request), $discipline->departement);

        $discipline->delete();

        return response()->json(status: 204);
    }

    /**
     * L'app mobile enseignant consulte aussi les disciplines (cahier de texte,
     * progression) avec un token `Enseignant`, qui ne porte pas
     * d'accréditation : on la traite comme un accès en lecture non restreint,
     * les écritures étant de toute façon réservées au backoffice.
     */
    private function auteur(Request $request): User
    {
        $auteur = $request->user();

        return $auteur instanceof User ? $auteur : new User();
    }

    /**
     * Auteur d'une écriture. Réservée aux comptes backoffice : un token
     * `Enseignant` de l'app mobile ne porte aucune accréditation, donc aucun
     * périmètre, et passerait tous les contrôles de département.
     */
    private function auteurModification(Request $request): User
    {
        $auteur = $request->user();

        abort_unless($auteur instanceof User, 403, 'Réservé aux administrateurs.');

        return $auteur;
    }
}
