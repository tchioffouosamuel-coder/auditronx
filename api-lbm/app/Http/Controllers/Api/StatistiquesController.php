<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\Enseignant;
use App\Services\RetardCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/** Exports PDF groupés (§4.2) — un bilan de retards individuel par enseignant, zippés. */
class StatistiquesController extends Controller
{
    use AccessibleEnseignants;

    public function exportZip(Request $request, RetardCalculator $retards): BinaryFileResponse
    {
        $debut = Carbon::parse($request->query('debut', now()->startOfMonth()));
        $fin = Carbon::parse($request->query('fin', now()->endOfMonth()));

        $enseignants = $this->enseignantsAccessibles($request->user())
            ->when($request->query('section'), fn ($q, $v) => $q->where('section', $v))
            ->get();

        $zipRelativePath = 'exports/bilans-'.now()->timestamp.'.zip';
        $zipPath = Storage::path($zipRelativePath);
        Storage::makeDirectory('exports');

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($enseignants as $enseignant) {
            $pdf = Pdf::loadView('pdf.retards-individuel', [
                'enseignant' => $enseignant,
                'debut' => $debut,
                'fin' => $fin,
                'ligne' => $this->ligneRetard($enseignant, $debut, $fin, $retards),
            ]);

            $zip->addFromString("{$enseignant->matricule}.pdf", $pdf->output());
        }

        $zip->close();

        return response()->download($zipPath, 'bilans-retards.zip')->deleteFileAfterSend();
    }

    private function ligneRetard(Enseignant $enseignant, Carbon $debut, Carbon $fin, RetardCalculator $retards): array
    {
        $presences = $enseignant->presences()
            ->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->whereNotNull('heure_arrivee')
            ->get();

        $minutesTotal = 0;
        $joursRetard = 0;

        foreach ($presences as $presence) {
            $minutes = $retards->minutesDeRetard($enseignant, $presence);

            if ($minutes) {
                $minutesTotal += $minutes;
                $joursRetard++;
            }
        }

        return ['jours_retard' => $joursRetard, 'minutes_retard_total' => $minutesTotal];
    }
}
