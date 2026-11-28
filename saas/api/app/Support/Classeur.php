<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Petits services communs aux imports de classeurs.
 *
 * `Excel::toArray()` rend les onglets dans l'ordre mais sans leur nom, alors
 * que trois imports s'en servent comme d'une donnée : le nom d'onglet porte le
 * code de la spécialité pour le référentiel APC, celui du module pour les
 * notes, et situe les erreurs pour les emplois du temps. La lecture des noms
 * existait en trois copies privées.
 */
class Classeur
{
    /** Longueur maximale d'un nom d'onglet, imposée par le format. */
    public const LONGUEUR_ONGLET = 31;

    /**
     * Noms des onglets d'un fichier, dans l'ordre. Tableau vide si le format
     * ne les expose pas (CSV) ou si le fichier est illisible — l'appelant
     * retombe alors sur « Feuille 1 », « Feuille 2 »…
     *
     * @return array<int, string>
     */
    public static function nomsOnglets(string $chemin): array
    {
        try {
            $lecteur = IOFactory::createReaderForFile($chemin);

            return method_exists($lecteur, 'listWorksheetNames') ? $lecteur->listWorksheetNames($chemin) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Un nom d'onglet acceptable et distinct des précédents.
     *
     * Excel refuse `\ / ? * [ ] :`, tronque au-delà de 31 caractères et
     * n'accepte pas deux onglets homonymes — un classeur produit sans ces
     * trois précautions est rejeté à l'ouverture.
     *
     * @param  array<int, string>  $dejaUtilises  modifié : le nom retenu y est ajouté
     */
    public static function nomOngletUnique(string $souhaite, array &$dejaUtilises, string $defaut = 'Feuille'): string
    {
        $base = trim(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $souhaite) ?? '') ?: $defaut;
        $nom = mb_substr($base, 0, self::LONGUEUR_ONGLET);
        $suffixe = 2;

        while (in_array(mb_strtolower($nom), array_map('mb_strtolower', $dejaUtilises), true)) {
            $fin = ' ('.$suffixe++.')';
            $nom = mb_substr($base, 0, self::LONGUEUR_ONGLET - mb_strlen($fin)).$fin;
        }

        $dejaUtilises[] = $nom;

        return $nom;
    }

    /**
     * « 14/03/2008 », « 2008-03-14 » ou le numéro de série d'Excel -> « 2008-03-14 ».
     *
     * Le numéro de série est le cas réel : une colonne formatée en date dans
     * le classeur arrive en nombre (39521), et l'interpréter comme une chaîne
     * donnerait une naissance en l'an 39521. Les jours et les mois se lisent
     * à l'européenne — « 03/04/2008 » est le 3 avril, pas le 4 mars.
     *
     * Rend `false` — et non `null` — quand la valeur est présente mais
     * illisible : l'appelant doit pouvoir distinguer « pas de date » de
     * « date fausse », et signaler la seconde.
     */
    public static function date(mixed $valeur): string|false|null
    {
        if ($valeur === null || (is_string($valeur) && trim($valeur) === '')) {
            return null;
        }

        if (is_numeric($valeur)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $valeur)->format('Y-m-d');
            } catch (\Throwable) {
                return false;
            }
        }

        $texte = trim((string) $valeur);

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y'] as $format) {
            $date = \DateTime::createFromFormat('!'.$format, $texte);
            if ($date !== false && $date->format($format) === $texte) {
                return $date->format('Y-m-d');
            }
        }

        return false;
    }
}
