<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Marqueur pour Excel::toArray() : traite la première ligne comme en-têtes
 * et slugifie chaque en-tête en clé de tableau associatif (ex. "Nom" -> "nom")
 * — la validation/mise à jour métier est faite par l'appelant (SpreadsheetController).
 */
class ArrayImport implements Import, WithHeadingRow
{
}
