<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Année scolaire : « 2025/2026 ».
 *
 * La règle — l'année bascule au 1er septembre — était jusqu'ici écrite dans
 * une méthode privée de CoursEnseignantController. Les inscriptions, les notes
 * et les bulletins en ont désormais besoin aussi, et trois copies d'une même
 * règle de calendrier finissent toujours par diverger d'un mois.
 *
 * Le séparateur est la barre oblique, comme dans `programmes.annee_scolaire`
 * et dans les classeurs de progression déjà en circulation. Le bulletin
 * l'imprime avec un tiret (« Année Scolaire : 2025-2026 ») : c'est une
 * question de présentation, traitée à l'impression et non en base, pour ne pas
 * couper les nouvelles données des anciennes.
 */
class AnneeScolaire
{
    /** Mois à partir duquel une nouvelle année scolaire commence. */
    public const MOIS_DE_RENTREE = 9;

    public const LONGUEUR_MAX = 20;

    /** Année scolaire d'une date donnée. */
    public static function pour(Carbon $date): string
    {
        $debut = $date->month >= self::MOIS_DE_RENTREE ? $date->year : $date->year - 1;

        return $debut.'/'.($debut + 1);
    }

    /** Année scolaire en cours. */
    public static function courante(): string
    {
        return self::pour(Carbon::now());
    }

    /**
     * Normalise une saisie : « 2025-2026 », « 2025 2026 » ou « 2025/26 »
     * désignent la même année qu'un classeur écrira de trois façons. Une
     * valeur qu'on ne sait pas lire est rendue telle quelle, nettoyée — mieux
     * vaut une année bizarre mais visible qu'une année inventée.
     */
    public static function normalise(?string $valeur): ?string
    {
        $valeur = trim((string) $valeur);

        if ($valeur === '') {
            return null;
        }

        if (preg_match('/^(\d{4})\s*[\/\-–—\s]\s*(\d{2,4})$/u', $valeur, $trouve) !== 1) {
            return mb_substr($valeur, 0, self::LONGUEUR_MAX);
        }

        $debut = (int) $trouve[1];
        $fin = (int) $trouve[2];

        // « 2025/26 » : la fin est écrite sur deux chiffres.
        if ($fin < 100) {
            $fin += intdiv($debut, 100) * 100;
        }

        return $debut.'/'.$fin;
    }

    /**
     * Les années présentes dans une colonne, les plus récentes d'abord —
     * pour les listes déroulantes des écrans.
     *
     * @param  array<int, string>  $annees
     * @return array<int, string>
     */
    public static function trier(array $annees): array
    {
        $annees = array_values(array_unique(array_filter($annees)));
        rsort($annees);

        return $annees;
    }
}
