<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Export XLSX générique : une simple liste de lignes (tableaux indexés) avec
 * un en-tête donné — réutilisé pour le template vierge (lignes vides) comme
 * pour l'export des données réelles (§4.2, gestion des entités principales).
 */
class ArrayExport implements FromArray, WithHeadings
{
    public function __construct(
        private readonly array $headings,
        private readonly array $rows = [],
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
