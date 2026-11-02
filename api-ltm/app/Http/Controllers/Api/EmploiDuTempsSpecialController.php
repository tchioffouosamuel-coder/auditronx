<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\EmploiDuTempsSpecial;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Emplois du temps spéciaux (§4.2 — Configuration). Pour un personnel dont
 * l'activité à l'école est générale — elle ne se découpe pas en cours — la
 * présence attendue se décrit par un jour, une heure de début et une heure de
 * fin. Dès qu'une personne en possède un, c'est lui qui sert de base à son
 * assiduité (voir `HoraireAttendu::plage`), et sa fiche ne porte plus aucune
 * période à rattraper.
 */
class EmploiDuTempsSpecialController extends Controller
{
    use AccessibleEnseignants;

    public function index(Request $request)
    {
        $query = EmploiDuTempsSpecial::with('enseignant')
            ->when($request->query('enseignant_id'), fn ($q, $v) => $q->where('enseignant_id', $v));

        $auteur = $request->user();

        if ($auteur instanceof User) {
            $query->whereIn('enseignant_id', $this->enseignantsAccessibles($auteur)->select('id'));
        }

        $perPage = min(max((int) $request->query('per_page', 100), 1), 500);

        return response()->json(
            $query->orderBy('enseignant_id')->orderBy('jour')->paginate($perPage),
        );
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->assertPerimetre($request, $data['enseignant_id']);
        $this->assertJourLibre($data);

        return response()->json(EmploiDuTempsSpecial::create($data)->load('enseignant'), 201);
    }

    public function show(EmploiDuTempsSpecial $emploiSpecial)
    {
        return response()->json($emploiSpecial->load('enseignant'));
    }

    public function update(Request $request, EmploiDuTempsSpecial $emploiSpecial)
    {
        $data = $this->validated($request, $emploiSpecial->id);
        $this->assertPerimetre($request, $data['enseignant_id'] ?? $emploiSpecial->enseignant_id);
        $this->assertJourLibre($data + [
            'enseignant_id' => $emploiSpecial->enseignant_id,
            'jour' => $emploiSpecial->jour,
        ], $emploiSpecial->id);

        $emploiSpecial->update($data);

        return response()->json($emploiSpecial->load('enseignant'));
    }

    public function destroy(Request $request, EmploiDuTempsSpecial $emploiSpecial)
    {
        $this->assertPerimetre($request, $emploiSpecial->enseignant_id);

        $emploiSpecial->delete();

        return response()->json(status: 204);
    }

    /**
     * Un emploi du temps spécial décide de l'assiduité opposable à une
     * personne : il ne s'attribue que dans le périmètre de l'accréditation,
     * sans quoi un censeur pourrait réécrire l'horaire attendu d'un collègue
     * d'une autre section.
     */
    private function assertPerimetre(Request $request, int|string|null $enseignantId): void
    {
        $auteur = $request->user();

        if (! $auteur instanceof User) {
            abort(403, 'Réservé aux administrateurs.');
        }

        $enseignant = Enseignant::find($enseignantId);

        abort_unless(
            $enseignant && $this->peutAccederA($auteur, $enseignant),
            403,
            'Votre accréditation ne couvre pas ce membre du personnel.',
        );
    }

    /** Une seule plage par jour : la base l'impose, on le dit en clair. */
    private function assertJourLibre(array $data, ?int $ignoreId = null): void
    {
        if (! isset($data['enseignant_id'], $data['jour'])) {
            return;
        }

        $existe = EmploiDuTempsSpecial::where('enseignant_id', $data['enseignant_id'])
            ->where('jour', $data['jour'])
            ->when($ignoreId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'jour' => ['Cette personne a déjà une plage spéciale ce jour-là.'],
            ]);
        }
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $rule = $ignoreId ? 'sometimes' : 'required';

        return $request->validate([
            'enseignant_id' => [$rule, 'exists:enseignants,id'],
            'jour' => [$rule, 'integer', 'between:1,7'],
            'heure_debut' => [$rule, 'date_format:H:i'],
            'heure_fin' => [$rule, 'date_format:H:i', 'after:heure_debut'],
        ]);
    }
}
