<?php

namespace App\Exports;

use App\Support\Classeur;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Classeur de saisie des notes d'une classe pour un trimestre : un onglet par
 * module programmé, la liste des élèves déjà posée dans chaque onglet.
 *
 * C'est la voie hors ligne, et dans bien des établissements la voie
 * principale : la connexion ne tient pas une heure de saisie, et les notes
 * circulent de toute façon en classeur entre les enseignants et le censeur.
 * Le fichier est **pré-rempli** — matricule et nom en colonnes figées — pour
 * qu'aucun appariement à la main ne soit nécessaire au retour.
 *
 * Le nom de l'onglet porte le code du module : c'est par lui que l'import
 * retrouve la ligne de maquette.
 */
class NotesWorkbookExport implements Export, WithMultipleSheets
{
    /**
     * @param  array<int, array{code: string, intitule: string, lignes: array<int, array<int, mixed>>}>  $modules
     */
    public function __construct(
        private readonly array $modules,
        private readonly int $baremeUea,
        private readonly int $baremeIntegratrice,
    ) {}

    public function sheets(): array
    {
        if ($this->modules === []) {
            return [new NotesModuleSheetExport('Aucun module', [], $this->entetes())];
        }

        $sheets = [];
        $titres = [];

        foreach ($this->modules as $module) {
            $sheets[] = new NotesModuleSheetExport(
                Classeur::nomOngletUnique($module['code'], $titres, 'Module'),
                $module['lignes'],
                $this->entetes(),
            );
        }

        return $sheets;
    }

    /**
     * Les barèmes sont écrits dans l'en-tête des colonnes de notes
     * (« uea_60 ») : un classeur rempli six mois plus tard, alors que la
     * répartition a changé, doit dire sur quoi il a été rempli.
     *
     * @return array<int, string>
     */
    private function entetes(): array
    {
        return ['matricule', 'nom', 'uea_'.$this->baremeUea, 'integratrice_'.$this->baremeIntegratrice];
    }
}

/** Un onglet : les élèves d'une classe et leurs deux notes pour un module. */
class NotesModuleSheetExport implements Export, FromArray, WithHeadings, WithTitle
{
    /**
     * @param  array<int, array<int, mixed>>  $lignes
     * @param  array<int, string>  $entetes
     */
    public function __construct(
        private readonly string $titre,
        private readonly array $lignes,
        private readonly array $entetes,
    ) {}

    public function array(): array
    {
        return $this->lignes;
    }

    public function headings(): array
    {
        return $this->entetes;
    }

    public function title(): string
    {
        return $this->titre;
    }
}
