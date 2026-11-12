<?php

namespace App\Services;

use App\Models\Parametre;
use App\Models\PeriodeCours;
use Illuminate\Support\Facades\DB;

/**
 * Grille horaire de l'établissement : la durée d'une période et les pauses
 * qui la coupent (récréation, pause de midi).
 *
 * Une pause n'est pas une période : une plage 07h30–10h10 qui enjambe une
 * récréation de 20 minutes vaut 3 périodes et non 4. C'est ici qu'est décidé
 * ce décompte — pour l'assiduité (BilanIndividuel, RetardsController) comme
 * pour les formulaires d'emploi du temps (web et mobile, qui lisent les
 * pauses par `GET /api/periodes-cours?type=pause`).
 */
class GrilleHoraire
{
    /** Paramètre (table `parametres`) où l'établissement range la durée d'une période. */
    public const CLE_DUREE_PERIODE = 'duree_periode_minutes';

    /**
     * Durée d'une période, en minutes, tant que l'établissement n'en a pas
     * choisi une dans Configuration. Toutes les grilles ne découpent pas
     * l'heure de la même façon : 40, 45, 50 ou 55 minutes selon les
     * établissements — d'où le paramètre.
     */
    public const DUREE_PERIODE_MINUTES = 40;

    /** Bornes de saisie : au-delà, ce n'est plus une période de cours. */
    public const DUREE_PERIODE_MIN = 5;

    public const DUREE_PERIODE_MAX = 240;

    /**
     * Durée mémorisée par base de données, comme les pauses : un bilan
     * d'assiduité la consulte pour chaque journée de chaque personne.
     *
     * @var array<string, int>
     */
    private static array $duree = [];

    /** Durée d'une période de cours de cet établissement, en minutes. */
    public static function dureePeriodeMinutes(): int
    {
        $cle = DB::connection()->getDatabaseName();

        return self::$duree[$cle] ??= self::borner(
            (int) Parametre::get(self::CLE_DUREE_PERIODE, (string) self::DUREE_PERIODE_MINUTES)
        );
    }

    /** Enregistre la durée d'une période et vide les valeurs mémorisées. */
    public static function definirDureePeriode(int $minutes): int
    {
        $minutes = self::borner($minutes);
        Parametre::set(self::CLE_DUREE_PERIODE, (string) $minutes);
        self::oublierPauses();

        return $minutes;
    }

    /**
     * Une durée hors bornes (0 écrit à la main en base, valeur abîmée)
     * ferait boucler les calculs ou diviser par zéro : on la ramène dans la
     * plage plutôt que de laisser passer.
     */
    private static function borner(int $minutes): int
    {
        return max(self::DUREE_PERIODE_MIN, min(self::DUREE_PERIODE_MAX, $minutes));
    }

    /**
     * Pauses mémorisées par base de données : la grille ne change pas en
     * cours de requête, et le bilan d'assiduité la consulte pour chaque jour
     * de chaque personne.
     *
     * @var array<string, array<int, array{0: int, 1: int}>>
     */
    private static array $pauses = [];

    /** @return array<int, array{0: int, 1: int}> pauses en minutes depuis minuit */
    public static function pauses(): array
    {
        $cle = DB::connection()->getDatabaseName();

        return self::$pauses[$cle] ??= PeriodeCours::pauses()
            ->map(fn ($pause) => [self::minutes($pause->heure_debut), self::minutes($pause->heure_fin)])
            ->filter(fn ($plage) => $plage[1] > $plage[0])
            ->values()
            ->all();
    }

    /**
     * À appeler après toute écriture sur la grille (tests, CRUD des
     * périodes, changement de la durée d'une période) : pauses et durée sont
     * mémorisées ensemble, elles s'oublient ensemble.
     */
    public static function oublierPauses(): void
    {
        self::$pauses = [];
        self::$duree = [];
    }

    /** « 08:10 » / « 08:10:00 » -> 490. */
    public static function minutes(?string $heure): int
    {
        [$h, $m] = array_pad(array_map('intval', explode(':', (string) $heure)), 2, 0);

        return $h * 60 + $m;
    }

    /** 490 -> « 08:10 ». */
    public static function heure(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
    }

    /** Minutes d'une plage, pauses déduites. */
    public static function minutesHorsPause(?string $heureDebut, ?string $heureFin): int
    {
        $debut = self::minutes($heureDebut);
        $fin = self::minutes($heureFin);

        if ($fin <= $debut) {
            return 0;
        }

        $pause = 0;
        foreach (self::pauses() as [$pauseDebut, $pauseFin]) {
            $pause += max(0, min($fin, $pauseFin) - max($debut, $pauseDebut));
        }

        return max(0, $fin - $debut - $pause);
    }

    /** Nombre de périodes couvertes par une plage, pauses déduites. */
    public static function periodesEntre(?string $heureDebut, ?string $heureFin): int
    {
        return max(1, (int) ceil(self::minutesHorsPause($heureDebut, $heureFin) / self::dureePeriodeMinutes()));
    }

    /** Idem pour un total de minutes déjà calculé hors pause. */
    public static function periodesPourMinutes(int $minutes): int
    {
        return max(1, (int) ceil($minutes / self::dureePeriodeMinutes()));
    }

    /**
     * Heure de fin d'une plage qui commence à [$heureDebut] et dure
     * [$nombrePeriodes] périodes de cours, les pauses rencontrées en chemin
     * étant enjambées : elles allongent la plage sans la faire compter
     * davantage.
     */
    public static function finApresPeriodes(?string $heureDebut, int $nombrePeriodes): string
    {
        $courant = self::minutes($heureDebut);

        if ($nombrePeriodes < 1) {
            return self::heure($courant);
        }

        $restant = $nombrePeriodes * self::dureePeriodeMinutes();
        $pauses = self::pauses();
        usort($pauses, fn ($a, $b) => $a[0] <=> $b[0]);

        // Garde-fou : une grille mal saisie (pauses enchaînées) ne doit pas
        // faire tourner la boucle indéfiniment.
        for ($tour = 0; $tour <= count($pauses); $tour++) {
            $prochaine = null;
            foreach ($pauses as [$pauseDebut, $pauseFin]) {
                if ($pauseFin > $courant && $pauseDebut < $courant + $restant && $pauseDebut >= $courant) {
                    $prochaine = [$pauseDebut, $pauseFin];
                    break;
                }
                // Plage commencée pendant une pause : on reprend à sa fin.
                if ($pauseDebut <= $courant && $pauseFin > $courant) {
                    $prochaine = [$courant, $pauseFin];
                    break;
                }
            }

            if ($prochaine === null) {
                break;
            }

            $restant -= $prochaine[0] - $courant;
            $courant = $prochaine[1];
        }

        return self::heure($courant + max(0, $restant));
    }
}
