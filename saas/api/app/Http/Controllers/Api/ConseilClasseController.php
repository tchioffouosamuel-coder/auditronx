<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\LitPeriodeConseil;
use App\Http\Controllers\Controller;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\User;
use App\Services\CalculBulletin;
use App\Services\ConseilClasse;
use Illuminate\Http\Request;

/**
 * Calcul des bulletins et conseil de classe (§bulletins APC, lot 5).
 *
 * Un conseil = une classe, une année, une période (`1`, `2`, `3` ou
 * `annuel`). Toutes les actions portent sur la classe entière, sauf la
 * décision et l'observation, qui portent sur un bulletin.
 */
class ConseilClasseController extends Controller
{
    use LitPeriodeConseil;

    /** GET /api/conseil?classe_id=&periode=&annee= */
    public function index(Request $request)
    {
        [$classe, $annee, $periode] = $this->contexte($request);

        return response()->json(ConseilClasse::etat($classe, $annee, $periode));
    }

    /** POST /api/conseil/generer — calcule (ou recalcule) les brouillons de la classe. */
    public function generer(Request $request)
    {
        [$classe, $annee, $periode] = $this->contexte($request);

        CalculBulletin::generer($classe, $annee, $periode, $this->auteur($request));

        return response()->json(ConseilClasse::etat($classe, $annee, $periode));
    }

    /** POST /api/conseil/valider — fige la classe et verrouille ses notes. */
    public function valider(Request $request)
    {
        [$classe, $annee, $periode] = $this->contexte($request);

        ConseilClasse::valider($classe, $annee, $periode, $this->auteur($request));

        return response()->json(ConseilClasse::etat($classe, $annee, $periode));
    }

    /**
     * POST /api/conseil/rouvrir — le motif est obligatoire : rouvrir un
     * bulletin signé est un geste dont on doit pouvoir rendre compte.
     */
    public function rouvrir(Request $request)
    {
        [$classe, $annee, $periode] = $this->contexte($request);
        $motif = $request->validate(['motif' => ['required', 'string', 'min:3', 'max:255']])['motif'];

        ConseilClasse::rouvrir($classe, $annee, $periode, trim($motif), $this->auteur($request));

        return response()->json(ConseilClasse::etat($classe, $annee, $periode));
    }

    /** GET /api/bulletins/{bulletin} — le bulletin complet, lignes comprises. */
    public function show(Bulletin $bulletin)
    {
        $bulletin->load(['lignes', 'inscription.eleve', 'validePar:id,name', 'decisionConseilPar:id,name']);

        return response()->json([
            ...ConseilClasse::resume($bulletin),
            'periode' => $bulletin->periode,
            'type' => $bulletin->type,
            'trimestre' => $bulletin->trimestre,
            'annee_scolaire' => $bulletin->annee_scolaire,
            'classe' => ['id' => $bulletin->classe_id, 'code' => $bulletin->classe_code, 'nom' => $bulletin->classe_nom],
            'eleve' => $bulletin->inscription?->eleve?->only(['id', 'matricule', 'nom', 'prenoms', 'nom_complet', 'sexe', 'date_naissance', 'lieu_naissance']),
            'statut_inscription' => $bulletin->statut_inscription,
            'effectif' => $bulletin->effectif,
            'effectif_classe' => $bulletin->effectif_classe,
            'moyenne_classe' => $bulletin->moyenne_classe,
            'moyenne_max' => $bulletin->moyenne_max,
            'moyenne_min' => $bulletin->moyenne_min,
            'genere_at' => $bulletin->genere_at?->toIso8601String(),
            'valide_at' => $bulletin->valide_at?->toIso8601String(),
            'valide_par' => $bulletin->validePar?->name,
            'decision_conseil_par' => $bulletin->decisionConseilPar?->name,
            'remplace_at' => $bulletin->remplace_at?->toIso8601String(),
            'motif_reouverture' => $bulletin->motif_reouverture,
            'lignes' => $bulletin->lignes,
        ]);
    }

    /** PUT /api/bulletins/{bulletin} — décision et observation du conseil. */
    public function update(Request $request, Bulletin $bulletin)
    {
        $data = $request->validate([
            'decision' => ['sometimes', 'nullable', 'string', 'max:30'],
            'observation' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $bulletin = ConseilClasse::modifier($bulletin, $data, $this->auteur($request));

        return response()->json(ConseilClasse::resume($bulletin));
    }

    /**
     * Classe, année et période de la requête.
     *
     * @return array{0: Classe, 1: string, 2: string}
     */
    private function contexte(Request $request): array
    {
        $request->validate(['classe_id' => ['required', 'integer', 'exists:classes,id']]);
        [$annee, $periode] = $this->periodeEtAnnee($request);

        return [Classe::findOrFail($request->integer('classe_id')), $annee, $periode];
    }

    private function auteur(Request $request): ?int
    {
        $utilisateur = $request->user();

        return $utilisateur instanceof User ? $utilisateur->id : null;
    }
}
