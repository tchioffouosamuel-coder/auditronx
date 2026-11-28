<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Specialite;
use Illuminate\Support\Str;

/**
 * Lecture du classeur « organisation pédagogique » d'où sort le référentiel
 * APC : un onglet par spécialité, les modules rangés en quatre colonnes — une
 * par bloc du bulletin — et le volume horaire dans une cinquième, aligné sur
 * la ligne.
 *
 * Cette disposition n'est pas celle d'un tableau de données : la colonne ne
 * dit pas *quoi* (nom, code, crédit) mais *dans quel bloc*. C'est la mise en
 * page que les délégations diffusent, et la convertir à la main avant chaque
 * import reviendrait à ressaisir le document. Le lecteur s'y adapte donc,
 * plutôt que d'exiger un format intermédiaire.
 *
 * ## Ce que le lecteur refuse de deviner
 *
 * Les classeurs réels sont irréguliers : deux modules sur une même ligne (à
 * qui vont les heures ?), un intitulé répété, deux lignes TOTAL qui se
 * contredisent, des onglets entiers sans code de module. Rien de tout cela
 * n'est tranché en silence :
 *
 * - **deux blocs sur une ligne** → les deux modules sont importés, le volume
 *   horaire laissé vide sur les deux, et un avertissement nomme la ligne ;
 * - **code déjà vu dans l'onglet** → la seconde occurrence est rejetée, avec
 *   son numéro de ligne ;
 * - **module sans code** → un code est fabriqué à partir de l'intitulé et
 *   signalé comme tel, pour être renommé à l'écran ;
 * - **lignes TOTAL** → jamais importées ; elles servent à lire le niveau
 *   (« TOTAL SECONDE (h) ») et à recouper les heures.
 *
 * La lecture ne touche pas la base : [lire()] rend ce qu'il a compris,
 * [appliquer()] l'écrit. C'est ce qui permet à l'écran d'import de montrer le
 * résultat avant de l'enregistrer, et au test de porter sur le classeur réel
 * sans base de données.
 */
class ReferentielApc
{
    /** Onglet de recoupement des totaux, présent dans les classeurs diffusés. */
    private const ONGLET_CONTROLE = 'controle';

    /** Préfixe des codes fabriqués, par bloc. */
    private const PREFIXES_CODE = [
        Module::BLOC_COMPETENCES_PARTICULIERES => 'PART',
        Module::BLOC_COMPETENCES_GENERALES => 'GEN',
        Module::BLOC_ENSEIGNEMENTS_GENERAUX => 'EGEN',
        Module::BLOC_ENSEIGNEMENTS_COMPLEMENTAIRES => 'COMP',
    ];

    /** Longueur maximale d'un code fabriqué, suffixe de désambiguïsation exclu. */
    private const LONGUEUR_CODE_FABRIQUE = 24;

    /**
     * Ce qui, entre parenthèses en fin d'intitulé, est un code de module.
     *
     * Deux formes : celle du document officiel (`C10`), et celle des codes
     * que le lecteur fabrique lui-même (`EGEN-FRANCAIS`). La seconde n'est
     * pas un luxe : l'export réécrit le code entre parenthèses, et sans elle
     * un référentiel exporté puis réimporté se verrait refabriquer des codes
     * à partir d'intitulés qui contiennent déjà le leur — le classeur ne
     * reviendrait pas à son point de départ.
     *
     * « EGEN » avant « GEN » dans l'alternance, sinon « GEN » capturerait le
     * préfixe et laisserait un « E » orphelin devant le tiret.
     */
    private const MOTIF_CODE = '/^(.*?)\s*\(\s*(C\s*\d+|(?:PART|EGEN|GEN|COMP)-[A-Z0-9-]+)\s*\)$/iu';

    /**
     * Lit un classeur déjà chargé en mémoire.
     *
     * @param  array<int, array{nom: string, lignes: array<int, array<int, mixed>>}>  $feuilles
     * @param  string|null  $niveauParDefaut  retenu quand l'onglet ne porte pas de ligne TOTAL
     * @return array{specialites: array<int, array<string, mixed>>, erreurs: array<int, array<string, mixed>>, avertissements: array<int, array<string, mixed>>, controles: array<int, array<string, mixed>>}
     */
    public static function lire(array $feuilles, ?string $niveauParDefaut = null): array
    {
        $specialites = [];
        $erreurs = [];
        $avertissements = [];
        $controles = [];

        foreach ($feuilles as $feuille) {
            $nom = trim((string) ($feuille['nom'] ?? ''));

            if (self::normalise($nom) === self::ONGLET_CONTROLE) {
                $controles = self::lireControle($feuille['lignes'] ?? []);

                continue;
            }

            $lue = self::lireFeuille($nom, $feuille['lignes'] ?? [], $niveauParDefaut);

            $erreurs = [...$erreurs, ...$lue['erreurs']];
            $avertissements = [...$avertissements, ...$lue['avertissements']];

            if ($lue['specialite'] !== null) {
                $specialites[] = $lue['specialite'];
            }
        }

        return [
            'specialites' => $specialites,
            'erreurs' => $erreurs,
            'avertissements' => $avertissements,
            'controles' => self::recoupeControles($controles, $specialites),
        ];
    }

    /**
     * Écrit en base ce que [lire()] a compris.
     *
     * Jamais destructif : un module absent du classeur n'est pas supprimé.
     * Un import est souvent partiel — une spécialité à la fois, un niveau à la
     * fois — et effacer ce qu'il ne mentionne pas ferait disparaître le reste
     * du référentiel au premier fichier incomplet. Les retraits se font à
     * l'écran, un par un.
     *
     * @param  array<int, array<string, mixed>>  $specialitesLues
     * @return array{specialites_creees: int, specialites_mises_a_jour: int, modules_crees: int, modules_mis_a_jour: int}
     */
    public static function appliquer(array $specialitesLues): array
    {
        $bilan = [
            'specialites_creees' => 0,
            'specialites_mises_a_jour' => 0,
            'modules_crees' => 0,
            'modules_mis_a_jour' => 0,
        ];

        foreach ($specialitesLues as $lue) {
            $specialite = Specialite::firstOrNew(['code' => $lue['code']]);

            if (! $specialite->exists) {
                // Le libellé long n'est pas dans le classeur : le code en tient
                // lieu jusqu'à ce qu'il soit saisi à l'écran. Inventer
                // « Électronique » pour un onglet « F2 » reviendrait à écrire
                // une spécialité fausse en tête des bulletins.
                $specialite->nom = $lue['code'];
                $specialite->save();
                $bilan['specialites_creees']++;
            } else {
                $bilan['specialites_mises_a_jour']++;
            }

            foreach ($lue['modules'] as $module) {
                $existant = Module::firstOrNew([
                    'specialite_id' => $specialite->id,
                    'niveau' => $lue['niveau'],
                    'code' => $module['code'],
                ]);

                $nouveau = ! $existant->exists;

                $existant->fill([
                    'intitule' => $module['intitule'],
                    'bloc' => $module['bloc'],
                    'ordre' => $module['ordre'],
                ]);

                // Un volume horaire déjà connu ne doit pas être effacé par une
                // ligne ambiguë du classeur, qui n'en porte aucun.
                if ($module['volume_horaire'] !== null) {
                    $existant->volume_horaire = $module['volume_horaire'];
                }

                $existant->save();

                $bilan[$nouveau ? 'modules_crees' : 'modules_mis_a_jour']++;
            }
        }

        return $bilan;
    }

    /* ------------------------------------------------------------------ */
    /* Lecture d'un onglet                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * @param  array<int, array<int, mixed>>  $lignes
     * @return array{specialite: ?array<string, mixed>, erreurs: array<int, array<string, mixed>>, avertissements: array<int, array<string, mixed>>}
     */
    private static function lireFeuille(string $nomFeuille, array $lignes, ?string $niveauParDefaut): array
    {
        $erreurs = [];
        $avertissements = [];

        $entete = self::trouveEntete($lignes);

        if ($entete === null) {
            return [
                'specialite' => null,
                'erreurs' => [[
                    'feuille' => $nomFeuille,
                    'ligne' => 1,
                    'erreur' => 'aucune colonne de bloc reconnue : l\'onglet doit porter en en-tête '
                        .'« MODULES DES COMPÉTENCES PARTICULIÈRES », « … GÉNÉRALES », '
                        .'« MODULES DES ENSEIGNEMENTS GÉNÉRAUX » et « … COMPLÉMENTAIRES »',
                    'valeurs' => [],
                ]],
                'avertissements' => [],
            ];
        }

        $modules = [];
        $codesVus = [];
        $niveau = null;
        $totalFeuille = 0;
        $ordre = 0;

        foreach ($lignes as $index => $ligne) {
            $numeroLigne = $index + 1;

            if ($numeroLigne <= $entete['ligne']) {
                continue;
            }

            $volumeHoraire = self::entierOuNull($ligne[$entete['colonne_vh']] ?? null);

            if (self::estLigneTotal($ligne)) {
                // « TOTAL SECONDE (h) » : le niveau du référentiel est écrit
                // là, et nulle part ailleurs dans le classeur.
                $niveau ??= self::niveauDepuisTotal($ligne);

                continue;
            }

            $cellules = [];
            foreach ($entete['blocs'] as $colonne => $bloc) {
                $intitule = self::texteOuNull($ligne[$colonne] ?? null);
                if ($intitule !== null) {
                    $cellules[] = ['bloc' => $bloc, 'intitule' => $intitule];
                }
            }

            if ($cellules === []) {
                continue;
            }

            if ($volumeHoraire !== null) {
                $totalFeuille += $volumeHoraire;
            }

            // Deux modules sur une même ligne : la colonne des heures n'en
            // porte qu'une, et rien ne dit à laquelle elle se rapporte.
            $ambigu = count($cellules) > 1;

            if ($ambigu) {
                $avertissements[] = [
                    'feuille' => $nomFeuille,
                    'ligne' => $numeroLigne,
                    'avertissement' => 'deux blocs renseignés sur la même ligne : les modules sont importés '
                        .'mais leur volume horaire reste à compléter'
                        .($volumeHoraire === null ? '' : " (les {$volumeHoraire} h de la ligne ne sont attribuées à aucun des deux)"),
                    'valeurs' => array_map(fn (array $c) => $c['intitule'], $cellules),
                ];
            }

            foreach ($cellules as $cellule) {
                $decoupe = self::decoupeCode($cellule['intitule'], $cellule['bloc'], $codesVus);

                if (isset($codesVus[$decoupe['code']])) {
                    $premiere = $codesVus[$decoupe['code']]['ligne'];
                    $erreurs[] = [
                        'feuille' => $nomFeuille,
                        'ligne' => $numeroLigne,
                        'erreur' => "module « {$decoupe['code']} » déjà présent ligne {$premiere} de cet onglet : ligne ignorée",
                        'valeurs' => ['intitule' => $cellule['intitule']],
                    ];

                    continue;
                }

                if ($decoupe['code_fabrique']) {
                    $avertissements[] = [
                        'feuille' => $nomFeuille,
                        'ligne' => $numeroLigne,
                        'avertissement' => "aucun code dans l'intitulé : le code « {$decoupe['code']} » a été fabriqué et reste à corriger",
                        'valeurs' => ['intitule' => $cellule['intitule']],
                    ];
                }

                $codesVus[$decoupe['code']] = ['ligne' => $numeroLigne, 'intitule' => $decoupe['intitule']];

                $modules[] = [
                    'code' => $decoupe['code'],
                    'intitule' => $decoupe['intitule'],
                    'bloc' => $cellule['bloc'],
                    'volume_horaire' => $ambigu ? null : $volumeHoraire,
                    'ordre' => $ordre++,
                    'code_fabrique' => $decoupe['code_fabrique'],
                    'ligne' => $numeroLigne,
                ];
            }
        }

        $niveau ??= self::texteOuNull($niveauParDefaut);

        if ($modules === []) {
            return [
                'specialite' => null,
                'erreurs' => [...$erreurs, [
                    'feuille' => $nomFeuille,
                    'ligne' => $entete['ligne'],
                    'erreur' => 'aucun module lisible sous les en-têtes de bloc',
                    'valeurs' => [],
                ]],
                'avertissements' => $avertissements,
            ];
        }

        if ($niveau === null) {
            return [
                'specialite' => null,
                'erreurs' => [...$erreurs, [
                    'feuille' => $nomFeuille,
                    'ligne' => count($lignes),
                    'erreur' => 'niveau introuvable : ajoutez une ligne « TOTAL SECONDE (h) » à l\'onglet, '
                        .'ou choisissez un niveau dans le formulaire d\'import',
                    'valeurs' => [],
                ]],
                'avertissements' => $avertissements,
            ];
        }

        return [
            'specialite' => [
                'code' => Str::upper($nomFeuille),
                'feuille' => $nomFeuille,
                'niveau' => $niveau,
                'modules' => $modules,
                'total_volume_horaire' => $totalFeuille,
            ],
            'erreurs' => $erreurs,
            'avertissements' => $avertissements,
        ];
    }

    /**
     * Repère la ligne d'en-tête et ce que porte chaque colonne.
     *
     * Cherchée et non supposée en ligne 1 : certains classeurs ouvrent sur un
     * titre ou une ligne vide.
     *
     * @param  array<int, array<int, mixed>>  $lignes
     * @return array{ligne: int, blocs: array<int, string>, colonne_vh: int}|null
     */
    private static function trouveEntete(array $lignes): ?array
    {
        foreach ($lignes as $index => $ligne) {
            $blocs = [];
            $colonneVh = null;

            foreach ($ligne as $colonne => $valeur) {
                $bloc = self::blocDepuisEntete((string) $valeur);
                if ($bloc !== null) {
                    $blocs[$colonne] = $bloc;

                    continue;
                }

                if ($colonneVh === null && self::estEnteteVolumeHoraire((string) $valeur)) {
                    $colonneVh = $colonne;
                }
            }

            if ($blocs !== []) {
                return [
                    'ligne' => $index + 1,
                    'blocs' => $blocs,
                    // Sans colonne « VH », les modules s'importent quand même :
                    // les heures se saisissent à l'écran.
                    'colonne_vh' => $colonneVh ?? max(array_keys($blocs)) + 1,
                ];
            }
        }

        return null;
    }

    /**
     * Bloc désigné par un en-tête de colonne.
     *
     * Reconnaissance par mots-clés plutôt que par égalité : les classeurs
     * diffusés écrivent « ENSEIGNEMENTS GENERALES » comme « ENSEIGNEMENTS
     * GENERAUX », avec des doubles espaces et des accents variables.
     * L'ordre des tests compte — « compétences générales » et « enseignements
     * généraux » partagent le mot « général ».
     */
    private static function blocDepuisEntete(string $valeur): ?string
    {
        $texte = self::normalise($valeur);

        if ($texte === '' || ! str_contains($texte, 'module')) {
            return null;
        }

        return match (true) {
            str_contains($texte, 'complementaire') => Module::BLOC_ENSEIGNEMENTS_COMPLEMENTAIRES,
            str_contains($texte, 'particulier') => Module::BLOC_COMPETENCES_PARTICULIERES,
            str_contains($texte, 'enseignement') => Module::BLOC_ENSEIGNEMENTS_GENERAUX,
            str_contains($texte, 'competence') => Module::BLOC_COMPETENCES_GENERALES,
            default => null,
        };
    }

    private static function estEnteteVolumeHoraire(string $valeur): bool
    {
        $texte = self::normalise($valeur);

        return $texte === 'vh' || str_contains($texte, 'volume horaire');
    }

    /** @param array<int, mixed> $ligne */
    private static function estLigneTotal(array $ligne): bool
    {
        foreach ($ligne as $valeur) {
            if (str_starts_with(self::normalise((string) $valeur), 'total')) {
                return true;
            }
        }

        return false;
    }

    /**
     * « TOTAL SECONDE (h) » -> « Seconde ».
     *
     * @param  array<int, mixed>  $ligne
     */
    private static function niveauDepuisTotal(array $ligne): ?string
    {
        foreach ($ligne as $valeur) {
            $texte = trim((string) $valeur);

            if (preg_match('/^total\s+(.+?)\s*\(\s*h\s*\)\s*$/iu', $texte, $trouve) === 1) {
                return Str::title(mb_strtolower(trim($trouve[1]), 'UTF-8'));
            }
        }

        return null;
    }

    /**
     * Sépare le code de l'intitulé : « Qualité… (QHSE) (C2) » -> C2 +
     * « Qualité… (QHSE) ». Seule la **dernière** parenthèse est candidate, et
     * seulement si elle a la forme d'un code de module — sans quoi un sigle
     * comme « (EPS) » serait pris pour un code.
     *
     * @param  array<string, array{ligne: int, intitule: string}>  $codesVus
     * @return array{code: string, intitule: string, code_fabrique: bool}
     */
    private static function decoupeCode(string $intitule, string $bloc, array $codesVus): array
    {
        $intitule = trim(preg_replace('/\s+/u', ' ', $intitule) ?? $intitule);

        if (preg_match(self::MOTIF_CODE, $intitule, $trouve) === 1) {
            $reste = trim($trouve[1]);

            if ($reste !== '') {
                return [
                    'code' => Str::upper(preg_replace('/\s+/', '', $trouve[2]) ?? $trouve[2]),
                    'intitule' => $reste,
                    'code_fabrique' => false,
                ];
            }
        }

        return [
            'code' => self::fabriqueCode($intitule, $bloc, $codesVus),
            'intitule' => $intitule,
            'code_fabrique' => true,
        ];
    }

    /**
     * Code de secours pour un module que le classeur n'a pas codé — le cas de
     * tous les enseignements généraux, et d'onglets entiers.
     *
     * Dérivé de l'intitulé pour rester lisible et stable d'un import à
     * l'autre : un code tiré d'un compteur changerait à chaque fichier et
     * dupliquerait le référentiel au second passage.
     *
     * **Un intitulé identique doit produire le même code**, et donc entrer en
     * collision : c'est par là que sont repérés les doublons d'un onglet qui
     * ne code aucun de ses modules — « Français » écrit deux fois dans
     * l'onglet MVT. Désambiguïser systématiquement par un suffixe rendrait ces
     * doublons indétectables et les importerait tous les deux. Le suffixe ne
     * sert donc qu'au cas inverse : deux intitulés **différents** dont les
     * codes se rejoignent après troncature.
     *
     * @param  array<string, array{ligne: int, intitule: string}>  $codesVus
     */
    private static function fabriqueCode(string $intitule, string $bloc, array $codesVus): string
    {
        $base = Str::upper(Str::slug(Str::ascii($intitule))) ?: 'MODULE';
        $base = self::PREFIXES_CODE[$bloc].'-'.Str::limit($base, self::LONGUEUR_CODE_FABRIQUE, '');

        $code = $base;
        $suffixe = 2;

        while (isset($codesVus[$code]) && self::normalise($codesVus[$code]['intitule']) !== self::normalise($intitule)) {
            $code = $base.'-'.$suffixe++;
        }

        return $code;
    }

    /* ------------------------------------------------------------------ */
    /* Onglet de contrôle                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Onglet « Contrôle » : le total d'heures que l'organisation pédagogique
     * annonce pour chaque spécialité. Il n'entre pas dans le référentiel, il
     * sert à le recouper.
     *
     * @param  array<int, array<int, mixed>>  $lignes
     * @return array<string, int>
     */
    private static function lireControle(array $lignes): array
    {
        $totaux = [];

        foreach ($lignes as $ligne) {
            $code = self::texteOuNull($ligne[0] ?? null);
            $totalOp = self::entierOuNull($ligne[2] ?? null);

            if ($code === null || $totalOp === null) {
                continue;
            }

            $totaux[Str::upper($code)] = $totalOp;
        }

        return $totaux;
    }

    /**
     * Compare les heures lues à celles annoncées. Un écart n'empêche pas
     * l'import : il désigne une incohérence du document, que seule
     * l'administration peut trancher.
     *
     * @param  array<string, int>  $totauxAnnonces
     * @param  array<int, array<string, mixed>>  $specialites
     * @return array<int, array<string, mixed>>
     */
    private static function recoupeControles(array $totauxAnnonces, array $specialites): array
    {
        $controles = [];

        foreach ($specialites as $specialite) {
            $annonce = $totauxAnnonces[$specialite['code']] ?? null;

            if ($annonce === null) {
                continue;
            }

            $controles[] = [
                'specialite' => $specialite['code'],
                'total_calcule' => $specialite['total_volume_horaire'],
                'total_annonce' => $annonce,
                'ecart' => $specialite['total_volume_horaire'] - $annonce,
            ];
        }

        return $controles;
    }

    /* ------------------------------------------------------------------ */
    /* Outils                                                              */
    /* ------------------------------------------------------------------ */

    /** Minuscules, sans accents, espaces repliés — pour comparer des en-têtes saisis à la main. */
    private static function normalise(string $valeur): string
    {
        $texte = Str::ascii(trim($valeur));
        $texte = preg_replace('/\s+/u', ' ', $texte) ?? $texte;

        return mb_strtolower($texte, 'UTF-8');
    }

    private static function texteOuNull(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

        $texte = trim(preg_replace('/\s+/u', ' ', (string) $valeur) ?? (string) $valeur);

        return $texte === '' ? null : $texte;
    }

    private static function entierOuNull(mixed $valeur): ?int
    {
        if ($valeur === null || $valeur === '' || ! is_numeric($valeur)) {
            return null;
        }

        return (int) $valeur;
    }
}
