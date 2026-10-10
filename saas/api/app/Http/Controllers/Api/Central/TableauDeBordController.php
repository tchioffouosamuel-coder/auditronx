<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\Abonnement;
use App\Models\Central\Etablissement;
use App\Models\Central\Facture;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Tableau de bord éditeur : état du parc d'abonnés.
 *
 * C'est le prix de l'isolation par base : un chiffre global demande une
 * requête par établissement. D'où la mise en cache courte — le portail
 * éditeur est consulté plusieurs fois par heure, et aucun de ces chiffres n'a
 * besoin d'être à la seconde. Les chiffres d'un établissement injoignable sont
 * renvoyés à null plutôt que de faire échouer tout le tableau.
 */
class TableauDeBordController extends Controller
{
    private const DUREE_CACHE = 300;

    public function __construct(private readonly TenantManager $tenants) {}

    public function index()
    {
        $parStatut = Etablissement::query()
            ->selectRaw('statut, count(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        return response()->json([
            'etablissements' => [
                'total' => (int) $parStatut->sum(),
                'par_statut' => $parStatut,
            ],
            'abonnements' => [
                'actifs' => Abonnement::where('statut', Abonnement::STATUT_ACTIF)->count(),
                'echeance_30j' => Abonnement::where('statut', Abonnement::STATUT_ACTIF)
                    ->whereNotNull('fin_le')
                    ->whereBetween('fin_le', [now()->toDateString(), now()->addDays(30)->toDateString()])
                    ->count(),
            ],
            'facturation' => [
                'impayees' => Facture::where('statut', Facture::STATUT_EMISE)
                    ->whereNotNull('echeance_le')
                    ->whereDate('echeance_le', '<', now()->toDateString())
                    ->count(),
                'encaisse_mois' => (float) Facture::where('statut', Facture::STATUT_PAYEE)
                    ->whereYear('payee_le', now()->year)
                    ->whereMonth('payee_le', now()->month)
                    ->sum('montant'),
            ],
            'usage' => $this->usageParEtablissement(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function usageParEtablissement(): array
    {
        return Cache::store(config('cache.default'))->remember(
            'plateforme:usage',
            self::DUREE_CACHE,
            fn () => Etablissement::query()
                ->whereNotNull('provisionne_le')
                ->orderBy('nom')
                ->get()
                ->map(fn (Etablissement $e) => [
                    'code' => $e->code,
                    'nom' => $e->nom,
                    'statut' => $e->statut,
                    ...$this->mesure($e),
                ])
                ->all()
        );
    }

    /** @return array<string, int|string|null> */
    private function mesure(Etablissement $etablissement): array
    {
        try {
            return $this->tenants->execute($etablissement, fn () => [
                'personnel' => Enseignant::count(),
                'presences_aujourdhui' => Presence::where('date', now()->toDateString())->count(),
                'derniere_presence' => Presence::max('date'),
                'injoignable' => false,
            ]);
        } catch (Throwable $e) {
            return [
                'personnel' => null,
                'presences_aujourdhui' => null,
                'derniere_presence' => null,
                'injoignable' => true,
            ];
        }
    }
}
