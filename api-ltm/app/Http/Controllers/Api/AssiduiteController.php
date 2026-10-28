<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Services\AssiduiteCalculator;
use App\Services\HoraireAttendu;
use App\Services\WordWriter;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Assiduité & rapports (§4.2) — statistiques par section, journal, personnel inactif. */
class AssiduiteController extends Controller
{
    use AccessibleEnseignants;

    /**
     * Les deux catégories de personnel. Enseignants et administratifs ne
     * partagent jamais un même journal : leurs horaires attendus n'ont pas la
     * même source (emploi du temps contre horaire fixe) et les documents sont
     * signés par des responsables différents.
     */
    private const LIBELLES_CATEGORIE = [
        'enseignant' => 'Personnel enseignant',
        'administration' => 'Personnel administratif',
    ];

    /** GET /api/assiduite/stats?debut=&fin=&section= */
    public function stats(Request $request, AssiduiteCalculator $assiduite)
    {
        $debut = Carbon::parse($request->query('debut', now()->startOfMonth()));
        $fin = Carbon::parse($request->query('fin', now()->endOfMonth()));
        $enseignants = $this->enseignantsAccessibles($request->user())
            ->when($request->query('section'), fn($q, $v) => $q->whereRaw('LOWER(section) = LOWER(?)', [$v]))
            ->with([
                'emploiDuTemps',
                'presences' => fn ($query) => $query->whereBetween('date', [$debut->toDateString(), $fin->toDateString()]),
                'signalements' => fn ($query) => $query->whereDate('date', '<=', $fin->toDateString()),
            ])
            ->get();

        $lignes = $enseignants->map(function (Enseignant $enseignant) use ($debut, $fin, $assiduite) {
            return [
                'enseignant_id' => $enseignant->id,
                'nom' => $enseignant->nom,
                'section' => $enseignant->section,
                ...$assiduite->bilan($enseignant, $debut, $fin),
            ];
        });

        return response()->json($lignes->sortByDesc('taux_assiduite')->values());
    }

    /** GET /api/assiduite/journal?date=&section=&categorie= — journal quotidien. */
    public function journal(Request $request, HoraireAttendu $horaires)
    {
        $date = Carbon::parse($request->query('date', now()));

        return response()->json($this->presencesDuJour($request, $date, $horaires));
    }

    /** GET /api/assiduite/journal/pdf?date=&section=&categorie= — export PDF quotidien. */
    public function journalPdf(Request $request, HoraireAttendu $horaires)
    {
        $date = Carbon::parse($request->query('date', now()));
        $pdf = Pdf::loadView('pdf.journal-presences', [
            'date' => $date,
            'presences' => $this->presencesDuJour($request, $date, $horaires),
        ]);

        return $pdf->download("journal-presences-{$date->toDateString()}.pdf");
    }

    /**
     * GET /api/assiduite/journal-hebdomadaire?semaine=&section=&categorie=
     *
     * Journal de la semaine sous forme de grille : une ligne par membre du
     * personnel, une colonne par jour. Le journal quotidien ne liste que les
     * présences enregistrées, donc un absent n'y apparaît pas du tout ; la
     * vue hebdomadaire part du personnel attendu, ce qui rend les absences
     * visibles en tant que telles.
     */
    public function journalHebdomadaire(Request $request, HoraireAttendu $horaires)
    {
        $semaine = $this->semaine($request, $horaires);

        // Les dates sont renvoyées en chaînes `Y-m-d` et non en objets Carbon :
        // sérialisés, ceux-ci partent en UTC et le lundi arrive côté client
        // daté du dimanche 23h, décalant toute la grille d'une colonne.
        return response()->json([
            'debut' => $semaine['debut']->toDateString(),
            'fin' => $semaine['fin']->toDateString(),
            'jours' => $semaine['jours']->map(fn (Carbon $jour) => [
                'date' => $jour->toDateString(),
                'libelle' => ucfirst($jour->locale('fr')->translatedFormat('D')),
                'jour_mois' => $jour->format('d/m'),
                'ferie' => $horaires->ferie($jour),
            ]),
            'lignes' => $semaine['lignes'],
        ]);
    }

    /** GET /api/assiduite/journal-hebdomadaire/pdf?semaine=&section=&categorie= */
    public function journalHebdomadairePdf(Request $request, HoraireAttendu $horaires)
    {
        $semaine = $this->semaine($request, $horaires);

        $pdf = Pdf::loadView('pdf.journal-hebdomadaire', $semaine)
            ->setPaper('a4', 'landscape');

        return $pdf->download("journal-hebdomadaire-{$semaine['debut']->toDateString()}.pdf");
    }

    /**
     * GET /api/assiduite/journal/word?date=&categorie=&section=
     *
     * Même journal quotidien, au format Word : l'administration reprend ces
     * documents (mentions manuscrites, visa, en-tête d'établissement) avant
     * diffusion, ce qu'un PDF ne permet pas.
     */
    public function journalWord(Request $request, HoraireAttendu $horaires)
    {
        $date = Carbon::parse($request->query('date', now()));
        $categorie = $this->categorieObligatoire($request);
        $presences = $this->presencesDuJour($request, $date, $horaires);

        $document = (new WordWriter)
            ->titre('Journal des présences')
            ->sousTitre(self::LIBELLES_CATEGORIE[$categorie].' — '.$this->dateEnToutesLettres($date))
            ->tableau(
                ['Personnel', 'Section', 'Fonction', 'Arrivée', 'Départ', 'Source'],
                $presences->map(fn (Presence $presence) => [
                    $presence->enseignant?->nom ?? '—',
                    $presence->enseignant?->section ?? '—',
                    $presence->enseignant?->fonction ?? '—',
                    $presence->heure_arrivee?->format('H:i') ?? '—',
                    $presence->heure_depart?->format('H:i') ?? '—',
                    $presence->source ?? '—',
                ])->all(),
                [30, 16, 20, 11, 11, 12],
            )
            ->paragraphe($presences->count().' pointage'.($presences->count() > 1 ? 's' : '').' enregistré'.($presences->count() > 1 ? 's' : '').'.');

        return $document->telecharger("journal-{$categorie}-{$date->toDateString()}.docx");
    }

    /** GET /api/assiduite/journal-hebdomadaire/word?semaine=&categorie=&section= */
    public function journalHebdomadaireWord(Request $request, HoraireAttendu $horaires)
    {
        $categorie = $this->categorieObligatoire($request);
        $semaine = $this->semaine($request, $horaires);

        $entetes = ['Personnel', 'Section'];
        foreach ($semaine['jours'] as $jour) {
            $entetes[] = ucfirst($jour->locale('fr')->translatedFormat('D')).' '.$jour->format('d/m');
        }
        $entetes[] = 'Présences';
        $entetes[] = 'Taux';

        $lignes = $semaine['lignes']->map(function (array $ligne) use ($semaine) {
            $cellules = [$ligne['nom'], $ligne['section'] ?? '—'];

            foreach ($ligne['jours'] as $cellule) {
                $cellules[] = $this->celluleHebdomadaire($cellule);
            }

            $cellules[] = $ligne['jours_presents'].' / '.$ligne['jours_attendus'];
            $cellules[] = $ligne['taux_assiduite'] === null ? '—' : $ligne['taux_assiduite'].' %';

            return $cellules;
        })->all();

        $largeurs = array_merge([24, 12], array_fill(0, $semaine['jours']->count(), 8), [9, 7]);

        $document = WordWriter::paysage()
            ->titre('Journal hebdomadaire des présences')
            ->sousTitre(self::LIBELLES_CATEGORIE[$categorie].' — semaine du '
                .$this->dateEnToutesLettres($semaine['debut']).' au '.$this->dateEnToutesLettres($semaine['fin']))
            ->tableau($entetes, $lignes, $largeurs)
            ->paragraphe(
                "Légende : ABS = attendu, aucun pointage · SIG = absence signalée · FÉR = jour férié\n"
                ."· = non attendu ce jour-là · – = jour à venir",
                ['couleur' => '555555'],
            );

        return $document->telecharger("journal-hebdomadaire-{$categorie}-{$semaine['debut']->toDateString()}.docx");
    }

    /**
     * GET /api/assiduite/bilan-mensuel?mois=YYYY-MM&categorie=&section=
     *
     * Bilan du mois regroupé par section : une ligne par membre du personnel,
     * un sous-total par section et un total général.
     */
    public function bilanMensuel(Request $request, AssiduiteCalculator $assiduite, HoraireAttendu $horaires)
    {
        return response()->json($this->moisParSection($request, $assiduite, $horaires));
    }

    /** GET /api/assiduite/bilan-mensuel/word?mois=&categorie=&section= */
    public function bilanMensuelWord(Request $request, AssiduiteCalculator $assiduite, HoraireAttendu $horaires)
    {
        $categorie = $this->categorieObligatoire($request);
        $bilan = $this->moisParSection($request, $assiduite, $horaires);

        $document = (new WordWriter)
            ->titre('Bilan mensuel d’assiduité')
            ->sousTitre(self::LIBELLES_CATEGORIE[$categorie].' — '.$bilan['libelle']);

        foreach ($bilan['sections'] as $section) {
            $document
                ->intertitre($section['section'].' — '.$section['effectif'].' personne'
                    .($section['effectif'] > 1 ? 's' : '').' · taux moyen '.$section['taux_assiduite'].' %')
                ->tableau(
                    ['Personnel', 'Matricule', 'Fonction', 'Jours attendus', 'Jours assurés', 'Signalements', 'Taux'],
                    array_map(fn (array $ligne) => [
                        $ligne['nom'],
                        $ligne['matricule'] ?? '—',
                        $ligne['fonction'] ?? '—',
                        $ligne['jours_attendus'],
                        $ligne['jours_presents'],
                        $ligne['jours_signales'],
                        $ligne['taux_assiduite'].' %',
                    ], $section['lignes']),
                    [26, 14, 20, 11, 11, 10, 8],
                );
        }

        $document
            ->intertitre('Synthèse par section')
            ->tableau(
                ['Section', 'Effectif', 'Jours attendus', 'Jours assurés', 'Taux'],
                array_merge(
                    array_map(fn (array $section) => [
                        $section['section'],
                        $section['effectif'],
                        $section['jours_attendus'],
                        $section['jours_presents'],
                        $section['taux_assiduite'].' %',
                    ], $bilan['sections']),
                    [[
                        'TOTAL',
                        $bilan['total']['effectif'],
                        $bilan['total']['jours_attendus'],
                        $bilan['total']['jours_presents'],
                        $bilan['total']['taux_assiduite'].' %',
                    ]],
                ),
                [34, 14, 18, 18, 16],
            );

        return $document->telecharger("bilan-mensuel-{$categorie}-{$bilan['mois']}.docx");
    }

    /**
     * Bilan du mois demandé, regroupé par section.
     *
     * @return array{mois: string, libelle: string, categorie: string|null, sections: array, total: array}
     */
    private function moisParSection(Request $request, AssiduiteCalculator $assiduite, HoraireAttendu $horaires): array
    {
        $mois = Carbon::parse($request->query('mois', now()->format('Y-m')).'-01')->startOfMonth();
        $debut = $mois->copy();
        $fin = $mois->copy()->endOfMonth();

        $enseignants = $this->enseignantsAccessibles($request->user())
            ->when($request->query('section'), fn ($q, $v) => $q->whereRaw('LOWER(section) = LOWER(?)', [$v]))
            ->with([
                'emploiDuTemps',
                'presences' => fn ($query) => $query->whereBetween('date', [$debut->toDateString(), $fin->toDateString()]),
                'signalements' => fn ($query) => $query->whereDate('date', '<=', $fin->toDateString()),
            ])
            ->orderBy('nom')
            ->get();
        $enseignants = $this->filtrerParCategorie($enseignants, $request, $horaires);

        $sections = $enseignants
            ->map(fn (Enseignant $enseignant) => [
                'enseignant_id' => $enseignant->id,
                'nom' => $enseignant->nom,
                'matricule' => $enseignant->matricule,
                'fonction' => $enseignant->fonction,
                'section' => $enseignant->section,
                ...$assiduite->bilan($enseignant, $debut, $fin),
            ])
            // Les fiches sans section ne doivent pas disparaître du bilan :
            // elles sont regroupées sous un libellé explicite.
            ->groupBy(fn (array $ligne) => trim((string) $ligne['section']) ?: 'Sans section')
            ->sortKeys()
            ->map(fn (Collection $lignes, string $section) => [
                'section' => $section,
                'effectif' => $lignes->count(),
                'jours_attendus' => $lignes->sum('jours_attendus'),
                'jours_presents' => $lignes->sum('jours_presents'),
                'taux_assiduite' => $this->taux($lignes->sum('jours_presents'), $lignes->sum('jours_attendus')),
                'lignes' => $lignes->sortByDesc('taux_assiduite')->values()->all(),
            ])
            ->values();

        return [
            'mois' => $mois->format('Y-m'),
            'libelle' => ucfirst($mois->locale('fr')->translatedFormat('F Y')),
            'categorie' => $this->categorieDemandee($request),
            'sections' => $sections->all(),
            'total' => [
                'effectif' => $enseignants->count(),
                'jours_attendus' => $sections->sum('jours_attendus'),
                'jours_presents' => $sections->sum('jours_presents'),
                'taux_assiduite' => $this->taux($sections->sum('jours_presents'), $sections->sum('jours_attendus')),
            ],
        ];
    }

    private function taux(int $presents, int $attendus): float
    {
        return $attendus > 0 ? round($presents / $attendus * 100, 1) : 0.0;
    }

    /** Rendu texte d'une cellule de la grille hebdomadaire pour les documents. */
    private function celluleHebdomadaire(array $cellule): string
    {
        return match (true) {
            (bool) $cellule['ferie'] => 'FÉR',
            ! $cellule['attendu'] => '·',
            $cellule['present'] => trim(($cellule['heure_arrivee'] ?? '—').' / '.($cellule['heure_depart'] ?? '—')),
            $cellule['a_venir'] => '–',
            // L'assiduité garantie du chef d'établissement prime sur le
            // signalement : la cellule n'est jamais une absence.
            $cellule['assiduite_garantie'] ?? false => 'OK',
            $cellule['est_signale'] => 'SIG',
            default => 'ABS',
        };
    }

    private function dateEnToutesLettres(Carbon $date): string
    {
        return $date->locale('fr')->translatedFormat('l j F Y');
    }

    /**
     * Construit la grille de la semaine contenant la date demandée.
     *
     * La semaine court du lundi au samedi : le dimanche n'est jamais un jour
     * de cours et une colonne systématiquement vide rendrait la grille moins
     * lisible à l'impression.
     *
     * @return array{debut: Carbon, fin: Carbon, jours: \Illuminate\Support\Collection, lignes: \Illuminate\Support\Collection}
     */
    private function semaine(Request $request, HoraireAttendu $horaires): array
    {
        $reference = Carbon::parse($request->query('semaine', now()));
        $debut = $reference->copy()->startOfWeek(Carbon::MONDAY);
        $fin = $debut->copy()->addDays(5);

        $jours = collect();
        for ($jour = $debut->copy(); $jour->lte($fin); $jour->addDay()) {
            $jours->push($jour->copy());
        }

        $enseignants = $this->enseignantsAccessibles($request->user())
            ->when($request->query('section'), fn ($q, $v) => $q->whereRaw('LOWER(section) = LOWER(?)', [$v]))
            ->with([
                'emploiDuTemps',
                'signalements' => fn ($query) => $query->whereDate('date', '<=', $fin->toDateString()),
            ])
            ->orderBy('nom')
            ->get();
        $enseignants = $this->filtrerParCategorie($enseignants, $request, $horaires);

        // Une seule requête pour toute la semaine, indexée par enseignant puis
        // par date : une requête par cellule ferait 6 x effectif allers-retours.
        $presences = Presence::whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->whereIn('enseignant_id', $enseignants->pluck('id'))
            ->get()
            ->groupBy([
                'enseignant_id',
                fn (Presence $presence) => Carbon::parse($presence->date)->toDateString(),
            ]);

        $lignes = $enseignants->map(function (Enseignant $enseignant) use ($presences, $jours, $horaires) {
            $parDate = $presences->get($enseignant->id, collect());
            $garantie = $horaires->assiduiteGarantie($enseignant);
            $attendus = 0;
            $presents = 0;

            $cellules = $jours->map(function (Carbon $jour) use ($enseignant, $parDate, $horaires, $garantie, &$attendus, &$presents) {
                $presence = $parDate->get($jour->toDateString(), collect())->first();
                $attendu = $horaires->estAttendu($enseignant, $jour, $enseignant->emploiDuTemps);
                $aVenir = $horaires->estAVenir($jour);
                $present = (bool) ($presence?->heure_arrivee || $presence?->heure_depart);
                $signalement = $attendu && ! $aVenir
                    ? $enseignant->signalements->first(fn ($item) => $item->couvre($jour))
                    : null;

                // `attendu` reste la vérité de l'emploi du temps ; seul le
                // comptage écarte les jours à venir, pour que la grille puisse
                // les afficher comme tels plutôt qu'en « non attendu ».
                if ($attendu && ! $aVenir) {
                    $attendus++;
                    if ($present || $signalement || $garantie) {
                        $presents++;
                    }
                }

                return [
                    'date' => $jour->toDateString(),
                    'attendu' => $attendu,
                    'a_venir' => $aVenir,
                    'present' => $present,
                    // Jour attendu sans pointage pour le chef d'établissement :
                    // la cellule ne doit pas s'afficher en absence alors que le
                    // taux de la ligne reste à 100 %.
                    'assiduite_garantie' => $garantie,
                    'est_signale' => $signalement !== null,
                    'type_signalement' => $signalement?->motif,
                    // Un férié rend `attendu` faux : sans ce libellé, la cellule
                    // serait indiscernable d'un jour sans cours.
                    'ferie' => $horaires->ferie($jour),
                    'heure_arrivee' => $presence?->heure_arrivee?->format('H:i'),
                    'heure_depart' => $presence?->heure_depart?->format('H:i'),
                ];
            });

            return [
                'enseignant_id' => $enseignant->id,
                'nom' => $enseignant->nom,
                'matricule' => $enseignant->matricule,
                'section' => $enseignant->section,
                'fonction' => $enseignant->fonction,
                'jours' => $cellules,
                'jours_attendus' => $attendus,
                'jours_presents' => $presents,
                'assiduite_garantie' => $garantie,
                'taux_assiduite' => match (true) {
                    $garantie => 100.0,
                    $attendus > 0 => round($presents / $attendus * 100, 1),
                    default => null,
                },
            ];
        });

        return [
            'debut' => $debut,
            'fin' => $fin,
            'jours' => $jours,
            'lignes' => $lignes,
        ];
    }

    private function presencesDuJour(Request $request, Carbon $date, HoraireAttendu $horaires)
    {
        $enseignants = $this->enseignantsAccessibles($request->user())
            ->when($request->query('section'), fn($q, $v) => $q->whereRaw('LOWER(section) = LOWER(?)', [$v]))
            ->get();
        $enseignants = $this->filtrerParCategorie($enseignants, $request, $horaires);

        return Presence::with('enseignant')
            ->whereDate('date', $date->toDateString())
            ->whereIn('enseignant_id', $enseignants->pluck('id'))
            ->orderBy('heure_arrivee')
            ->get();
    }

    /**
     * Filtre les journaux sur les deux catégories métier. Les fiches
     * administratives sont identifiées par leur section, pas par est_admin :
     * ce dernier indique uniquement qu'un enseignant possède un compte admin.
     */
    private function filtrerParCategorie(
        Collection $enseignants,
        Request $request,
        HoraireAttendu $horaires,
    ): Collection {
        $categorie = $this->categorieDemandee($request);

        if ($categorie === null) {
            return $enseignants;
        }

        $administration = $categorie === 'administration';

        return $enseignants
            ->filter(fn (Enseignant $enseignant) => $horaires->estAdministratif($enseignant) === $administration)
            ->values();
    }

    private function categorieDemandee(Request $request): ?string
    {
        return $request->validate([
            'categorie' => ['nullable', 'in:enseignant,administration'],
        ])['categorie'] ?? null;
    }

    /**
     * Catégorie exigée pour les documents : enseignants et administratifs ne
     * figurent jamais dans le même journal, un export sans catégorie
     * produirait précisément le document mixte que l'on veut éviter.
     */
    private function categorieObligatoire(Request $request): string
    {
        return $request->validate([
            'categorie' => ['required', 'in:enseignant,administration'],
        ])['categorie'];
    }

    /** GET /api/assiduite/personnel-inactif?jours=N — enseignants sans pointage depuis N jours. */
    public function personnelInactif(Request $request)
    {
        $jours = (int) $request->query('jours', 7);
        $seuil = now()->subDays($jours)->toDateString();

        $enseignants = $this->enseignantsAccessibles($request->user())->get();

        $dernieresPresences = Presence::whereIn('enseignant_id', $enseignants->pluck('id'))
            ->selectRaw('enseignant_id, MAX(date) as derniere_date')
            ->groupBy('enseignant_id')
            ->pluck('derniere_date', 'enseignant_id');

        $inactifs = $enseignants->filter(function (Enseignant $enseignant) use ($dernieresPresences, $seuil) {
            $derniere = $dernieresPresences->get($enseignant->id);

            return ! $derniere || $derniere < $seuil;
        })->values();

        return response()->json($inactifs->map(fn(Enseignant $e) => [
            'enseignant_id' => $e->id,
            'nom' => $e->nom,
            'section' => $e->section,
            'derniere_presence' => $dernieresPresences->get($e->id),
        ]));
    }

    /** GET /api/assiduite/sans-presence — enseignants n'ayant jamais pointé (aucune présence enregistrée). */
    public function sansPresence(Request $request)
    {
        return response()->json($this->enseignantsSansPresence($request)->map(fn(Enseignant $e) => [
            'enseignant_id' => $e->id,
            'nom' => $e->nom,
            'matricule' => $e->matricule,
            'section' => $e->section,
            'fonction' => $e->fonction,
            'tel' => $e->tel,
        ]));
    }

    /** GET /api/assiduite/sans-presence/pdf — exporte la liste des enseignants n'ayant jamais pointé en PDF. */
    public function sansPresencePdf(Request $request)
    {
        $pdf = Pdf::loadView('pdf.sans-presence', [
            'date' => now(),
            'enseignants' => $this->enseignantsSansPresence($request),
        ]);

        return $pdf->download('sans-presence-'.now()->toDateString().'.pdf');
    }

    private function enseignantsSansPresence(Request $request)
    {
        return $this->enseignantsAccessibles($request->user())
            ->whereDoesntHave('presences')
            ->orderBy('nom')
            ->get();
    }
}
