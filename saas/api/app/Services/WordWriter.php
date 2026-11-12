<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Générateur de documents Word (.docx) — titres, paragraphes et tableaux.
 *
 * Les journaux et bilans existaient déjà en PDF (DomPDF), mais un PDF ne se
 * retouche pas : l'administration reprend ces documents pour y ajouter des
 * mentions avant diffusion ou archivage, d'où la demande d'un format Word.
 *
 * L'écriture se fait directement en WordprocessingML plutôt que via PhpWord :
 * la bibliothèque n'est pas installée côté API (et l'installer imposerait une
 * mise à jour du vendor sur chaque déploiement) alors que le sous-ensemble
 * utile ici — titres, tableaux bordés, orientation paysage — tient en quelques
 * balises. Le fichier produit est un vrai .docx (OOXML zippé), pas un HTML
 * déguisé : il s'ouvre dans Word, LibreOffice et Google Docs.
 */
class WordWriter
{
    /**
     * Tailles de police, en demi-points (22 = 11 pt). Les documents se lisent
     * imprimés, souvent photocopiés : le 9 pt d'origine passait mal.
     */
    public const TAILLE_CORPS = 22;

    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const NS_WP = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';

    private const NS_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    private const NS_PIC = 'http://schemas.openxmlformats.org/drawingml/2006/picture';

    /** Fragments WordprocessingML du corps, dans l'ordre d'insertion. */
    private array $corps = [];

    /** Logo embarqué dans le document, le cas échéant : extension et octets. */
    private ?array $media = null;

    public function __construct(private readonly string $orientation = 'portrait') {}

    public static function paysage(): self
    {
        return new self('paysage');
    }

    /**
     * En-tête officiel de l'établissement : la tutelle française à gauche, son
     * équivalent anglais à droite, sur un tableau sans bordures.
     *
     * Le bandeau se place avant le titre et n'imprime rien s'il n'est pas
     * renseigné, comme dans les PDF (cf. `pdf.partials.entete`).
     *
     * @param  array{gauche: string[], droite: string[], contact: string}  $colonnes
     */
    public function enteteEtablissement(array $colonnes): self
    {
        $logo = $colonnes['logo'] ?? null;

        if ($colonnes['gauche'] === [] && $colonnes['droite'] === [] && $logo === null) {
            return $this;
        }

        $celluleLogo = $logo !== null ? $this->celluleLogo($logo) : '';

        $cellule = fn (array $lignes, int $largeur) => '<w:tc><w:tcPr><w:tcW w:w="'.$largeur.'" w:type="pct"/></w:tcPr>'
            .($lignes === []
                ? '<w:p/>'
                : implode('', array_map(
                    fn (string $ligne) => $this->xmlParagraphe($ligne, [
                        'taille' => 20,
                        'centre' => true,
                        'apres' => 0,
                    ]),
                    $lignes,
                )))
            .'</w:tc>';

        // Avec logo, trois colonnes : tutelle française, logo, tutelle
        // anglaise — la disposition d'un en-tête administratif camerounais,
        // identique à celle des PDF (`pdf.partials.entete`).
        $grille = $celluleLogo !== ''
            ? '<w:gridCol w:w="3600"/><w:gridCol w:w="1800"/><w:gridCol w:w="3600"/>'
            : '<w:gridCol w:w="4500"/><w:gridCol w:w="4500"/>';
        $largeur = $celluleLogo !== '' ? 2000 : 2500;

        $this->corps[] = '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/>'
            .'<w:tblLayout w:type="fixed"/></w:tblPr>'
            .'<w:tblGrid>'.$grille.'</w:tblGrid>'
            .'<w:tr>'.$cellule($colonnes['gauche'], $largeur).$celluleLogo.$cellule($colonnes['droite'], $largeur).'</w:tr>'
            .'</w:tbl>';

        if ($colonnes['contact'] !== '') {
            $this->paragraphe($colonnes['contact'], [
                'taille' => 18,
                'centre' => true,
                'couleur' => '555555',
            ]);
        } else {
            // Word fusionnerait le tableau suivant avec celui de l'en-tête.
            $this->corps[] = '<w:p/>';
        }

        return $this;
    }

    /**
     * Logo de l'établissement, centré dans la colonne du milieu de l'en-tête.
     *
     * L'image est embarquée dans le .docx (partie `word/media`) : un document
     * envoyé par courriel doit rester lisible hors du serveur.
     */
    private function celluleLogo(string $fichier): string
    {
        $binaire = @file_get_contents($fichier);

        if ($binaire === false) {
            return '';
        }

        $extension = mb_strtolower(pathinfo($fichier, PATHINFO_EXTENSION)) ?: 'png';
        $this->media = ['extension' => $extension, 'contenu' => $binaire];

        // Hauteur fixée à 1,2 cm, largeur déduite du rapport de l'image, en
        // EMU (914400 par pouce) : un logo étiré trahirait l'établissement.
        $hauteur = (int) round(1.2 / 2.54 * 914400);
        $dimensions = @getimagesizefromstring($binaire);
        $ratio = $dimensions && $dimensions[1] > 0 ? $dimensions[0] / $dimensions[1] : 1.0;
        $largeur = (int) round($hauteur * $ratio);

        $dessin = '<w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
            .'<wp:extent cx="'.$largeur.'" cy="'.$hauteur.'"/>'
            .'<wp:docPr id="1" name="Logo"/>'
            .'<a:graphic xmlns:a="'.self::NS_A.'"><a:graphicData uri="'.self::NS_PIC.'">'
            .'<pic:pic xmlns:pic="'.self::NS_PIC.'">'
            .'<pic:nvPicPr><pic:cNvPr id="1" name="Logo"/><pic:cNvPicPr/></pic:nvPicPr>'
            .'<pic:blipFill><a:blip r:embed="rId2"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$largeur.'" cy="'.$hauteur.'"/></a:xfrm>'
            .'<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            .'</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing>';

        return '<w:tc><w:tcPr><w:tcW w:w="1000" w:type="pct"/><w:vAlign w:val="center"/></w:tcPr>'
            .'<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="0"/></w:pPr><w:r>'.$dessin.'</w:r></w:p>'
            .'</w:tc>';
    }

    public function titre(string $texte): self
    {
        return $this->paragraphe($texte, ['taille' => 34, 'gras' => true, 'centre' => true, 'apres' => 60]);
    }

    public function sousTitre(string $texte): self
    {
        return $this->paragraphe($texte, ['taille' => 24, 'centre' => true, 'couleur' => '555555', 'apres' => 200]);
    }

    public function intertitre(string $texte): self
    {
        return $this->paragraphe($texte, ['taille' => 26, 'gras' => true, 'avant' => 240, 'apres' => 80]);
    }

    /**
     * @param  array{taille?: int, gras?: bool, centre?: bool, couleur?: string, avant?: int, apres?: int}  $options
     */
    public function paragraphe(string $texte, array $options = []): self
    {
        $this->corps[] = $this->xmlParagraphe($texte, $options);

        return $this;
    }

    /**
     * Tableau bordé à en-tête répété sur chaque page.
     *
     * @param  string[]  $entetes
     * @param  array<int, array<int, string|int|float|null>>  $lignes
     * @param  int[]|null  $largeurs  poids relatifs des colonnes (défaut : égales)
     */
    public function tableau(array $entetes, array $lignes, ?array $largeurs = null): self
    {
        $colonnes = max(count($entetes), 1);
        $largeurs = $largeurs && count($largeurs) === $colonnes
            ? $largeurs
            : array_fill(0, $colonnes, 1);
        $total = array_sum($largeurs) ?: $colonnes;

        // Les largeurs sont exprimées en cinquantièmes de pourcent (type="pct"),
        // la table occupant toujours la pleine largeur utile de la page.
        $grille = implode('', array_map(
            fn (int $poids) => '<w:gridCol w:w="'.(int) round($poids / $total * 9000).'"/>',
            $largeurs,
        ));

        $xml = '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblLayout w:type="fixed"/><w:tblBorders>';
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $bord) {
            $xml .= '<w:'.$bord.' w:val="single" w:sz="4" w:space="0" w:color="888888"/>';
        }
        $xml .= '</w:tblBorders></w:tblPr><w:tblGrid>'.$grille.'</w:tblGrid>';

        $xml .= $this->xmlLigne($entetes, $largeurs, $total, entete: true);
        foreach ($lignes as $ligne) {
            $xml .= $this->xmlLigne(array_values((array) $ligne), $largeurs, $total, entete: false);
        }

        $this->corps[] = $xml.'</w:tbl>';

        // Word fusionne deux tableaux consécutifs non séparés par un
        // paragraphe : le bilan mensuel en enchaîne un par section.
        $this->corps[] = '<w:p/>';

        return $this;
    }

    /** Réponse de téléchargement du document, fichier temporaire supprimé après envoi. */
    public function telecharger(string $nomFichier): BinaryFileResponse
    {
        $chemin = tempnam(sys_get_temp_dir(), 'docx');

        if ($chemin === false) {
            throw new RuntimeException('Impossible de créer le fichier temporaire du document Word.');
        }

        $this->ecrire($chemin);

        return response()
            ->download($chemin, $nomFichier, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])
            ->deleteFileAfterSend();
    }

    /** Écrit l'archive .docx à l'emplacement donné (exposé pour les tests). */
    public function ecrire(string $chemin): void
    {
        $zip = new ZipArchive;

        if ($zip->open($chemin, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Impossible d'écrire le document Word : {$chemin}.");
        }

        foreach ($this->parties() as $nom => $contenu) {
            $zip->addFromString($nom, $contenu);
        }

        $zip->close();
    }

    /** @return array<string, string> parties du paquet OOXML, indexées par chemin interne */
    private function parties(): array
    {
        $entete = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";

        // Une image dans un .docx, c'est trois ajouts : son type MIME, sa
        // relation depuis le document, et la partie binaire elle-même.
        $extension = $this->media['extension'] ?? null;
        $typeMedia = $extension === null
            ? ''
            : '<Default Extension="'.$extension.'" ContentType="image/'
                .($extension === 'jpg' ? 'jpeg' : $extension).'"/>';
        $relationMedia = $extension === null
            ? ''
            : '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"'
                .' Target="media/logo.'.$extension.'"/>';

        return [
            '[Content_Types].xml' => $entete
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
                .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
                .$typeMedia
                .'</Types>',

            '_rels/.rels' => $entete
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
                .'</Relationships>',

            'word/_rels/document.xml.rels' => $entete
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                .$relationMedia
                .'</Relationships>',

            'word/styles.xml' => $entete
                .'<w:styles xmlns:w="'.self::NS_W.'">'
                .'<w:docDefaults><w:rPrDefault><w:rPr>'
                .'<w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/><w:sz w:val="22"/>'
                .'</w:rPr></w:rPrDefault></w:docDefaults>'
                .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
                .'</w:styles>',

            'word/document.xml' => $entete
                .'<w:document xmlns:w="'.self::NS_W.'" xmlns:r="'.self::NS_R.'"'
                .' xmlns:wp="'.self::NS_WP.'" xmlns:a="'.self::NS_A.'" xmlns:pic="'.self::NS_PIC.'"><w:body>'
                .implode('', $this->corps)
                .$this->xmlSection()
                .'</w:body></w:document>',

            ...($this->media === null ? [] : [
                'word/media/logo.'.$this->media['extension'] => $this->media['contenu'],
            ]),
        ];
    }

    private function xmlSection(): string
    {
        // Dimensions A4 en twips (1/1440 de pouce) : 11906 x 16838.
        $page = $this->orientation === 'paysage'
            ? '<w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>'
            : '<w:pgSz w:w="11906" w:h="16838"/>';

        return '<w:sectPr>'.$page
            .'<w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="360" w:footer="360" w:gutter="0"/>'
            .'</w:sectPr>';
    }

    /**
     * @param  array<int, string|int|float|null>  $cellules
     * @param  int[]  $largeurs
     */
    private function xmlLigne(array $cellules, array $largeurs, int $total, bool $entete): string
    {
        // Un en-tête répété en haut de chaque page : ces documents dépassent
        // souvent la page et des colonnes anonymes y seraient illisibles.
        $xml = '<w:tr>'.($entete ? '<w:trPr><w:tblHeader/></w:trPr>' : '');

        foreach (array_values($largeurs) as $index => $poids) {
            $fond = $entete ? '<w:shd w:val="clear" w:color="auto" w:fill="E8EDF2"/>' : '';
            $xml .= '<w:tc><w:tcPr><w:tcW w:w="'.(int) round($poids / $total * 5000).'" w:type="pct"/>'
                .$fond.'<w:vAlign w:val="center"/></w:tcPr>'
                .$this->xmlParagraphe((string) ($cellules[$index] ?? ''), [
                    'gras' => $entete,
                    'centre' => $entete || $index > 0,
                    'avant' => 20,
                    'apres' => 20,
                ])
                .'</w:tc>';
        }

        return $xml.'</w:tr>';
    }

    /** @param array{taille?: int, gras?: bool, centre?: bool, couleur?: string, avant?: int, apres?: int} $options */
    private function xmlParagraphe(string $texte, array $options = []): string
    {
        $proprietes = '<w:spacing w:before="'.(int) ($options['avant'] ?? 0).'" w:after="'.(int) ($options['apres'] ?? 60).'"/>';

        if ($options['centre'] ?? false) {
            $proprietes .= '<w:jc w:val="center"/>';
        }

        $run = '';
        if (isset($options['taille'])) {
            $run .= '<w:sz w:val="'.(int) $options['taille'].'"/>';
        }
        if ($options['gras'] ?? false) {
            $run .= '<w:b/>';
        }
        if (isset($options['couleur'])) {
            $run .= '<w:color w:val="'.$options['couleur'].'"/>';
        }

        // Les retours à la ligne saisis deviennent de vrais sauts de ligne :
        // un \n brut dans <w:t> serait rendu comme une espace.
        $segments = explode("\n", $texte);
        $contenu = '';
        foreach ($segments as $index => $segment) {
            $contenu .= ($index > 0 ? '<w:br/>' : '')
                .'<w:t xml:space="preserve">'.$this->echapper($segment).'</w:t>';
        }

        return '<w:p><w:pPr>'.$proprietes.($run !== '' ? '<w:rPr>'.$run.'</w:rPr>' : '').'</w:pPr>'
            .'<w:r>'.($run !== '' ? '<w:rPr>'.$run.'</w:rPr>' : '').$contenu.'</w:r></w:p>';
    }

    private function echapper(string $texte): string
    {
        return htmlspecialchars($texte, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
