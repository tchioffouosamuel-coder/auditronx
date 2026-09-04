<?php

namespace App\Services;

use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Parametre;
use App\Models\Presence;
use Carbon\Carbon;

/**
 * Calcule le retard d'un enseignant à partir de son premier cours du jour
 * (emploi du temps) comparé à l'heure d'arrivée pointée, avec un seuil de
 * tolérance configurable (§4.2 — Retards & bilans).
 */
class RetardCalculator
{
    public const CLE_TOLERANCE = 'tolerance_retard_minutes';

    public const DEFAUT_TOLERANCE = 10;

    public function toleranceMinutes(): int
    {
        return (int) Parametre::get(self::CLE_TOLERANCE, (string) self::DEFAUT_TOLERANCE);
    }

    public function definirTolerance(int $minutes): void
    {
        Parametre::set(self::CLE_TOLERANCE, (string) $minutes);
    }

    /** Premier cours prévu pour cet enseignant à la date donnée (selon le jour de la semaine). */
    public function premierCoursDuJour(Enseignant $enseignant, Carbon $date): ?EmploiDuTemps
    {
        return $enseignant->emploiDuTemps()
            ->where('jour', $date->isoWeekday())
            ->orderBy('heure_debut')
            ->first();
    }

    /**
     * Retourne le nombre de minutes de retard (0 si à l'heure ou en avance),
     * ou null si aucun cours n'est prévu ou si l'enseignant n'a pas pointé.
     */
    public function minutesDeRetard(Enseignant $enseignant, Presence $presence): ?int
    {
        if (! $presence->heure_arrivee) {
            return null;
        }

        $cours = $this->premierCoursDuJour($enseignant, $presence->date);

        if (! $cours) {
            return null;
        }

        $heureAttendue = Carbon::parse($presence->date->toDateString().' '.$cours->heure_debut)
            ->addMinutes($this->toleranceMinutes());

        $retard = $presence->heure_arrivee->diffInMinutes($heureAttendue, false);

        return $retard < 0 ? (int) abs($retard) : 0;
    }

    public function estEnRetard(Enseignant $enseignant, Presence $presence): bool
    {
        return ($this->minutesDeRetard($enseignant, $presence) ?? 0) > 0;
    }
}
