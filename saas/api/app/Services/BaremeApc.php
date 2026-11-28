<?php

namespace App\Services;

use App\Models\Parametre;
use Illuminate\Support\Facades\DB;

/**
 * Barème des bulletins de compétences (modèle APC, second cycle technique et
 * professionnel industriel).
 *
 * Tout ce qui transforme des notes en décisions vit ici, et nulle part
 * ailleurs : la note finale d'un module, sa mention, sa sanction, la moyenne
 * générale pondérée par les crédits, la position dans le groupe et la décision
 * du conseil de classe. Les écrans de saisie, le calcul des bulletins et les
 * vues PDF appellent ce service plutôt que de recopier les seuils — un
 * établissement qui déplace son seuil de réussite ne doit pas le déplacer à
 * sept endroits.
 *
 * Les valeurs par défaut sont celles du modèle national présenté en formation
 * (seuil 70/100, répartition 60/40, mentions A→E). Elles sont surchargeables
 * par établissement via la table `parametres`, préfixe `apc.`, comme l'en-tête
 * des documents ou la durée d'une période.
 *
 * ## Arrondi
 *
 * Toutes les notes produites sont arrondies au dixième, et c'est la **valeur
 * arrondie** qui sert ensuite de base à la mention, à la sanction et à la
 * moyenne générale. Autrement dit : ce qui est imprimé est ce qui est calculé.
 * Faire autrement produirait des bulletins invérifiables à la main — un module
 * affiché 85,0 classé en « Bien » parce que sa valeur brute vaut 84,96, ou une
 * moyenne générale que le parent ne retrouve pas en reprenant la colonne.
 *
 * ## Ce que ce service ne décide pas
 *
 * Une note absente (`null`) signifie « pas de note », jamais « zéro ». Elle ne
 * distingue pas pour autant le module **non programmé** ce trimestre — le
 * tiret du bulletin — de la saisie **incomplète**. Cette distinction se lit
 * dans la maquette de la classe (les trimestres où le module est programmé),
 * pas dans la note : c'est au composant appelant de la faire.
 */
class BaremeApc
{
    /** Préfixe des clés de la table `parametres`, pour ne pas se mêler aux autres réglages. */
    public const PREFIXE = 'apc.';

    public const CLE_SEUIL_REUSSITE = self::PREFIXE.'seuil_reussite';

    public const CLE_BAREME_UEA = self::PREFIXE.'bareme_uea';

    public const CLE_MENTIONS = self::PREFIXE.'mentions';

    public const CLE_NOMBRE_TRIMESTRES = self::PREFIXE.'nombre_trimestres';

    /**
     * Note sur laquelle se juge une compétence. Le bulletin est noté sur 100,
     * jamais sur 20 : c'est la somme de la moyenne des unités d'évaluation
     * (UEA) et de l'épreuve intégratrice.
     */
    public const NOTE_MAXIMALE = 100;

    /** Part de la moyenne des UEA dans la note finale ; le reste va à l'épreuve intégratrice. */
    public const BAREME_UEA = 60;

    /** En deçà, la compétence n'est pas acquise : le module est à reprendre. */
    public const SEUIL_REUSSITE = 70.0;

    public const NOMBRE_TRIMESTRES = 3;

    /** Nombre de décimales des notes imprimées — voir « Arrondi » plus haut. */
    public const DECIMALES = 1;

    /**
     * Bornes **inférieures** des mentions, de la plus haute à la plus basse.
     *
     * Le document de référence les écrit en intervalles fermés (A : 90-100,
     * B : 85-89, …), ce qui laisse 84,5 ou 89,2 sans mention alors que les
     * notes finales ont un dixième. Les stocker en bornes inférieures ferme
     * ces trous sans déplacer aucune limite : une note classée B hier reste
     * classée B.
     *
     * @var array<string, float>
     */
    public const MENTIONS = ['A' => 90.0, 'B' => 85.0, 'C' => 80.0, 'D' => 75.0, 'E' => 70.0];

    /**
     * Appréciation littérale attachée à chaque mention. Elle n'est pas
     * paramétrable : c'est la légende imprimée sur le bulletin, et la laisser
     * diverger de la lettre rendrait cette légende fausse.
     *
     * @var array<string, string>
     */
    public const APPRECIATIONS = [
        'A' => 'Excellent',
        'B' => 'Très bien',
        'C' => 'Bien',
        'D' => 'Assez bien',
        'E' => 'Passable',
    ];

    public const APPRECIATION_NON_ACQUISE = 'Non acquise – à reprendre';

    /** Colonne « SANCTION » du bulletin : compétence validée, ou non acquise. */
    public const SANCTION_VALIDEE = 'S';

    public const SANCTION_NON_ACQUISE = 'E';

    /** Ce qu'imprime le bulletin là où il n'y a rien à imprimer. */
    public const TIRET = '—';

    /** Décision globale d'un bulletin trimestriel. */
    public const DECISION_SUCCES = 'succes';

    public const DECISION_REMEDIATION = 'remediation';

    public const DECISION_ECHEC = 'echec';

    /** Décision du bulletin annuel. */
    public const DECISION_ADMIS = 'admis';

    public const DECISION_SOUS_RESERVE = 'validation_sous_reserve';

    public const DECISION_REDOUBLEMENT = 'redoublement';

    /** Libellés imprimés des décisions. */
    public const LIBELLES_DECISIONS = [
        self::DECISION_SUCCES => 'Succès',
        self::DECISION_REMEDIATION => 'Remédiation',
        self::DECISION_ECHEC => 'Échec',
        self::DECISION_ADMIS => 'Admis',
        self::DECISION_SOUS_RESERVE => 'Validation sous réserve',
        self::DECISION_REDOUBLEMENT => 'Redoublement',
    ];

    /**
     * Validation des compétences professionnelles. Le formulaire officiel
     * n'offre que deux cases — « OUI » et « NON / PARTIEL » — et non trois :
     * on s'y tient plutôt que d'inventer un troisième état qui n'aurait nulle
     * part où s'imprimer.
     */
    public const VALIDATION_OUI = 'oui';

    public const VALIDATION_NON_PARTIEL = 'non_partiel';

    /** Position de l'élève par rapport au groupe. */
    public const POSITION_TETE = 'tete';

    public const POSITION_MILIEU = 'milieu';

    public const POSITION_DIFFICULTE = 'difficulte';

    public const LIBELLES_POSITIONS = [
        self::POSITION_TETE => 'Tête de classe',
        self::POSITION_MILIEU => 'Milieu de classe',
        self::POSITION_DIFFICULTE => 'En difficulté',
    ];

    /**
     * Barème mémorisé par base de données : une génération de bulletins de
     * classe le consulte une fois par ligne de chaque bulletin, soit quelques
     * milliers de fois pour une classe de quarante.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $memo = [];

    /* ---------------------------------------------------------------- */
    /* Réglages                                                          */
    /* ---------------------------------------------------------------- */

    /** Note en deçà de laquelle une compétence n'est pas acquise. */
    public static function seuilReussite(): float
    {
        return self::reglages()['seuil_reussite'];
    }

    /**
     * Répartition de la note finale entre la moyenne des UEA et l'épreuve
     * intégratrice. Les deux parts totalisent toujours [NOTE_MAXIMALE] : seule
     * la première est stockée, la seconde s'en déduit.
     *
     * @return array{uea: int, integratrice: int}
     */
    public static function repartition(): array
    {
        $uea = self::reglages()['bareme_uea'];

        return ['uea' => $uea, 'integratrice' => self::NOTE_MAXIMALE - $uea];
    }

    /**
     * Bornes inférieures des mentions, de la plus haute à la plus basse.
     *
     * @return array<string, float>
     */
    public static function mentions(): array
    {
        return self::reglages()['mentions'];
    }

    public static function nombreTrimestres(): int
    {
        return self::reglages()['nombre_trimestres'];
    }

    /**
     * Le barème complet, tel que l'exposent l'API de configuration et les
     * écrans de saisie.
     *
     * @return array{seuil_reussite: float, bareme_uea: int, bareme_integratrice: int, mentions: array<string, float>, nombre_trimestres: int, appreciations: array<string, string>}
     */
    public static function valeurs(): array
    {
        $reglages = self::reglages();

        return [
            'seuil_reussite' => $reglages['seuil_reussite'],
            'bareme_uea' => $reglages['bareme_uea'],
            'bareme_integratrice' => self::NOTE_MAXIMALE - $reglages['bareme_uea'],
            'mentions' => $reglages['mentions'],
            'nombre_trimestres' => $reglages['nombre_trimestres'],
            'appreciations' => self::APPRECIATIONS,
            'note_maximale' => self::NOTE_MAXIMALE,
        ];
    }

    /**
     * Enregistre les réglages fournis ; ceux qui sont absents ne bougent pas.
     *
     * @param  array{seuil_reussite?: float|int|string, bareme_uea?: int|string, mentions?: array<string, float|int|string>, nombre_trimestres?: int|string}  $valeurs
     * @return array<string, mixed> le barème après écriture
     */
    public static function definir(array $valeurs): array
    {
        if (array_key_exists('seuil_reussite', $valeurs)) {
            Parametre::set(self::CLE_SEUIL_REUSSITE, (string) (float) $valeurs['seuil_reussite']);
        }

        if (array_key_exists('bareme_uea', $valeurs)) {
            Parametre::set(self::CLE_BAREME_UEA, (string) (int) $valeurs['bareme_uea']);
        }

        if (array_key_exists('nombre_trimestres', $valeurs)) {
            Parametre::set(self::CLE_NOMBRE_TRIMESTRES, (string) (int) $valeurs['nombre_trimestres']);
        }

        if (array_key_exists('mentions', $valeurs)) {
            $mentions = [];
            foreach (self::MENTIONS as $lettre => $defaut) {
                $mentions[$lettre] = (float) ($valeurs['mentions'][$lettre] ?? $defaut);
            }
            Parametre::set(self::CLE_MENTIONS, (string) json_encode($mentions));
        }

        self::oublier();

        return self::valeurs();
    }

    /** À appeler après toute écriture directe sur les paramètres `apc.` (tests, seeders). */
    public static function oublier(): void
    {
        self::$memo = [];
    }

    /* ---------------------------------------------------------------- */
    /* Notes                                                             */
    /* ---------------------------------------------------------------- */

    /**
     * Note finale d'un module sur un trimestre : moyenne des UEA (/60) +
     * épreuve intégratrice (/40).
     *
     * Les deux parts sont exigées. Une moyenne d'UEA sans épreuve
     * intégratrice ne vaut pas « la note moins quarante points » : c'est une
     * saisie en cours, et la traiter comme un zéro ferait échouer un élève sur
     * une évaluation qui n'a pas encore eu lieu.
     */
    public static function noteFinaleTrimestre(float|int|null $moyenneUea, float|int|null $noteIntegratrice): ?float
    {
        if ($moyenneUea === null || $noteIntegratrice === null) {
            return null;
        }

        return self::arrondir((float) $moyenneUea + (float) $noteIntegratrice);
    }

    /**
     * Note finale annuelle d'un module : moyenne des trimestres **où il est
     * programmé**. Les trimestres sans note sortent du dénominateur — c'est le
     * tiret du bulletin, pas un zéro.
     *
     * @param  array<int, float|int|null>  $notesTrimestres
     */
    public static function noteFinaleAnnuelle(array $notesTrimestres): ?float
    {
        $notes = array_values(array_filter($notesTrimestres, fn ($note) => $note !== null));

        return $notes === [] ? null : self::arrondir(array_sum($notes) / count($notes));
    }

    /**
     * Moyenne générale des notes pondérées : somme des notes multipliées par
     * leur crédit, divisée par le total des crédits.
     *
     * Un module sans note ne compte ni au numérateur ni au dénominateur : le
     * stage non programmé de l'année, dont le bulletin met le crédit entre
     * parenthèses, ne doit pas diluer la moyenne.
     *
     * @param  array<int, array{note?: float|int|null, credit?: int|null}>  $lignes
     */
    public static function moyenneGenerale(array $lignes): ?float
    {
        $total = 0.0;
        $credits = 0;

        foreach (self::lignesRetenues($lignes) as $ligne) {
            $total += (float) $ligne['note'] * (int) $ligne['credit'];
            $credits += (int) $ligne['credit'];
        }

        return $credits === 0 ? null : self::arrondir($total / $credits);
    }

    /**
     * Synthèse d'un bulletin : les totaux du bas de tableau et ce qui s'en
     * déduit. Point d'entrée unique du calcul d'un bulletin — un seul endroit
     * décide quelles lignes comptent.
     *
     * @param  array<int, array{note?: float|int|null, credit?: int|null, volume_horaire?: int|null}>  $lignes
     * @return array{moyenne_generale: ?float, total_credits: int, total_volume_horaire: ?int, modules_notes: int, competences_non_validees: int, mention: ?string, validation_competences: string}
     */
    public static function synthese(array $lignes): array
    {
        $retenues = self::lignesRetenues($lignes);
        $moyenne = self::moyenneGenerale($retenues);

        $credits = 0;
        $volumeHoraire = null;
        $nonValidees = 0;

        foreach ($retenues as $ligne) {
            $credits += (int) $ligne['credit'];

            if (($ligne['volume_horaire'] ?? null) !== null) {
                $volumeHoraire = (int) $volumeHoraire + (int) $ligne['volume_horaire'];
            }

            if (! self::estValidee($ligne['note'])) {
                $nonValidees++;
            }
        }

        return [
            'moyenne_generale' => $moyenne,
            'total_credits' => $credits,
            'total_volume_horaire' => $volumeHoraire,
            'modules_notes' => count($retenues),
            'competences_non_validees' => $nonValidees,
            'mention' => self::mention($moyenne),
            'validation_competences' => $nonValidees === 0 ? self::VALIDATION_OUI : self::VALIDATION_NON_PARTIEL,
        ];
    }

    /* ---------------------------------------------------------------- */
    /* Mention, sanction, appréciation                                   */
    /* ---------------------------------------------------------------- */

    public static function estValidee(float|int|null $note): bool
    {
        return $note !== null && (float) $note >= self::seuilReussite();
    }

    /** Lettre de mention, ou `null` sous le seuil de réussite : le bulletin y imprime un tiret. */
    public static function mention(float|int|null $note): ?string
    {
        if (! self::estValidee($note)) {
            return null;
        }

        foreach (self::mentions() as $lettre => $borne) {
            if ((float) $note >= $borne) {
                return $lettre;
            }
        }

        return null;
    }

    public static function sanction(float|int|null $note): ?string
    {
        if ($note === null) {
            return null;
        }

        return self::estValidee($note) ? self::SANCTION_VALIDEE : self::SANCTION_NON_ACQUISE;
    }

    public static function appreciation(float|int|null $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $mention = self::mention($note);

        return $mention === null
            ? self::APPRECIATION_NON_ACQUISE
            : (self::APPRECIATIONS[$mention] ?? self::APPRECIATION_NON_ACQUISE);
    }

    /* ---------------------------------------------------------------- */
    /* Décisions du conseil de classe                                    */
    /* ---------------------------------------------------------------- */

    /**
     * Décision globale proposée pour un bulletin trimestriel.
     *
     * Proposée, et non prononcée : le conseil de classe reste libre de la
     * modifier. Toutes les compétences acquises valent succès ; s'il en manque
     * mais que la moyenne tient le seuil, la remédiation suffit ; sinon le
     * trimestre est en échec.
     */
    public static function decisionTrimestre(float|int|null $moyenneGenerale, int $competencesNonValidees): string
    {
        if ($competencesNonValidees === 0) {
            return self::DECISION_SUCCES;
        }

        return self::estValidee($moyenneGenerale) ? self::DECISION_REMEDIATION : self::DECISION_ECHEC;
    }

    /** Décision annuelle proposée, sur la même logique que le trimestre. */
    public static function decisionAnnuelle(float|int|null $moyenneGenerale, int $competencesNonValidees): string
    {
        if ($competencesNonValidees === 0) {
            return self::DECISION_ADMIS;
        }

        return self::estValidee($moyenneGenerale) ? self::DECISION_SOUS_RESERVE : self::DECISION_REDOUBLEMENT;
    }

    public static function libelleDecision(?string $decision): ?string
    {
        return $decision === null ? null : (self::LIBELLES_DECISIONS[$decision] ?? null);
    }

    /**
     * Position de l'élève par rapport au groupe, par tiercile de rang.
     *
     * Le tiercile plutôt que la comparaison à la moyenne de la classe : dans
     * une classe où tout le monde tient autour de la moyenne, cette dernière
     * bascule un élève de « tête » à « en difficulté » pour deux dixièmes,
     * alors que son rang, lui, ne bouge pas.
     */
    public static function positionGroupe(?int $rang, ?int $effectif): ?string
    {
        if ($rang === null || $effectif === null || $effectif < 1 || $rang < 1 || $rang > $effectif) {
            return null;
        }

        $tiers = (int) ceil($effectif / 3);

        return match (true) {
            $rang <= $tiers => self::POSITION_TETE,
            $rang > $effectif - $tiers => self::POSITION_DIFFICULTE,
            default => self::POSITION_MILIEU,
        };
    }

    public static function libellePosition(?string $position): ?string
    {
        return $position === null ? null : (self::LIBELLES_POSITIONS[$position] ?? null);
    }

    /* ---------------------------------------------------------------- */
    /* Outils                                                            */
    /* ---------------------------------------------------------------- */

    /** Arrondi d'affichage, appliqué à toute note produite par ce service. */
    public static function arrondir(float|int|null $note): ?float
    {
        return $note === null ? null : round((float) $note, self::DECIMALES);
    }

    /**
     * Les lignes qui entrent dans les totaux : celles qui ont à la fois une
     * note et un crédit non nul.
     *
     * @param  array<int, array<string, mixed>>  $lignes
     * @return array<int, array<string, mixed>>
     */
    private static function lignesRetenues(array $lignes): array
    {
        return array_values(array_filter(
            $lignes,
            fn (array $ligne) => ($ligne['note'] ?? null) !== null && (int) ($ligne['credit'] ?? 0) > 0,
        ));
    }

    /**
     * Réglages de l'établissement courant, lus une fois par base.
     *
     * @return array{seuil_reussite: float, bareme_uea: int, mentions: array<string, float>, nombre_trimestres: int}
     */
    private static function reglages(): array
    {
        $cle = DB::connection()->getDatabaseName();

        return self::$memo[$cle] ??= [
            'seuil_reussite' => self::borner(
                (float) Parametre::get(self::CLE_SEUIL_REUSSITE, (string) self::SEUIL_REUSSITE),
                0.0,
                (float) self::NOTE_MAXIMALE,
                self::SEUIL_REUSSITE,
            ),
            'bareme_uea' => (int) self::borner(
                (float) Parametre::get(self::CLE_BAREME_UEA, (string) self::BAREME_UEA),
                1.0,
                (float) self::NOTE_MAXIMALE - 1,
                (float) self::BAREME_UEA,
            ),
            'mentions' => self::mentionsEnregistrees(),
            'nombre_trimestres' => (int) self::borner(
                (float) Parametre::get(self::CLE_NOMBRE_TRIMESTRES, (string) self::NOMBRE_TRIMESTRES),
                1.0,
                6.0,
                (float) self::NOMBRE_TRIMESTRES,
            ),
        ];
    }

    /**
     * Mentions enregistrées, remises en ordre décroissant de borne.
     *
     * L'ordre importe : [mention()] rend la première lettre dont la borne est
     * atteinte. Des bornes saisies dans le désordre classeraient toutes les
     * notes en « E ».
     *
     * @return array<string, float>
     */
    private static function mentionsEnregistrees(): array
    {
        $brut = json_decode(Parametre::get(self::CLE_MENTIONS, '') ?: '[]', true);

        if (! is_array($brut) || $brut === []) {
            return self::MENTIONS;
        }

        $mentions = [];
        foreach (self::MENTIONS as $lettre => $defaut) {
            $borne = $brut[$lettre] ?? $defaut;
            $mentions[$lettre] = is_numeric($borne)
                ? self::borner((float) $borne, 0.0, (float) self::NOTE_MAXIMALE, $defaut)
                : $defaut;
        }

        arsort($mentions);

        return $mentions;
    }

    /**
     * Une valeur hors bornes (saisie directe en base, enregistrement abîmé)
     * produirait des bulletins silencieusement faux — un seuil de réussite à
     * zéro validerait tout. On revient au défaut plutôt que de laisser passer.
     */
    private static function borner(float $valeur, float $min, float $max, float $defaut): float
    {
        return $valeur < $min || $valeur > $max ? $defaut : $valeur;
    }
}
