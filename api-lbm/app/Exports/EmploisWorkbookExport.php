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

class EmploisWorkbookExport implements Export, WithMultipleSheets
{
    public const HEADINGS = [
        'matricule_enseignant',
        'code_classe',
        'code_discipline',
        'jour',
        'heure_debut',
        'heure_fin',
        'salle',
        'type_cours',
    ];

    public function __construct(
        private readonly array $classes,
        private readonly array $matriculesEnseignants,
        private readonly array $codesDisciplines,
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

        $sheets[] = new EmploisListsSheetExport($this->matriculesEnseignants, $this->codesDisciplines);

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
                $this->addListValidation($sheet, 'A', '=MatriculesEnseignants', 'Choisir un matricule dans la liste.');
                $this->addListValidation($sheet, 'C', '=CodesDisciplines', 'Choisir une matière dans la liste.');
                $this->addListValidation($sheet, 'E', '=HeuresDebut', 'Choisir une heure de début dans la liste.');
                $this->addListValidation($sheet, 'F', '=HeuresFin', 'Choisir une heure de fin dans la liste.');
            },
        ];
    }

    private function addListValidation(Worksheet $sheet, string $column, string $formula, string $prompt): void
    {
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(false);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setPromptTitle('Valeur prédéfinie');
        $validation->setPrompt($prompt);
        $validation->setErrorTitle('Valeur non autorisée');
        $validation->setError('Sélectionnez une valeur proposée dans la liste.');
        $validation->setFormula1($formula);

        $sheet->setDataValidation($column . '2:' . $column . self::MAX_INPUT_ROW, $validation);
    }
}

class EmploisListsSheetExport implements Export, FromArray, WithEvents, WithHeadings, WithTitle
{
    public function __construct(
        private readonly array $matriculesEnseignants,
        private readonly array $codesDisciplines,
    ) {}

    public function array(): array
    {
        $rows = [];
        $rowCount = max(
            1,
            count($this->matriculesEnseignants),
            count($this->codesDisciplines),
            count(EmploiDuTemps::HEURES_DEBUT),
            count(EmploiDuTemps::HEURES_FIN),
        );

        for ($index = 0; $index < $rowCount; $index++) {
            $rows[] = [
                $this->matriculesEnseignants[$index] ?? null,
                $this->codesDisciplines[$index] ?? null,
                EmploiDuTemps::HEURES_DEBUT[$index] ?? null,
                EmploiDuTemps::HEURES_FIN[$index] ?? null,
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['matricules_enseignants', 'codes_disciplines', 'heures_debut', 'heures_fin'];
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
                $ranges = [
                    'MatriculesEnseignants' => 'A',
                    'CodesDisciplines' => 'B',
                    'HeuresDebut' => 'C',
                    'HeuresFin' => 'D',
                ];
                $counts = [
                    'MatriculesEnseignants' => count($this->matriculesEnseignants),
                    'CodesDisciplines' => count($this->codesDisciplines),
                    'HeuresDebut' => count(EmploiDuTemps::HEURES_DEBUT),
                    'HeuresFin' => count(EmploiDuTemps::HEURES_FIN),
                ];

                foreach ($ranges as $name => $column) {
                    $spreadsheet->addNamedRange(new NamedRange(
                        $name,
                        $sheet,
                        '$' . $column . '$2:$' . $column . '$' . max(2, $counts[$name] + 1),
                    ));
                }

                $sheet->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
            },
        ];
    }
}
