<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inscription;
use App\Services\AnneeScolaire;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Inscriptions : qui est dans quelle classe, pour quelle année, avec quel
 * statut.
 *
 * Séparées des élèves parce qu'elles changent chaque année alors que
 * l'identité ne change pas, et parce que ce sont elles — et non les élèves —
 * que les notes et les bulletins désignent.
 */
class InscriptionController extends Controller
{
    public function index(Request $request)
    {
        $inscriptions = Inscription::with(['eleve', 'classe:id,code,nom'])
            ->when($request->filled('classe_id'), fn ($q) => $q->where('classe_id', $request->integer('classe_id')))
            ->when($request->filled('annee'), fn ($q) => $q->where(
                'annee_scolaire',
                AnneeScolaire::normalise($request->string('annee')->toString())
            ))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')->toString()))
            ->when($request->boolean('actives'), fn ($q) => $q->where('actif', true))
            ->orderBy('annee_scolaire', 'desc')
            ->paginate(50);

        return response()->json($inscriptions);
    }

    public function store(Request $request)
    {
        $data = $this->valide($request);

        return response()->json(
            Inscription::create($data)->load(['eleve', 'classe:id,code,nom']),
            201
        );
    }

    public function show(Inscription $inscription)
    {
        return response()->json($inscription->load(['eleve', 'classe:id,code,nom']));
    }

    public function update(Request $request, Inscription $inscription)
    {
        $inscription->update($this->valide($request, $inscription));

        return response()->json($inscription->load(['eleve', 'classe:id,code,nom']));
    }

    public function destroy(Inscription $inscription)
    {
        $inscription->delete();

        return response()->json(status: 204);
    }

    /** @return array<string, mixed> */
    private function valide(Request $request, ?Inscription $inscription = null): array
    {
        $data = $request->validate([
            'eleve_id' => ['required', 'integer', 'exists:eleves,id'],
            'classe_id' => ['required', 'integer', 'exists:classes,id'],
            'annee_scolaire' => ['nullable', 'string', 'max:'.AnneeScolaire::LONGUEUR_MAX],
            'statut' => ['nullable', Rule::in(array_keys(Inscription::LIBELLES_STATUTS))],
            'actif' => ['nullable', 'boolean'],
        ]);

        $data['annee_scolaire'] = AnneeScolaire::normalise($data['annee_scolaire'] ?? null)
            ?? AnneeScolaire::courante();
        $data['statut'] = $data['statut'] ?? Inscription::STATUT_REGULIER;
        $data['actif'] = $data['actif'] ?? true;

        /*
         * Un élève n'a qu'une inscription par année : changer de classe en
         * cours d'année modifie l'inscription, il n'y en a pas deux. La règle
         * `unique` est posée ici plutôt que dans la validation déclarative
         * parce qu'elle porte sur un couple dont l'un des membres vient
         * d'être normalisé.
         */
        $doublon = Inscription::where('eleve_id', $data['eleve_id'])
            ->where('annee_scolaire', $data['annee_scolaire'])
            ->when($inscription !== null, fn ($q) => $q->whereKeyNot($inscription->id))
            ->exists();

        if ($doublon) {
            throw ValidationException::withMessages([
                'eleve_id' => "Cet élève a déjà une inscription pour l'année {$data['annee_scolaire']}.",
            ]);
        }

        return $data;
    }
}
