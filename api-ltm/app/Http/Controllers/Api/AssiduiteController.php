<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Services\HoraireAttendu;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/** Assiduité & rapports (§4.2) — statistiques par section, journal, personnel inactif. */
class AssiduiteController extends Controller
{
    use AccessibleEnseignants;

    /** GET /api/assiduite/stats?debut=&fin=&section= */
    public function stats(Request $request, HoraireAttendu $horaires)
    {
        $debut = Carbon::parse($request->query('debut', now()->startOfMonth()));
        $fin = Carbon::parse($request->query('fin', now()->endOfMonth()));
        $enseignants = $this->enseignantsAccessibles($request->user())
            ->when($request->query('section'), fn($q, $v) => $q->whereRaw('LOWER(section) = LOWER(?)', [$v]))
            ->with('emploiDuTemps')
            ->get();

        $presences = Presence::whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->whereIn('enseignant_id', $enseignants->pluck('id'))
            ->whereNotNull('heure_arrivee')
            ->get()
            ->groupBy('enseignant_id');

        $lignes = $enseignants->map(function (Enseignant $enseignant) use ($presences, $debut, $fin, $horaires) {
            $datesAttendues = collect();
            for ($date = $debut->copy(); $date->lte($fin); $date->addDay()) {
                if ($horaires->estAttendu($enseignant, $date)) {
                    $datesAttendues->push($date->toDateString());
                }
            }
            $datesAttendues = $datesAttendues->unique()->values();
            $datesPresents = $presences->get($enseignant->id, collect())
                ->pluck('date')
                ->map(fn($date) => Carbon::parse($date)->toDateString());
            $joursPresents = $datesPresents->intersect($datesAttendues)->unique()->count();
            $joursAttendus = $datesAttendues->count();

            return [
                'enseignant_id' => $enseignant->id,
                'nom' => $enseignant->nom,
                'section' => $enseignant->section,
                'jours_presents' => $joursPresents,
                'jours_attendus' => $joursAttendus,
                'taux_assiduite' => $joursAttendus > 0 ? round($joursPresents / $joursAttendus * 100, 1) : 0.0,
            ];
        });

        return response()->json($lignes->sortByDesc('taux_assiduite')->values());
    }

    /** GET /api/assiduite/journal?date=&section= — journal des présences d'un jour donné. */
    public function journal(Request $request)
    {
        $date = Carbon::parse($request->query('date', now()));

        return response()->json($this->presencesDuJour($request, $date));
    }

    /** GET /api/assiduite/journal/pdf?date=&section= — exporte le journal visible en PDF. */
    public function journalPdf(Request $request)
    {
        $date = Carbon::parse($request->query('date', now()));
        $pdf = Pdf::loadView('pdf.journal-presences', [
            'date' => $date,
            'presences' => $this->presencesDuJour($request, $date),
        ]);

        return $pdf->download("journal-presences-{$date->toDateString()}.pdf");
    }

    /**
     * GET /api/assiduite/journal-hebdomadaire?semaine=&section=
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
            ]),
            'lignes' => $semaine['lignes'],
        ]);
    }

    /** GET /api/assiduite/journal-hebdomadaire/pdf?semaine=&section= */
    public function journalHebdomadairePdf(Request $request, HoraireAttendu $horaires)
    {
        $semaine = $this->semaine($request, $horaires);

        $pdf = Pdf::loadView('pdf.journal-hebdomadaire', $semaine)
            ->setPaper('a4', 'landscape');

        return $pdf->download("journal-hebdomadaire-{$semaine['debut']->toDateString()}.pdf");
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
            ->with('emploiDuTemps')
            ->orderBy('nom')
            ->get();

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
            $attendus = 0;
            $presents = 0;

            $cellules = $jours->map(function (Carbon $jour) use ($enseignant, $parDate, $horaires, &$attendus, &$presents) {
                $presence = $parDate->get($jour->toDateString(), collect())->first();
                $attendu = $horaires->estAttendu($enseignant, $jour, $enseignant->emploiDuTemps);
                $present = $presence?->heure_arrivee !== null;

                if ($attendu) {
                    $attendus++;
                    if ($present) {
                        $presents++;
                    }
                }

                return [
                    'date' => $jour->toDateString(),
                    'attendu' => $attendu,
                    'present' => $present,
                    'heure_arrivee' => $presence?->heure_arrivee?->format('H:i'),
                    'heure_depart' => $presence?->heure_depart?->format('H:i'),
                ];
            });

            return [
                'enseignant_id' => $enseignant->id,
                'nom' => $enseignant->nom,
                'matricule' => $enseignant->matricule,
                'section' => $enseignant->section,
                'jours' => $cellules,
                'jours_attendus' => $attendus,
                'jours_presents' => $presents,
                'taux_assiduite' => $attendus > 0 ? round($presents / $attendus * 100, 1) : null,
            ];
        });

        return [
            'debut' => $debut,
            'fin' => $fin,
            'jours' => $jours,
            'lignes' => $lignes,
        ];
    }

    private function presencesDuJour(Request $request, Carbon $date)
    {
        $enseignants = $this->enseignantsAccessibles($request->user())
            ->when($request->query('section'), fn($q, $v) => $q->whereRaw('LOWER(section) = LOWER(?)', [$v]))
            ->pluck('id');

        return Presence::with('enseignant')
            ->whereDate('date', $date->toDateString())
            ->whereIn('enseignant_id', $enseignants)
            ->orderBy('heure_arrivee')
            ->get();
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
