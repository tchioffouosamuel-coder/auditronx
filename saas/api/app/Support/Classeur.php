<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;

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
}
