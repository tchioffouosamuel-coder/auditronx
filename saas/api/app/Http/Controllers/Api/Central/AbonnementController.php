<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\Abonnement;
use App\Models\Central\Etablissement;
use App\Models\Central\Plan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Abonnements des établissements.
 *
 * Souscrire n'active rien techniquement et suspendre ne supprime rien : le
 * statut de l'établissement (`Etablissement::statut`) reste la seule chose que
 * le middleware d'identification regarde. Cette séparation évite qu'un
 * décalage de facturation coupe l'accès d'un lycée en pleine journée de cours.
 */
class AbonnementController extends Controller
{
    public function index(Request $request)
    {
        $abonnements = Abonnement::query()
            ->with(['etablissement:id,code,nom', 'plan:id,code,nom,prix,devise'])
            ->when($request->query('etablissement'), function ($q, $code) {
                $q->whereHas('etablissement', fn ($q) => $q->where('code', Etablissement::normaliseCode($code)));
            })
            ->when($request->query('statut'), fn ($q, $v) => $q->where('statut', $v))
            ->when($request->boolean('echeance_proche'), function ($q) {
                $q->where('statut', Abonnement::STATUT_ACTIF)
                    ->whereNotNull('fin_le')
                    ->whereBetween('fin_le', [now()->toDateString(), now()->addDays(30)->toDateString()]);
            })
            ->orderByDesc('debut_le')
            ->get();

        return response()->json(['data' => $abonnements]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'etablissement_id' => ['required', 'exists:central.etablissements,id'],
            'plan_id' => ['required', 'exists:central.plans,id'],
            'debut_le' => ['required', 'date'],
            'fin_le' => ['nullable', 'date', 'after:debut_le'],
            'montant' => ['nullable', 'numeric', 'min:0'],
            'periodicite' => ['sometimes', 'in:mensuel,trimestriel,annuel'],
            'notes' => ['nullable', 'string'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);

        // Un établissement n'a qu'un abonnement courant : souscrire à un
        // nouveau plan clôt le précédent au lieu d'empiler deux abonnements
        // actifs dont on ne saurait plus lequel fait foi.
        Abonnement::where('etablissement_id', $data['etablissement_id'])
            ->where('statut', Abonnement::STATUT_ACTIF)
            ->update(['statut' => Abonnement::STATUT_RESILIE, 'fin_le' => now()->toDateString()]);

        $abonnement = Abonnement::create([
            ...$data,
            'statut' => Abonnement::STATUT_ACTIF,
            'montant' => $data['montant'] ?? $plan->prix,
            'devise' => $plan->devise,
            'periodicite' => $data['periodicite'] ?? $plan->periodicite,
        ]);

        return response()->json(['data' => $abonnement->load('plan')], 201);
    }

    public function update(Request $request, Abonnement $abonnement)
    {
        $data = $request->validate([
            'fin_le' => ['nullable', 'date'],
            'statut' => ['sometimes', Rule::in(Abonnement::STATUTS)],
            'montant' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $abonnement->update($data);

        return response()->json(['data' => $abonnement->fresh(['plan', 'etablissement'])]);
    }

    /** Renouvellement : prolonge l'échéance d'une période du plan. */
    public function renouvelle(Abonnement $abonnement)
    {
        $depart = $abonnement->fin_le && $abonnement->fin_le->isFuture()
            ? $abonnement->fin_le->copy()
            : now();

        $fin = match ($abonnement->periodicite) {
            'annuel' => $depart->addYear(),
            'trimestriel' => $depart->addMonths(3),
            default => $depart->addMonth(),
        };

        $abonnement->update([
            'fin_le' => $fin->toDateString(),
            'statut' => Abonnement::STATUT_ACTIF,
        ]);

        return response()->json(['data' => $abonnement->fresh('plan')]);
    }
}
