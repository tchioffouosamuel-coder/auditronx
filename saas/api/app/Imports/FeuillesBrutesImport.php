<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;

/**
 * Marqueur pour Excel::toArray() : rend chaque onglet en lignes brutes,
 * colonnes indexées par position.
 *
 * À l'opposé d'ArrayImport, qui slugifie la première ligne en clés : le
 * classeur du référentiel APC ne nomme pas des champs en en-tête, il nomme
 * des **blocs**, et une même colonne y porte des modules et non une valeur
 * d'attribut. La position est donc l'information utile.
 */
class FeuillesBrutesImport implements Import
{
}
