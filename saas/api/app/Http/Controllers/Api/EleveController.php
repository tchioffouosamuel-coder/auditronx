<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Services\AnneeScolaire;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Élèves (§bulletins APC) : l'identité. La classe, l'année et le statut
 * passent par les inscriptions (InscriptionController).
 */
class EleveController extends Controller
{
    public function index(Request $request)
    {
        $annee = AnneeScolaire::normalise($request->string('annee')->toString()) ?? AnneeScolaire::courante();

        $eleves = Eleve::query()
            ->with(['inscriptions' => fn ($q) => $q->where('annee_scolaire', $annee)->with('classe:id,code,nom')])
            ->when($request->filled('classe_id'), fn ($q) => $q->whereHas(
                'inscriptions',
                fn ($i) => $i->where('classe_id', $request->integer('classe_id'))->where('annee_scolaire', $annee)
            ))
            ->when($request->filled('q'), function ($q) use ($request) {
                $recherche = '%'.$request->string('q')->toString().'%';
                $q->where(fn ($w) => $w->where('nom', 'like', $recherche)
                    ->orWhere('prenoms', 'like', $recherche)
                    ->orWhere('matricule', 'like', $recherche));
            })
            ->orderBy('nom')
            ->orderBy('prenoms')
            ->paginate(50);

        return response()->json($eleves);
    }

    /**
     * GET /api/classes/{classe}/eleves?annee=
     *
     * La liste de classe telle que l'attendent la saisie des notes et
     * l'en-tête du bulletin.
     *
     * L'effectif est **compté sur les inscriptions actives**, et non lu dans
     * `classes.effectif` : cette dernière est une valeur saisie à la main, qui
     * se périme dès le premier départ. Un bulletin qui annonce « Effectif :
     * 28 » quand la classe en compte 26 fausse aussi le rang.
     */
    public function parClasse(Request $request, Classe $classe)
    {
        $annee = AnneeScolaire::normalise($request->string('annee')->toString()) ?? AnneeScolaire::courante();

        $inscriptions = Inscription::activesDe($classe->id, $annee)
            ->with('eleve')
            ->get()
            // Clé composée plutôt qu'un tri à plusieurs critères : `sortBy`
            // ne les enchaîne pas de façon fiable (cf. MaquetteClasse).
            ->sortBy(fn (Inscription $inscription) => mb_strtolower(
                trim(($inscription->eleve?->nom ?? '').' '.($inscription->eleve?->prenoms ?? '')),
                'UTF-8',
            ))
            ->values();

        return response()->json([
            'classe' => $classe->only(['id', 'code', 'nom', 'niveau']),
            'annee_scolaire' => $annee,
            'effectif' => $inscriptions->count(),
            'eleves' => $inscriptions->map(fn (Inscription $inscription) => [
                'inscription_id' => $inscription->id,
                'eleve_id' => $inscription->eleve_id,
                'matricule' => $inscription->eleve?->matricule,
                'nom_complet' => $inscription->eleve?->nom_complet,
                'sexe' => $inscription->eleve?->sexe,
                'date_naissance' => $inscription->eleve?->date_naissance?->toDateString(),
                'lieu_naissance' => $inscription->eleve?->lieu_naissance,
                'statut' => $inscription->statut,
            ])->all(),
        ]);
    }

    /** Les années scolaires pour lesquelles il existe des inscriptions — filtres des écrans. */
    public function annees()
    {
        return response()->json(
            AnneeScolaire::trier(Inscription::query()->distinct()->pluck('annee_scolaire')->all())
                ?: [AnneeScolaire::courante()]
        );
    }

    public function store(Request $request)
    {
        return response()->json(Eleve::create($this->valide($request)), 201);
    }

    public function show(Eleve $eleve)
    {
        return response()->json($eleve->load(['inscriptions.classe:id,code,nom']));
    }

    public function update(Request $request, Eleve $eleve)
    {
        $eleve->update($this->valide($request, $eleve));

        return response()->json($eleve);
    }

    public function destroy(Eleve $eleve)
    {
        $eleve->delete();

        return response()->json(status: 204);
    }

    /** @return array<string, mixed> */
    private function valide(Request $request, ?Eleve $eleve = null): array
    {
        return $request->validate([
            'matricule' => ['required', 'string', 'max:50', Rule::unique('eleves', 'matricule')->ignore($eleve?->id)],
            'matricule_national' => ['nullable', 'string', 'max:50'],
            'nom' => ['required', 'string', 'max:255'],
            'prenoms' => ['nullable', 'string', 'max:255'],
            'sexe' => ['nullable', Rule::in(Eleve::SEXES)],
            'date_naissance' => ['nullable', 'date'],
            'lieu_naissance' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:30'],
        ]);
    }
}
