<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\Enseignant;
use App\Services\BilanCumule;
use App\Services\BilanIndividuel;
use App\Services\GrilleHoraire;
use App\Services\HoraireAttendu;
use App\Services\PonctualiteCalculator;
use App\Services\RetardCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Retards & bilans (§4.2) — seuils de tolérance, bilans PDF. */
class RetardsController extends Controller
{
    use AccessibleEnseignants;

    public function parametres(RetardCalculator $retards, PonctualiteCalculator $ponctualite)
    {
        return response()->json([
            'tolerance_minutes' => $retards->toleranceMinutes(),
            'coefficient_minutes' => $ponctualite->coefficientMinutes(),
        ]);
    }

    public function definirParametres(Request $request, RetardCalculator $retards, PonctualiteCalculator $ponctualite)
    {
        $data = $request->validate([
            'tolerance_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'coefficient_minutes' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);
        $retards->definirTolerance($data['tolerance_minutes']);

        if (isset($data['coefficient_minutes'])) {
            $ponctualite->definirCoefficientMinutes($data['coefficient_minutes']);
        }

        return response()->json([
            'tolerance_minutes' => $retards->toleranceMinutes(),
            'coefficient_minutes' => $ponctualite->coefficientMinutes(),
        ]);
    }

    /** GET /api/retards?debut=&fin=&section= — liste des retards sur une période. */
    public function index(Request $request, BilanCumule $bilans)
    {
        [$debut, $fin, $enseignants] = $this->periodeEtEnseignants($request);

        $lignes = collect($bilans->classement($enseignants, $debut, $fin))
            ->sortBy(fn (array $ligne) => mb_strtolower($ligne['nom']), SORT_NATURAL)
            ->values();

        return response()->json($lignes);
    }

    /** GET /api/retards/classement-ponctualite?debut=&fin=&section= — classement des enseignants par ponctualité. */
    public function classementPonctualite(Request $request, HoraireAttendu $horaires, BilanCumule $bilans)
    {
        [$debut, $fin, $enseignants] = $this->periodeEtEnseignants($request);

        if (mb_strtolower(trim((string) $request->query('section'))) !== HoraireAttendu::SECTION_ADMINISTRATIVE) {
            $enseignants = $enseignants->reject(fn (Enseignant $enseignant) => $horaires->estAdministratif($enseignant));
        }

        return response()->json($bilans->classement($enseignants, $debut, $fin));
    }

    /** GET /api/retards/bilan-cumule?debut=&fin=&section=&tri= — bilan PDF de tous les enseignants du périmètre. */
    public function bilanCumule(Request $request, HoraireAttendu $horaires, BilanCumule $bilans)
    {
        [$debut, $fin, $enseignants] = $this->periodeEtEnseignants($request);

        // Le personnel administratif n'apparaît pas sur le bilan des enseignants,
        // mais la fiche de la section « Administration » doit le lister en entier.
        if (mb_strtolower(trim((string) $request->query('section'))) !== HoraireAttendu::SECTION_ADMINISTRATIVE) {
            $enseignants = $enseignants->reject(fn(Enseignant $enseignant) => $horaires->estAdministratif($enseignant));
        }

        $lignes = collect($bilans->classement($enseignants, $debut, $fin));

        // Tri par score de ponctualité (classement) si demandé, sinon par nom.
        $data = $request->query('tri') === 'ponctualite'
            ? $lignes->sortBy(fn(array $ligne) => [-$ligne['score_ponctualite'], mb_strtolower($ligne['nom'])])->values()->all()
            : $lignes->sortBy(fn(array $ligne) => mb_strtolower($ligne['nom']), SORT_NATURAL)->values()->all();

        $pdf = Pdf::loadView('pdf.retards-cumule', [
            'data' => $data,
            'mois' => $debut->format('m'),
            'annee' => $debut->year,
            'classement' => $request->query('tri') === 'ponctualite',
        ]);

        return $pdf->download("bilan-retards-{$debut->toDateString()}-{$fin->toDateString()}.pdf");
    }

    /** GET /api/retards/bilan/{enseignant}?debut=&fin= — fiche individuelle PDF. */
    public function bilanIndividuel(Request $request, Enseignant $enseignant, BilanIndividuel $bilan)
    {
        abort_unless($this->peutAccederA($request->user(), $enseignant), 403);

        $debut = Carbon::parse($request->query('debut', now()->startOfMonth()));
        $fin = Carbon::parse($request->query('fin', now()->endOfMonth()));

        $fiche = $bilan->donnees($enseignant, $debut, $fin);

        $pdf = Pdf::loadView('pdf.retards-individuel', [
            ...$fiche,
            'debut' => $debut,
            'fin' => $fin,
        ]);

        return $pdf->download("bilan-retards-{$enseignant->matricule}.pdf");
    }

    private function periodeEtEnseignants(Request $request): array
    {
        $debut = Carbon::parse($request->query('debut', now()->startOfMonth()));
        $fin = Carbon::parse($request->query('fin', now()->endOfMonth()));

        $enseignants = $this->enseignantsAccessibles($request->user())
            ->when($request->query('section'), function ($q, $section) {
                $normalized = mb_strtolower(trim((string) $section));
                $sections = $normalized === 'générale'
                    ? ['générale', 'enseignement générale', 'enseignement général']
                    : [$normalized];

                return $q->whereRaw(
                    'LOWER(TRIM(section)) IN (' . implode(',', array_fill(0, count($sections), '?')) . ')',
                    $sections,
                );
            })
            ->get();

        return [$debut, $fin, $enseignants];
    }

}
