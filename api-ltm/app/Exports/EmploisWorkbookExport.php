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

    public function __construct(
        private readonly array $classes,
        private readonly array $nomsEnseignants,
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

        $sheets[] = new EmploisListsSheetExport($this->nomsEnseignants, $this->codesDisciplines);

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
                $this->addListValidation($sheet, 'E', '=HeuresDebut');
                $this->addListValidation($sheet, 'F', '=HeuresFin');
                $this->addListValidation($sheet, 'H', '=TypesCours');
            },
        ];
    }

    private function addListValidation(Worksheet $sheet, string $column, string $formula): void
    {
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowInputMessage(false);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Valeur non autorisée');
        $validation->setError('Sélectionnez une valeur proposée dans la liste.');
        $validation->setFormula1($formula);

        $sheet->setDataValidation($column . '2:' . $column . self::MAX_INPUT_ROW, $validation);
    }
}

class EmploisListsSheetExport implements Export, FromArray, WithEvents, WithHeadings, WithTitle
{
    public function __construct(
        private readonly array $nomsEnseignants,
        private readonly array $codesDisciplines,
    ) {}

    public function array(): array
    {
        $rows = [];
        $rowCount = max(
            1,
            count($this->nomsEnseignants),
            count($this->codesDisciplines),
            count(EmploiDuTemps::HEURES_DEBUT),
            count(EmploiDuTemps::HEURES_FIN),
            count(EmploisWorkbookExport::TYPES_COURS),
        );

        for ($index = 0; $index < $rowCount; $index++) {
            $rows[] = [
                $this->nomsEnseignants[$index] ?? null,
                $this->codesDisciplines[$index] ?? null,
                EmploiDuTemps::HEURES_DEBUT[$index] ?? null,
                EmploiDuTemps::HEURES_FIN[$index] ?? null,
                EmploisWorkbookExport::TYPES_COURS[$index] ?? null,
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['noms_enseignants', 'codes_disciplines', 'heures_debut', 'heures_fin', 'types_cours'];
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
                    'NomsEnseignants' => 'A',
                    'CodesDisciplines' => 'B',
                    'HeuresDebut' => 'C',
                    'HeuresFin' => 'D',
                    'TypesCours' => 'E',
                ];
                $counts = [
                    'NomsEnseignants' => count($this->nomsEnseignants),
                    'CodesDisciplines' => count($this->codesDisciplines),
                    'HeuresDebut' => count(EmploiDuTemps::HEURES_DEBUT),
                    'HeuresFin' => count(EmploiDuTemps::HEURES_FIN),
                    'TypesCours' => count(EmploisWorkbookExport::TYPES_COURS),
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
