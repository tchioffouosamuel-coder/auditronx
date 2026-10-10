<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\Plan;
use Illuminate\Http\Request;

/** Catalogue des offres, administré depuis le portail éditeur. */
class PlanController extends Controller
{
    public function index(Request $request)
    {
        $plans = Plan::query()
            ->when($request->has('actif'), fn ($q) => $q->where('actif', $request->boolean('actif')))
            ->withCount('abonnements')
            ->orderBy('prix')
            ->get();

        return response()->json(['data' => $plans]);
    }

    public function store(Request $request)
    {
        $plan = Plan::create($this->valide($request));

        return response()->json(['data' => $plan], 201);
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->valide($request, $plan));

        return response()->json(['data' => $plan->fresh()]);
    }

    public function destroy(Plan $plan)
    {
        // Un plan souscrit n'est pas supprimable : les abonnements et factures
        // déjà émis y font référence. On le désactive, il disparaît des choix
        // sans réécrire l'historique.
        if ($plan->abonnements()->exists()) {
            $plan->update(['actif' => false]);

            return response()->json([
                'message' => 'Plan désactivé (des abonnements y font référence).',
                'data' => $plan->fresh(),
            ]);
        }

        $plan->delete();

        return response()->json(['message' => 'Plan supprimé.']);
    }

    private function valide(Request $request, ?Plan $plan = null): array
    {
        $unicite = 'unique:central.plans,code' . ($plan ? ',' . $plan->id : '');

        return $request->validate([
            'code' => [$plan ? 'sometimes' : 'required', 'string', 'max:32', $unicite],
            'nom' => [$plan ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'prix' => ['sometimes', 'numeric', 'min:0'],
            'devise' => ['sometimes', 'string', 'max:8'],
            'periodicite' => ['sometimes', 'in:mensuel,trimestriel,annuel'],
            'max_personnel' => ['nullable', 'integer', 'min:1'],
            'max_bornes' => ['nullable', 'integer', 'min:1'],
            'fonctionnalites' => ['nullable', 'array'],
            'fonctionnalites.*' => ['string', 'max:64'],
            'actif' => ['sometimes', 'boolean'],
        ]);
    }
}
