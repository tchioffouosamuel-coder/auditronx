<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\Abonnement;
use App\Models\Central\Etablissement;
use App\Models\Central\Facture;
use App\Models\Central\Plan;
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
                'encaisse_mois' => Facture::where('statut', Facture::STATUT_PAYEE)
                    ->whereYear('payee_le', now()->year)
                    ->whereMonth('payee_le', now()->month)
                    ->selectRaw('devise, sum(montant) as total')
                    ->groupBy('devise')
                    ->pluck('total', 'devise'),
            ],
            'offres' => [
                'actives' => Plan::where('actif', true)->count(),
            ],
            'usage' => $this->usageParEtablissement(),
            'historique' => $this->historiqueActivite(),
        ]);
    }

    /** Indicateurs mensuels pour suivre l'activité du parc sur six mois. */
    private function historiqueActivite(): array
    {
        return Cache::store(config('cache.default'))->remember(
            'plateforme:historique-activite',
            self::DUREE_CACHE,
            function (): array {
                $mois = collect(range(5, 0))->map(function (int $retard) {
                    $debut = now()->startOfMonth()->subMonthsNoOverflow($retard);
                    $fin = $debut->copy()->endOfMonth();

                    return [
                        'cle' => $debut->format('Y-m'),
                        'mois' => $debut->format('m/Y'),
                        'debut' => $debut->toDateString(),
                        'fin' => $fin->toDateString(),
                        'etablissements_ouverts' => Etablissement::whereBetween('created_at', [$debut, $fin])->count(),
                        'factures_payees' => Facture::where('statut', Facture::STATUT_PAYEE)
                            ->whereBetween('payee_le', [$debut->toDateString(), $fin->toDateString()])
                            ->count(),
                        'pointages' => 0,
                        'bases_injoignables' => 0,
                    ];
                })->keyBy('cle');

                $debut = $mois->first()['debut'];
                $fin = $mois->last()['fin'];
                $etablissements = Etablissement::query()
                    ->whereNotNull('provisionne_le')
                    ->whereIn('statut', [Etablissement::STATUT_ACTIF, Etablissement::STATUT_SUSPENDU])
                    ->get();

                foreach ($etablissements as $etablissement) {
                    try {
                        $comptages = $this->tenants->execute($etablissement, function () use ($debut, $fin) {
                            $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
                            $moisSql = $driver === 'mysql'
                                ? "DATE_FORMAT(date, '%Y-%m')"
                                : "strftime('%Y-%m', date)";

                            return Presence::query()
                                ->selectRaw("{$moisSql} as mois, count(*) as total")
                                ->whereBetween('date', [$debut, $fin])
                                ->groupBy('mois')
                                ->pluck('total', 'mois');
                        });

                        foreach ($comptages as $cle => $total) {
                            if ($mois->has($cle)) {
                                $mois[$cle]['pointages'] += (int) $total;
                            }
                        }
                    } catch (Throwable) {
                        foreach ($mois as $ligne) {
                            $ligne['bases_injoignables']++;
                            $mois[$ligne['cle']] = $ligne;
                        }
                    }
                }

                return $mois->values()->map(fn (array $ligne) => collect($ligne)->except(['cle', 'debut', 'fin'])->all())->all();
            }
        );
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
