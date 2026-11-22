<?php

namespace App\Exports;

use App\Models\EmploiDuTemps;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Classeur d'emploi du temps : un onglet par classe, plus un onglet caché qui
 * porte les listes déroulantes. Les heures proposées sont celles des périodes
 * de l'établissement (table `periodes_cours`, écran Configuration) et non plus
 * une grille figée dans le code : un établissement qui redécoupe sa journée
 * voyait sinon son propre classeur refuser ses horaires. Le jour se choisit en
 * clair — « Lundi » plutôt que « 1 ».
 */
class EmploisWorkbookExport implements Export, WithMultipleSheets
{
    public const TYPES_COURS = ['Théorique', 'Pratique'];

    public const HEADINGS = [
        'nom_enseignant',
        'code_classe',
        'code_discipline',
        'jour',
        'heure_debut',
        'heure_fin',
        'salle',
        'type_cours',
    ];

    /**
     * @param  array<int, array{id: int, code: string}>  $classes
     * @param  array<int, string>  $nomsEnseignants
     * @param  array<int, string>  $codesDisciplines
     * @param  array<int, string>  $heuresDebut  heures de début des périodes de l'établissement
     * @param  array<int, string>  $heuresFin  heures de fin des périodes de l'établissement
     * @param  array<int, array<int, mixed>>  $rowsByClasse
     */
    public function __construct(
        private readonly array $classes,
        private readonly array $nomsEnseignants,
        private readonly array $codesDisciplines,
        private readonly array $heuresDebut,
        private readonly array $heuresFin,
        private readonly array $rowsByClasse,
    ) {}

    public function sheets(): array
    {
        $sheets = [];
        $usedTitles = [];

        foreach ($this->classes as $classe) {
            $title = $this->uniqueTitle((string) $classe['code'], $usedTitles);
            $sheets[] = new EmploisClasseSheetExport(
                $title,
                $this->rowsByClasse[$classe['id']] ?? [],
            );
        }

        if ($sheets === []) {
            $sheets[] = new EmploisClasseSheetExport('Emplois', []);
        }

        $sheets[] = new EmploisListsSheetExport(
            $this->nomsEnseignants,
            $this->codesDisciplines,
            $this->heuresDebut,
            $this->heuresFin,
        );

        return $sheets;
    }

    private function uniqueTitle(string $code, array &$usedTitles): string
    {
        $base = preg_replace('/[\\\\\/?*\[\]:]/', ' ', trim($code)) ?: 'Classe';
        $title = substr($base, 0, 31);
        $suffix = 2;

        while (in_array(strtolower($title), $usedTitles, true)) {
            $ending = ' (' . $suffix++ . ')';
            $title = substr($base, 0, 31 - strlen($ending)) . $ending;
        }

        $usedTitles[] = strtolower($title);

        return $title;
    }
}

class EmploisClasseSheetExport implements Export, FromArray, WithEvents, WithHeadings, WithTitle
{
    private const MAX_INPUT_ROW = 1001;

    public function __construct(
        private readonly string $sheetTitle,
        private readonly array $rows,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return EmploisWorkbookExport::HEADINGS;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $this->addListValidation($sheet, 'A', '=NomsEnseignants');
                $this->addListValidation($sheet, 'C', '=CodesDisciplines');
                $this->addListValidation($sheet, 'D', '=Jours');
                $this->addListValidation($sheet, 'E', '=HeuresDebut', 'Début de période');
                $this->addListValidation($sheet, 'F', '=HeuresFin', 'Fin de période');
                $this->addListValidation($sheet, 'H', '=TypesCours');
            },
        ];
    }

    /**
     * Les heures proposées sont celles des périodes de l'établissement : on le
     * dit dans la bulle de saisie, sinon une liste restreinte passe pour un
     * défaut du logiciel alors qu'il suffit d'ajouter la période manquante
     * dans Configuration.
     */
    private function addListValidation(Worksheet $sheet, string $column, string $formula, ?string $promptTitle = null): void
    {
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowInputMessage($promptTitle !== null);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Valeur non autorisée');
        $validation->setError('Sélectionnez une valeur proposée dans la liste.');

        if ($promptTitle !== null) {
            $validation->setPromptTitle($promptTitle);
            $validation->setPrompt("Choisissez une heure parmi les périodes de l'établissement (Configuration > Périodes de cours).");
        }

        $validation->setFormula1($formula);

        $sheet->setDataValidation($column . '2:' . $column . self::MAX_INPUT_ROW, $validation);
    }
}

class EmploisListsSheetExport implements Export, FromArray, WithEvents, WithHeadings, WithTitle
{
    public function __construct(
        private readonly array $nomsEnseignants,
        private readonly array $codesDisciplines,
        private readonly array $heuresDebut,
        private readonly array $heuresFin,
    ) {}

    /** @return array<string, array<int, string>> Colonne de l'onglet -> valeurs proposées. */
    private function colonnes(): array
    {
        return [
            'A' => array_values($this->nomsEnseignants),
            'B' => array_values($this->codesDisciplines),
            'C' => array_values($this->heuresDebut),
            'D' => array_values($this->heuresFin),
            'E' => EmploisWorkbookExport::TYPES_COURS,
            'F' => array_values(EmploiDuTemps::NOMS_JOURS),
        ];
    }

    public function array(): array
    {
        $colonnes = array_values($this->colonnes());
        $rows = [];
        $rowCount = max(1, ...array_map('count', $colonnes));

        for ($index = 0; $index < $rowCount; $index++) {
            $rows[] = array_map(fn(array $valeurs) => $valeurs[$index] ?? null, $colonnes);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['noms_enseignants', 'codes_disciplines', 'heures_debut', 'heures_fin', 'types_cours', 'jours'];
    }

    public function title(): string
    {
        return '_listes';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $spreadsheet = $sheet->getParent();
                $colonnes = $this->colonnes();
                $plages = [
                    'NomsEnseignants' => 'A',
                    'CodesDisciplines' => 'B',
                    'HeuresDebut' => 'C',
                    'HeuresFin' => 'D',
                    'TypesCours' => 'E',
                    'Jours' => 'F',
                ];

                foreach ($plages as $name => $column) {
                    $spreadsheet->addNamedRange(new NamedRange(
                        $name,
                        $sheet,
                        '$' . $column . '$2:$' . $column . '$' . max(2, count($colonnes[$column]) + 1),
                    ));
                }

                $sheet->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
            },
        ];
    }
}
