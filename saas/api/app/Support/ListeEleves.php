<?php

namespace App\Support;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Le classeur « liste des élèves », au format de la liste que l'administration
 * reçoit et diffuse : un onglet par classe, un titre fusionné en ligne 1
 * (« LISTE DES ÉLÈVES — 2nde F4-BA — 2026-2027 »), les en-têtes en ligne 3,
 * un élève par ligne à partir de la 4.
 *
 * La classe et l'année ne sont pas des colonnes : c'est le titre qui les
 * porte, une fois pour tout l'onglet. La lecture les y reprend.
 *
 * La lecture est tolérante sur la forme — accents, casse, ligne des en-têtes
 * décalée, colonnes dans un autre ordre — parce que la liste passe de main en
 * main sous Excel avant d'arriver ici. Elle reconnaît les colonnes à leur
 * intitulé, non à leur position.
 */
class ListeEleves
{
    public const TITRE = 'LISTE DES ÉLÈVES';

    /** Le titre d'un onglet d'élèves non encore affectés. */
    public const SANS_CLASSE = 'SANS CLASSE';

    /** Colonne => intitulé imprimé, dans l'ordre du modèle. */
    public const COLONNES = [
        'numero' => 'N°',
        'nom' => 'NOMS ET PRÉNOMS',
        'matricule_national' => 'MATRICULE NATIONAL',
        'matricule' => 'MATRICULE INTERNE',
        'date_naissance' => 'DATE DE NAISSANCE',
        'lieu_naissance' => 'LIEU DE NAISSANCE',
        'telephone' => 'TÉLÉPHONE',
        'sexe' => 'SEXE',
        'statut' => 'STATUT',
    ];

    /**
     * Variantes d'intitulé acceptées à la lecture, normalisées (voir cle()).
     * Pas de « NOM » seul : c'était l'en-tête de l'ancien modèle à plat
     * (matricule, nom, code_classe…), qui doit être refusé et non lu sans sa
     * classe.
     */
    private const SYNONYMES = [
        'N' => 'numero', 'NO' => 'numero', 'NUMERO' => 'numero',
        'NOMS ET PRENOMS' => 'nom', 'NOM ET PRENOMS' => 'nom', 'NOMS ET PRENOM' => 'nom',
        'NOM ET PRENOM' => 'nom',
        'MATRICULE NATIONAL' => 'matricule_national',
        'MATRICULE INTERNE' => 'matricule', 'MATRICULE' => 'matricule',
        'DATE DE NAISSANCE' => 'date_naissance',
        'LIEU DE NAISSANCE' => 'lieu_naissance',
        'TELEPHONE' => 'telephone', 'TEL' => 'telephone', 'CONTACT' => 'telephone',
        'SEXE' => 'sexe',
        'STATUT' => 'statut',
    ];

    /** Colonnes du modèle saisies en texte : un matricule « 0123 » garde son zéro. */
    private const TEXTE = ['matricule_national', 'matricule', 'telephone'];

    /** Largeurs du modèle officiel. */
    private const LARGEURS = [7, 36, 24, 18, 18, 24, 19, 9, 10];

    private const LIGNE_TITRE = 1;

    private const LIGNE_ENTETES = 3;

    /** Jusqu'où chercher la ligne des en-têtes dans un onglet retouché. */
    private const RECHERCHE_ENTETES = 10;

    /** « LISTE DES ÉLÈVES — 2nde F4-BA — 2026-2027 ». */
    public static function titre(string $classe, string $annee): string
    {
        return self::TITRE.' — '.$classe.' — '.$annee;
    }

    /**
     * Écrit le classeur dans un fichier temporaire et en rend le chemin.
     *
     * @param  array<int, array{onglet: string, classe: string, annee: string, lignes: array<int, array<string, mixed>>}>  $feuilles
     *                                                                                                                         chaque ligne indexée par les clés de COLONNES (`numero` est recalculé)
     */
    public static function ecrire(array $feuilles): string
    {
        $classeur = new Spreadsheet;
        $classeur->removeSheetByIndex(0);
        $onglets = [];

        foreach ($feuilles as $feuille) {
            $ws = $classeur->createSheet();
            $ws->setTitle(Classeur::nomOngletUnique($feuille['onglet'], $onglets, 'Eleves'));
            self::remplir($ws, $feuille);
        }

        $classeur->setActiveSheetIndex(0);

        $chemin = tempnam(sys_get_temp_dir(), 'eleves').'.xlsx';
        (new Xlsx($classeur))->save($chemin);
        $classeur->disconnectWorksheets();

        return $chemin;
    }

    /** @param array{classe: string, annee: string, lignes: array<int, array<string, mixed>>} $feuille */
    private static function remplir(Worksheet $ws, array $feuille): void
    {
        $colonnes = array_keys(self::COLONNES);
        $derniere = self::lettre(count($colonnes));

        $ws->setCellValue('A'.self::LIGNE_TITRE, self::titre($feuille['classe'], $feuille['annee']));
        $ws->mergeCells('A'.self::LIGNE_TITRE.':'.$derniere.self::LIGNE_TITRE);
        $ws->getStyle('A'.self::LIGNE_TITRE)->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B486B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension(self::LIGNE_TITRE)->setRowHeight(24);

        foreach (array_values(self::COLONNES) as $i => $intitule) {
            $ws->setCellValue(self::lettre($i + 1).self::LIGNE_ENTETES, $intitule);
            $ws->getColumnDimension(self::lettre($i + 1))->setWidth(self::LARGEURS[$i]);
        }
        $ws->getStyle('A'.self::LIGNE_ENTETES.':'.$derniere.self::LIGNE_ENTETES)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '177E89']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);

        $ligne = self::LIGNE_ENTETES;
        foreach (array_values($feuille['lignes']) as $n => $valeurs) {
            $ligne++;
            $valeurs['numero'] = $n + 1;

            foreach ($colonnes as $i => $cle) {
                $valeur = $valeurs[$cle] ?? null;
                if ($valeur === null || $valeur === '') {
                    continue;
                }

                $cellule = self::lettre($i + 1).$ligne;
                in_array($cle, self::TEXTE, true)
                    ? $ws->setCellValueExplicit($cellule, (string) $valeur, DataType::TYPE_STRING)
                    : $ws->setCellValue($cellule, $valeur);
            }
        }

        // Le modèle vierge garde une plage prête à la saisie : bordures et
        // format texte des matricules couvrent quelques lignes de plus.
        $fin = max($ligne, self::LIGNE_ENTETES + 60);
        $corps = 'A'.(self::LIGNE_ENTETES + 1).':'.$derniere.$fin;
        $ws->getStyle($corps)->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'CAD4DA']]],
        ]);
        foreach (self::TEXTE as $cle) {
            $lettre = self::lettre(array_search($cle, $colonnes, true) + 1);
            $ws->getStyle($lettre.(self::LIGNE_ENTETES + 1).':'.$lettre.$fin)
                ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        $ws->freezePane('A'.(self::LIGNE_ENTETES + 1));
        $ws->setAutoFilter('A'.self::LIGNE_ENTETES.':'.$derniere.$ligne);
        $ws->setSelectedCell('A'.(self::LIGNE_ENTETES + 1));
    }

    /**
     * Lit les onglets au format de la liste. Un onglet sans ligne d'en-têtes
     * reconnaissable (une notice, un récapitulatif) est passé sous silence ;
     * aucun onglet reconnu donne un tableau vide.
     *
     * @return array<int, array{
     *     onglet: string,
     *     index: int,
     *     ligne_titre: int,
     *     classe: ?string,
     *     annee: ?string,
     *     lignes: array<int, array<string, mixed>>
     * }> lignes indexées par leur numéro dans la feuille
     */
    public static function lire(string $chemin): array
    {
        $lecteur = IOFactory::createReaderForFile($chemin);
        $lecteur->setReadDataOnly(true);
        $classeur = $lecteur->load($chemin);
        $feuilles = [];

        foreach ($classeur->getAllSheets() as $index => $ws) {
            $cellules = $ws->toArray(null, true, false, false);
            $entetes = self::chercherEntetes($cellules);

            if ($entetes === null) {
                continue;
            }

            [$ligneEntetes, $positions] = $entetes;
            [$ligneTitre, $classe, $annee] = self::lireTitre($cellules, $ligneEntetes);

            $lignes = [];
            for ($i = $ligneEntetes + 1; $i < count($cellules); $i++) {
                $valeurs = [];
                foreach ($positions as $cle => $position) {
                    $valeurs[$cle] = $cellules[$i][$position] ?? null;
                }
                unset($valeurs['numero']);

                if (array_filter($valeurs, fn ($v) => $v !== null && trim((string) $v) !== '') === []) {
                    continue;
                }

                $lignes[$i + 1] = $valeurs;
            }

            $feuilles[] = [
                'onglet' => $ws->getTitle(),
                'index' => $index,
                'ligne_titre' => $ligneTitre,
                'classe' => $classe,
                'annee' => $annee,
                'lignes' => $lignes,
            ];
        }

        $classeur->disconnectWorksheets();

        return $feuilles;
    }

    /**
     * La ligne des en-têtes : la première qui nomme au moins le nom et le
     * matricule interne.
     *
     * @param  array<int, array<int, mixed>>  $cellules
     * @return array{0: int, 1: array<string, int>}|null indice de la ligne, position de chaque colonne
     */
    private static function chercherEntetes(array $cellules): ?array
    {
        foreach (array_slice($cellules, 0, self::RECHERCHE_ENTETES, true) as $i => $ligne) {
            $positions = [];
            foreach ($ligne as $position => $valeur) {
                $cle = self::SYNONYMES[self::cle($valeur)] ?? null;
                if ($cle !== null && ! isset($positions[$cle])) {
                    $positions[$cle] = $position;
                }
            }

            if (isset($positions['nom'], $positions['matricule'])) {
                return [$i, $positions];
            }
        }

        return null;
    }

    /**
     * Classe et année lues dans le titre au-dessus des en-têtes. Le séparateur
     * est le tiret entouré d'espaces — « F4-BA » en porte un sans espace, qui
     * appartient au nom de la classe.
     *
     * @param  array<int, array<int, mixed>>  $cellules
     * @return array{0: int, 1: ?string, 2: ?string} ligne du titre (1 à défaut), classe, année
     */
    private static function lireTitre(array $cellules, int $ligneEntetes): array
    {
        for ($i = 0; $i < $ligneEntetes; $i++) {
            foreach ($cellules[$i] as $valeur) {
                $texte = trim(preg_replace('/\s+/u', ' ', (string) $valeur) ?? '');
                if ($texte === '') {
                    continue;
                }

                $morceaux = preg_split('/\s+[—–-]\s+/u', $texte) ?: [];
                if (count($morceaux) < 2) {
                    continue;
                }

                array_shift($morceaux);
                $annee = null;
                if (preg_match('/^\d{4}\s*[\/\-–—]\s*\d{2,4}$/u', (string) end($morceaux)) === 1) {
                    $annee = array_pop($morceaux);
                }

                $classe = trim(implode(' - ', $morceaux));

                return [$i + 1, $classe === '' ? null : $classe, $annee];
            }
        }

        return [self::LIGNE_TITRE, null, null];
    }

    /** « Noms et Prénoms » -> « NOMS ET PRENOMS » : sans accent, ponctuation ni casse. */
    private static function cle(mixed $valeur): string
    {
        return trim(preg_replace('/[^A-Z0-9]+/', ' ', strtoupper(Str::ascii((string) $valeur))) ?? '');
    }

    private static function lettre(int $colonne): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colonne);
    }
}
