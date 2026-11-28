<?php

namespace App\Http\Controllers\Api;

use App\Exports\ReferentielApcWorkbookExport;
use App\Http\Controllers\Controller;
use App\Imports\FeuillesBrutesImport;
use App\Models\Module;
use App\Models\Specialite;
use App\Services\ReferentielApc;
use App\Support\Classeur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Modèle / export / import du référentiel APC, dans la disposition du
 * classeur d'organisation pédagogique : un onglet par spécialité, quatre
 * colonnes de blocs, les heures en regard.
 *
 * Pourquoi un contrôleur à part et non une entrée de plus dans
 * SpreadsheetController : les entités de ce dernier partagent un format — une
 * ligne, des colonnes nommées, un modèle par ligne — que le référentiel ne
 * suit pas. La colonne n'y désigne pas un champ mais un bloc, une ligne peut
 * produire deux modules, et l'import rend des avertissements et un
 * recoupement d'heures en plus des erreurs.
 */
class ReferentielApcController extends Controller
{
    /** GET /api/spreadsheet/referentiel/template — classeur vierge à la bonne disposition. */
    public function template(): BinaryFileResponse
    {
        return Excel::download(new ReferentielApcWorkbookExport([]), 'referentiel-apc-modele.xlsx');
    }

    /** GET /api/spreadsheet/referentiel/export — le référentiel enregistré, même disposition. */
    public function export(): BinaryFileResponse
    {
        return Excel::download(
            new ReferentielApcWorkbookExport($this->groupesExportables()),
            'referentiel-apc-export.xlsx'
        );
    }

    /**
     * POST /api/spreadsheet/referentiel/import
     *
     * Deux temps séparés : `apercu=1` lit le classeur et rend ce qu'il a
     * compris sans rien écrire. C'est le mode qu'emploie l'écran avant de
     * demander confirmation — un classeur d'organisation pédagogique réécrit
     * le référentiel d'une filière entière, et le découvrir après coup coûte
     * cher.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'niveau' => ['nullable', 'string', 'max:50'],
            'apercu' => ['nullable', 'boolean'],
        ]);

        $fichier = $request->file('file');
        $feuilles = $this->feuilles($fichier->getRealPath(), $fichier);

        $lecture = ReferentielApc::lire($feuilles, $request->string('niveau')->toString() ?: null);

        $apercu = [
            'specialites' => array_map(fn (array $specialite) => [
                'code' => $specialite['code'],
                'feuille' => $specialite['feuille'],
                'niveau' => $specialite['niveau'],
                'modules' => count($specialite['modules']),
                'total_volume_horaire' => $specialite['total_volume_horaire'],
                'codes_fabriques' => count(array_filter(
                    $specialite['modules'],
                    fn (array $module) => $module['code_fabrique'],
                )),
                'volumes_horaires_manquants' => count(array_filter(
                    $specialite['modules'],
                    fn (array $module) => $module['volume_horaire'] === null,
                )),
            ], $lecture['specialites']),
            'erreurs' => $lecture['erreurs'],
            'avertissements' => $lecture['avertissements'],
            'controles' => $lecture['controles'],
        ];

        if ($request->boolean('apercu')) {
            return response()->json([...$apercu, 'applique' => false]);
        }

        // Un classeur partiellement écrit serait pire qu'un import refusé :
        // la moitié d'un référentiel ne se repère pas à l'œil.
        $bilan = DB::transaction(fn () => ReferentielApc::appliquer($lecture['specialites']));

        return response()->json([...$apercu, 'applique' => true, 'bilan' => $bilan]);
    }

    /**
     * Lit le classeur en lignes brutes, et récupère les noms d'onglets : ce
     * sont eux qui portent le code de la spécialité, et `Excel::toArray` ne
     * les expose pas.
     *
     * @return array<int, array{nom: string, lignes: array<int, array<int, mixed>>}>
     */
    private function feuilles(string $chemin, mixed $fichier): array
    {
        $onglets = Classeur::nomsOnglets($chemin);
        $feuilles = [];

        foreach (Excel::toArray(new FeuillesBrutesImport, $fichier) as $index => $lignes) {
            $feuilles[] = [
                'nom' => $onglets[$index] ?? 'Feuille '.($index + 1),
                'lignes' => $lignes,
            ];
        }

        return $feuilles;
    }


    /**
     * Le référentiel regroupé par spécialité et par niveau — un onglet par
     * groupe à l'export.
     *
     * @return array<int, array<string, mixed>>
     */
    private function groupesExportables(): array
    {
        $specialites = Specialite::orderBy('ordre')->orderBy('code')->get()->keyBy('id');

        return Module::ordreBulletin()
            ->orderBy('specialite_id')
            ->get()
            ->groupBy(fn (Module $module) => $module->specialite_id.'|'.$module->niveau)
            ->map(fn ($modules) => [
                'code' => $specialites[$modules->first()->specialite_id]?->code ?? 'SPECIALITE',
                'niveau' => $modules->first()->niveau,
                'modules' => $modules->map(fn (Module $module) => [
                    'bloc' => $module->bloc,
                    'code' => $module->code,
                    'intitule' => $module->intitule,
                    'volume_horaire' => $module->volume_horaire,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
