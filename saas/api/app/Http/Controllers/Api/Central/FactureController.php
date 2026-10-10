<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\Abonnement;
use App\Models\Central\Etablissement;
use App\Models\Central\Facture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Facturation des abonnements (émission, encaissement, impayés). */
class FactureController extends Controller
{
    public function index(Request $request)
    {
        $factures = Facture::query()
            ->with(['etablissement:id,code,nom', 'abonnement:id,plan_id'])
            ->when($request->query('etablissement'), function ($q, $code) {
                $q->whereHas('etablissement', fn ($q) => $q->where('code', Etablissement::normaliseCode($code)));
            })
            ->when($request->query('statut'), fn ($q, $v) => $q->where('statut', $v))
            ->when($request->boolean('impayees'), function ($q) {
                $q->where('statut', Facture::STATUT_EMISE)
                    ->whereNotNull('echeance_le')
                    ->whereDate('echeance_le', '<', now()->toDateString());
            })
            ->orderByDesc('emise_le')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $factures]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'etablissement_id' => ['required', 'exists:central.etablissements,id'],
            'abonnement_id' => ['nullable', 'exists:central.abonnements,id'],
            'montant' => ['required', 'numeric', 'min:0'],
            'devise' => ['sometimes', 'string', 'max:8'],
            'emise_le' => ['sometimes', 'date'],
            'echeance_le' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $abonnement = isset($data['abonnement_id'])
            ? Abonnement::find($data['abonnement_id'])
            : null;

        $facture = Facture::create([
            ...$data,
            'numero' => $this->prochainNumero(),
            'devise' => $data['devise'] ?? $abonnement?->devise ?? 'XAF',
            'emise_le' => $data['emise_le'] ?? now()->toDateString(),
            'statut' => Facture::STATUT_EMISE,
        ]);

        return response()->json(['data' => $facture->load('etablissement:id,code,nom')], 201);
    }

    public function marquePayee(Request $request, Facture $facture)
    {
        $data = $request->validate([
            'payee_le' => ['sometimes', 'date'],
            'moyen_paiement' => ['nullable', 'in:mobile_money,virement,especes,cheque'],
            'reference_paiement' => ['nullable', 'string', 'max:255'],
        ]);

        $facture->update([
            ...$data,
            'payee_le' => $data['payee_le'] ?? now()->toDateString(),
            'statut' => Facture::STATUT_PAYEE,
        ]);

        return response()->json(['data' => $facture->fresh()]);
    }

    public function annule(Facture $facture)
    {
        // Une facture payée ne s'annule pas d'un clic : il faut un avoir, qui
        // est une autre facture. Refuser ici évite de masquer un encaissement.
        if ($facture->statut === Facture::STATUT_PAYEE) {
            return response()->json([
                'message' => 'Une facture payée ne peut pas être annulée : émettre un avoir.',
            ], 422);
        }

        $facture->update(['statut' => Facture::STATUT_ANNULEE]);

        return response()->json(['data' => $facture->fresh()]);
    }

    /**
     * Numérotation séquentielle par année : `2026-0001`.
     *
     * Le verrou de table évite deux factures du même numéro si deux membres du
     * support émettent en même temps.
     */
    private function prochainNumero(): string
    {
        $annee = now()->year;

        return DB::connection('central')->transaction(function () use ($annee) {
            $dernier = Facture::where('numero', 'like', $annee . '-%')
                ->lockForUpdate()
                ->orderByDesc('numero')
                ->value('numero');

            $rang = $dernier ? ((int) substr($dernier, -4)) + 1 : 1;

            return $annee . '-' . str_pad((string) $rang, 4, '0', STR_PAD_LEFT);
        });
    }
}
