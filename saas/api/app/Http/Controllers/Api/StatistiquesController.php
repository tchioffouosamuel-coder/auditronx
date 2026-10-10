<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Services\BilanIndividuel;
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

    public function exportZip(Request $request, BilanIndividuel $bilan): BinaryFileResponse
    {
        $debut = Carbon::parse($request->query('debut', now()->startOfMonth()));
        $fin = Carbon::parse($request->query('fin', now()->endOfMonth()));

        $enseignants = $this->enseignantsAccessibles($request->user())
            ->when($request->query('section'), fn($q, $v) => $q->whereRaw('LOWER(section) = LOWER(?)', [$v]))
            ->get();

        $zipRelativePath = 'exports/bilans-' . now()->timestamp . '.zip';
        $zipPath = Storage::path($zipRelativePath);
        Storage::makeDirectory('exports');

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($enseignants as $enseignant) {
            // Même calcul que la fiche individuelle (BilanIndividuel) : jours
            // attendus, présences, retards et taux réels. L'ancienne version
            // remplissait la vue avec des zéros en dur (taux 0 % pour tous).
            $pdf = Pdf::loadView('pdf.retards-individuel', [
                ...$bilan->donnees($enseignant, $debut->copy(), $fin->copy()),
                'debut' => $debut,
                'fin' => $fin,
            ]);

            $zip->addFromString("{$enseignant->matricule}.pdf", $pdf->output());
        }

        $zip->close();

        return response()->download($zipPath, 'bilans-retards.zip')->deleteFileAfterSend();
    }
}
