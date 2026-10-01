<?php

namespace App\Services;

use App\Models\Enseignant;
use App\Models\Parametre;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Plage de présence attendue d'un membre du personnel pour un jour donné
 * (§4.2 — Assiduité). Les enseignants sont attendus selon leur emploi du
 * temps ; le personnel administratif (section « Administration ») suit un
 * horaire fixe configurable, indépendant de tout emploi du temps : par défaut
 * du lundi au vendredi, de 07h30 à 15h30.
 */
class HoraireAttendu
{
    public const SECTION_ADMINISTRATIVE = 'administration';

    public const CLE_JOURS = 'admin_jours_travail';

    public const CLE_HEURE_DEBUT = 'admin_heure_debut';

    public const CLE_HEURE_FIN = 'admin_heure_fin';

    /** Jours ISO (1 = lundi ... 7 = dimanche). */
    public const DEFAUT_JOURS = [1, 2, 3, 4, 5];

    public const DEFAUT_HEURE_DEBUT = '07:30';

    public const DEFAUT_HEURE_FIN = '15:30';

    private ?array $parametres = null;

    /**
     * Comparaison insensible à la casse : `enseignants.section` porte des
     * variantes de saisie ("Administration" / "administration"), voir
     * AccessibleEnseignants.
     */
    public function estAdministratif(Enseignant $enseignant): bool
    {
        return mb_strtolower(trim((string) $enseignant->section)) === self::SECTION_ADMINISTRATIVE;
    }

    /** @return array{jours: int[], heure_debut: string, heure_fin: string} */
    public function parametresAdministratifs(): array
    {
        return $this->parametres ??= [
            'jours' => array_map('intval', array_filter(
                explode(',', Parametre::get(self::CLE_JOURS, implode(',', self::DEFAUT_JOURS))),
                fn (string $jour) => $jour !== '',
            )),
            'heure_debut' => Parametre::get(self::CLE_HEURE_DEBUT, self::DEFAUT_HEURE_DEBUT),
            'heure_fin' => Parametre::get(self::CLE_HEURE_FIN, self::DEFAUT_HEURE_FIN),
        ];
    }

    /** @param int[] $jours */
    public function definirParametresAdministratifs(array $jours, string $heureDebut, string $heureFin): void
    {
        $jours = array_values(array_unique(array_map('intval', $jours)));
        sort($jours);

        Parametre::set(self::CLE_JOURS, implode(',', $jours));
        Parametre::set(self::CLE_HEURE_DEBUT, $heureDebut);
        Parametre::set(self::CLE_HEURE_FIN, $heureFin);

        $this->parametres = null;
    }

    /**
     * Plage attendue ce jour-là, ou null si la personne n'est pas attendue.
     * `minutes` = durée de présence prévue (journée de travail pour un
     * administratif, somme des cours pour un enseignant).
     *
     * @param  Collection|null  $emplois  emploi du temps déjà chargé de l'enseignant (évite une requête)
     * @return array{heure_debut: string, heure_fin: string, minutes: int}|null
     */
    public function plage(Enseignant $enseignant, Carbon $date, ?Collection $emplois = null): ?array
    {
        if ($this->estAdministratif($enseignant)) {
            $parametres = $this->parametresAdministratifs();

            if (! in_array($date->isoWeekday(), $parametres['jours'], true)) {
                return null;
            }

            return [
                'heure_debut' => $parametres['heure_debut'],
                'heure_fin' => $parametres['heure_fin'],
                'minutes' => (int) Carbon::parse($parametres['heure_debut'])->diffInMinutes(Carbon::parse($parametres['heure_fin'])),
            ];
        }

        $cours = ($emplois ?? $enseignant->emploiDuTemps)->where('jour', $date->isoWeekday());

        if ($cours->isEmpty()) {
            return null;
        }

        return [
            'heure_debut' => $cours->min('heure_debut'),
            'heure_fin' => $cours->max('heure_fin'),
            'minutes' => (int) $cours->sum(fn ($item) => Carbon::parse($item->heure_debut)->diffInMinutes(Carbon::parse($item->heure_fin))),
        ];
    }

    public function estAttendu(Enseignant $enseignant, Carbon $date, ?Collection $emplois = null): bool
    {
        return $this->plage($enseignant, $date, $emplois) !== null;
    }
}
