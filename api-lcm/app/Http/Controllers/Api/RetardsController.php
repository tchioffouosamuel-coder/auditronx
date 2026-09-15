<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Services\RetardCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Retards & bilans (§4.2) — seuils de tolérance, bilans PDF. */
class RetardsController extends Controller
{
    use AccessibleEnseignants;

    public function parametres(RetardCalculator $retards)
    {
        return response()->json(['tolerance_minutes' => $retards->toleranceMinutes()]);
    }

    public function definirParametres(Request $request, RetardCalculator $retards)
    {
        $data = $request->validate(['tolerance_minutes' => ['required', 'integer', 'min:0', 'max:120']]);
        $retards->definirTolerance($data['tolerance_minutes']);

        return response()->json(['tolerance_minutes' => $retards->toleranceMinutes()]);
    }

    /** GET /api/retards?debut=&fin=&section= — liste des retards sur une période. */
    public function index(Request $request, RetardCalculator $retards)
    {
        [$debut, $fin, $enseignants] = $this->periodeEtEnseignants($request);

        $lignes = $this->calculerRetards($enseignants, $debut, $fin, $retards);

        return response()->json($lignes->values());
    }

    /** GET /api/retards/bilan-cumule?debut=&fin=&section= — bilan PDF de tous les enseignants du périmètre. */
    public function bilanCumule(Request $request, RetardCalculator $retards)
    {
        [$debut, $fin, $enseignants] = $this->periodeEtEnseignants($request);

        $lignes = $this->calculerRetards($enseignants, $debut, $fin, $retards);

        $pdf = Pdf::loadView('pdf.retards-cumule', [
            'debut' => $debut, 'fin' => $fin, 'lignes' => $lignes,
        ]);

        return $pdf->download("bilan-retards-{$debut->toDateString()}-{$fin->toDateString()}.pdf");
    }

    /** GET /api/retards/bilan/{enseignant}?debut=&fin= — fiche individuelle PDF. */
    public function bilanIndividuel(Request $request, Enseignant $enseignant, RetardCalculator $retards)
    {
        abort_unless($this->peutAccederA($request->user(), $enseignant), 403);

        $debut = Carbon::parse($request->query('debut', now()->startOfMonth()));
        $fin = Carbon::parse($request->query('fin', now()->endOfMonth()));

        $ligne = $this->calculerRetards(collect([$enseignant]), $debut, $fin, $retards)->first();

        $pdf = Pdf::loadView('pdf.retards-individuel', ['enseignant' => $enseignant, 'debut' => $debut, 'fin' => $fin, 'ligne' => $ligne]);

        return $pdf->download("bilan-retards-{$enseignant->matricule}.pdf");
    }

    private function periodeEtEnseignants(Request $request): array
    {
        $debut = Carbon::parse($request->query('debut', now()->startOfMonth()));
        $fin = Carbon::parse($request->query('fin', now()->endOfMonth()));

        $enseignants = $this->enseignantsAccessibles($request->user())
            ->when($request->query('section'), fn ($q, $v) => $q->where('section', $v))
            ->get();

        return [$debut, $fin, $enseignants];
    }

    private function calculerRetards($enseignants, Carbon $debut, Carbon $fin, RetardCalculator $retards)
    {
        $presences = Presence::whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->whereIn('enseignant_id', $enseignants->pluck('id'))
            ->whereNotNull('heure_arrivee')
            ->get()
            ->groupBy('enseignant_id');

        return $enseignants->map(function (Enseignant $enseignant) use ($presences, $retards) {
            $minutesTotal = 0;
            $joursRetard = 0;

            foreach ($presences->get($enseignant->id, collect()) as $presence) {
                $minutes = $retards->minutesDeRetard($enseignant, $presence);

                if ($minutes) {
                    $minutesTotal += $minutes;
                    $joursRetard++;
                }
            }

            return [
                'enseignant_id' => $enseignant->id,
                'nom' => $enseignant->nom,
                'matricule' => $enseignant->matricule,
                'section' => $enseignant->section,
                'jours_retard' => $joursRetard,
                'minutes_retard_total' => $minutesTotal,
            ];
        });
    }
}
