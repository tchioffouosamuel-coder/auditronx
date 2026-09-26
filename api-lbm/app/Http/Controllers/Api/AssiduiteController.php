<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\Enseignant;
use App\Models\Presence;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/** Assiduité & rapports (§4.2) — statistiques par section, journal, personnel inactif. */
class AssiduiteController extends Controller
{
    use AccessibleEnseignants;

    /** GET /api/assiduite/stats?debut=&fin=&section= */
    public function stats(Request $request)
    {
        $debut = Carbon::parse($request->query('debut', now()->startOfMonth()));
        $fin = Carbon::parse($request->query('fin', now()->endOfMonth()));
        $enseignants = $this->enseignantsAccessibles($request->user())
            ->when($request->query('section'), fn($q, $v) => $q->whereRaw('LOWER(section) = LOWER(?)', [$v]))
            ->with('emploiDuTemps')->get();

        $presences = Presence::whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->whereIn('enseignant_id', $enseignants->pluck('id'))
            ->whereNotNull('heure_arrivee')
            ->get()
            ->groupBy('enseignant_id');

        $lignes = $enseignants->map(function (Enseignant $enseignant) use ($presences, $debut, $fin) {
            $datesAttendues = collect();
            for ($date = $debut->copy(); $date->lte($fin); $date->addDay()) {
                if ($enseignant->emploiDuTemps->contains('jour', $date->isoWeekday())) $datesAttendues->push($date->toDateString());
            }
            $datesAttendues = $datesAttendues->unique()->values();
            $datesPresents = $presences->get($enseignant->id, collect())->pluck('date')->map(fn($date) => Carbon::parse($date)->toDateString());
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
}
