<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\Etablissement;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Throwable;

/**
 * Consultation des présences de tous les abonnés, depuis le portail éditeur.
 *
 * Le support Auditron devait jusqu'ici demander une capture d'écran au client,
 * ou se faire inviter comme utilisateur de son établissement, pour répondre à
 * « est-ce que les pointages remontent chez vous ? ». Cette route répond pour
 * tout le parc d'un coup.
 *
 * Deux limites assumées, conséquences directes de l'isolation par base :
 *
 *  - une requête par établissement, donc pas de tri ni de pagination globaux.
 *    On agrège d'abord, on trie ensuite en mémoire — acceptable à l'échelle de
 *    quelques dizaines d'abonnés, à revoir au-delà (voir ARCHITECTURE.md,
 *    « Montée en charge ») ;
 *  - un établissement injoignable est signalé comme tel et n'interrompt pas
 *    les autres. Afficher 0 laisserait croire à un lycée sans activité, ce qui
 *    est l'erreur de diagnostic exactement inverse.
 *
 * Pas de cache ici, contrairement au tableau de bord : on consulte cette page
 * justement quand on doute d'une remontée en cours, et un chiffre vieux de
 * cinq minutes répondrait à côté de la question.
 */
class PresenceController extends Controller
{
    /** Garde-fou : au-delà, la réponse pèse trop pour un écran de consultation. */
    private const MAX_LIGNES_PAR_ETABLISSEMENT = 500;

    public function __construct(private readonly TenantManager $tenants) {}

    public function index(Request $request)
    {
        $filtres = $request->validate([
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'etablissement' => ['nullable', 'string'],
            'avec_lignes' => ['nullable', 'boolean'],
        ]);

        $debut = $filtres['date_debut'] ?? now()->toDateString();
        $fin = $filtres['date_fin'] ?? $debut;
        $avecLignes = (bool) ($filtres['avec_lignes'] ?? false);

        $etablissements = Etablissement::query()
            ->whereNotNull('provisionne_le')
            ->when(
                $filtres['etablissement'] ?? null,
                fn ($q, $code) => $q->where('code', Etablissement::normaliseCode($code)),
            )
            ->orderBy('nom')
            ->get();

        $resultats = $etablissements
            ->map(fn (Etablissement $e) => [
                'code' => $e->code,
                'nom' => $e->nom,
                'statut' => $e->statut,
                ...$this->presencesDe($e, $debut, $fin, $avecLignes),
            ])
            ->all();

        return response()->json([
            'periode' => ['debut' => $debut, 'fin' => $fin],
            'etablissements' => $resultats,
            'totaux' => $this->totaux($resultats),
        ]);
    }

    /**
     * Relevé d'un établissement sur la période.
     *
     * @return array<string, mixed>
     */
    private function presencesDe(
        Etablissement $etablissement,
        string $debut,
        string $fin,
        bool $avecLignes,
    ): array {
        try {
            return $this->tenants->execute($etablissement, function () use ($debut, $fin, $avecLignes) {
                $base = Presence::whereBetween('date', [$debut, $fin]);

                $releve = [
                    'effectif' => Enseignant::count(),
                    'pointages' => (clone $base)->count(),
                    'arrivees' => (clone $base)->whereNotNull('heure_arrivee')->count(),
                    'departs' => (clone $base)->whereNotNull('heure_depart')->count(),
                    // Une arrivée sans départ sur une journée écoulée : le
                    // signe le plus courant d'une borne qui a cessé de
                    // remonter en cours de journée.
                    'sans_depart' => (clone $base)
                        ->whereNotNull('heure_arrivee')
                        ->whereNull('heure_depart')
                        ->count(),
                    'dernier_pointage' => Presence::max('date'),
                    'injoignable' => false,
                ];

                if ($avecLignes) {
                    $releve['lignes'] = (clone $base)
                        ->with('enseignant:id,nom,matricule,section')
                        ->orderByDesc('date')
                        ->orderBy('enseignant_id')
                        ->limit(self::MAX_LIGNES_PAR_ETABLISSEMENT)
                        ->get()
                        ->map(fn (Presence $p) => [
                            'date' => $p->date?->toDateString(),
                            'nom' => $p->enseignant?->nom,
                            'matricule' => $p->enseignant?->matricule,
                            'section' => $p->enseignant?->section,
                            'heure_arrivee' => $p->heure_arrivee?->format('H:i'),
                            'heure_depart' => $p->heure_depart?->format('H:i'),
                            'source' => $p->source,
                        ])
                        ->all();

                    $releve['lignes_tronquees'] = $releve['pointages'] > self::MAX_LIGNES_PAR_ETABLISSEMENT;
                }

                return $releve;
            });
        } catch (Throwable $e) {
            return [
                'effectif' => null,
                'pointages' => null,
                'arrivees' => null,
                'departs' => null,
                'sans_depart' => null,
                'dernier_pointage' => null,
                'injoignable' => true,
                'erreur' => $e->getMessage(),
                ...($avecLignes ? ['lignes' => [], 'lignes_tronquees' => false] : []),
            ];
        }
    }

    /**
     * Totaux du parc. Les établissements injoignables sont comptés à part :
     * les additionner à zéro ferait passer une panne pour une journée calme.
     *
     * @param  array<int, array<string, mixed>>  $resultats
     * @return array<string, int>
     */
    private function totaux(array $resultats): array
    {
        $joignables = array_filter($resultats, fn ($r) => ! $r['injoignable']);

        return [
            'etablissements' => count($resultats),
            'injoignables' => count($resultats) - count($joignables),
            'effectif' => (int) array_sum(array_column($joignables, 'effectif')),
            'pointages' => (int) array_sum(array_column($joignables, 'pointages')),
            'arrivees' => (int) array_sum(array_column($joignables, 'arrivees')),
            'departs' => (int) array_sum(array_column($joignables, 'departs')),
            'sans_depart' => (int) array_sum(array_column($joignables, 'sans_depart')),
        ];
    }
}
