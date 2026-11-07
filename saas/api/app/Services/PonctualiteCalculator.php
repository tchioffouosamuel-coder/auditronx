<?php

namespace App\Services;

use App\Models\Parametre;

/**
 * Taux de ponctualité d'un enseignant sur une période (jours prévus vs jours
 * ponctuels, voir BilanIndividuel/RetardsController) : un jour est ponctuel
 * s'il était attendu, non à venir, et sans retard (voir RetardCalculator).
 *
 * Le taux seul ne départage pas deux enseignants ayant le même nombre de
 * jours ponctuels mais des minutes de retard cumulées différentes sur leurs
 * jours non ponctuels : le `score_ponctualite` applique un coefficient
 * régressif sur ces minutes — chaque minute supplémentaire pèse un peu moins
 * que la précédente — pour obtenir un ordre de classement strict.
 */
class PonctualiteCalculator
{
    public const CLE_COEFFICIENT = 'ponctualite_coefficient_minutes';

    public const DEFAUT_COEFFICIENT = 30;

    public function coefficientMinutes(): int
    {
        return (int) Parametre::get(self::CLE_COEFFICIENT, (string) self::DEFAUT_COEFFICIENT);
    }

    public function definirCoefficientMinutes(int $minutes): void
    {
        Parametre::set(self::CLE_COEFFICIENT, (string) max(1, $minutes));
    }

    /** @return array{jours_prevus: int, jours_ponctuels: int, taux_ponctualite: float, total_retard_minutes: int, coefficient_retard: float, score_ponctualite: float} */
    public function calculer(int $joursPrevus, int $joursPonctuels, int $totalRetardMinutes): array
    {
        $ratio = $joursPrevus > 0 ? $joursPonctuels / $joursPrevus : 0.0;
        $coefficientRetard = 1 / (1 + $totalRetardMinutes / $this->coefficientMinutes());

        return [
            'jours_prevus' => $joursPrevus,
            'jours_ponctuels' => $joursPonctuels,
            'taux_ponctualite' => round($ratio * 100, 1),
            'total_retard_minutes' => $totalRetardMinutes,
            'coefficient_retard' => round($coefficientRetard, 4),
            'score_ponctualite' => round($ratio * $coefficientRetard * 100, 2),
        ];
    }
}
