<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Bulletin;
use App\Services\AnneeScolaire;
use App\Services\BaremeApc;
use Illuminate\Http\Request;

/**
 * Lecture de la période (`1`, `2`, `3` ou `annuel`) et de l'année d'une
 * requête de conseil ou d'impression (§bulletins APC).
 *
 * Partagé entre le conseil et l'impression : deux copies de la liste des
 * périodes permises finiraient par ne plus accepter les mêmes.
 */
trait LitPeriodeConseil
{
    /** @return array{0: string, 1: string} [année scolaire, période] */
    protected function periodeEtAnnee(Request $request): array
    {
        $trimestres = implode(',', range(1, BaremeApc::nombreTrimestres()));

        $request->validate([
            // Pas de règle `string` : un corps JSON envoie `"periode": 1`.
            'periode' => ['required', 'in:'.$trimestres.','.Bulletin::PERIODE_ANNUELLE],
            'annee' => ['nullable', 'string', 'max:'.AnneeScolaire::LONGUEUR_MAX],
        ]);

        return [
            AnneeScolaire::normalise($request->string('annee')->toString()) ?? AnneeScolaire::courante(),
            $request->string('periode')->toString(),
        ];
    }
}
