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
 * tolérance configurable (§4.2 — Retards & bilans). Le personnel administratif
 * est comparé au début de sa journée de travail (voir HoraireAttendu).
 */
class RetardCalculator
{
    public const CLE_TOLERANCE = 'tolerance_retard_minutes';

    public const DEFAUT_TOLERANCE = 10;

    public function __construct(private HoraireAttendu $horaires) {}

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
     * Heure à laquelle la personne est attendue ce jour-là : début de journée
     * fixe pour le personnel administratif, premier cours pour un enseignant.
     * Null si elle n'est pas attendue.
     */
    public function heureDebutAttendue(Enseignant $enseignant, Carbon $date): ?string
    {
        if ($this->horaires->estAdministratif($enseignant)) {
            return $this->horaires->plage($enseignant, $date)['heure_debut'] ?? null;
        }

        return $this->premierCoursDuJour($enseignant, $date)?->heure_debut;
    }

    /**
     * Retourne le nombre de minutes de retard (0 si à l'heure ou en avance),
     * ou null si la personne n'est pas attendue ce jour-là ou n'a pas pointé.
     */
    public function minutesDeRetard(Enseignant $enseignant, Presence $presence): ?int
    {
        if (! $presence->heure_arrivee) {
            return null;
        }

        $heureDebut = $this->heureDebutAttendue($enseignant, $presence->date);

        if (! $heureDebut) {
            return null;
        }

        $heureAttendue = Carbon::parse($presence->date->toDateString().' '.$heureDebut)
            ->addMinutes($this->toleranceMinutes());

        $retard = $presence->heure_arrivee->diffInMinutes($heureAttendue, false);

        return $retard < 0 ? (int) abs($retard) : 0;
    }

    public function estEnRetard(Enseignant $enseignant, Presence $presence): bool
    {
        return ($this->minutesDeRetard($enseignant, $presence) ?? 0) > 0;
    }
}
