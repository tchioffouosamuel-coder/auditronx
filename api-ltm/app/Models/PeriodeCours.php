<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Une période de cours de l'établissement : un couple heure de début / heure
 * de fin, ordonné, éventuellement nommé (« P1 », « Récréation »). C'est cette
 * liste qui alimente les listes déroulantes de l'emploi du temps, côté web
 * comme côté mobile. La grille historique `EmploiDuTemps::HEURES_DEBUT` ne
 * sert plus qu'à peupler la table à la migration.
 */
class PeriodeCours extends Model
{
    use HasFactory;

    protected $table = 'periodes_cours';

    protected $fillable = ['ordre', 'libelle', 'heure_debut', 'heure_fin'];

    protected $appends = ['libelle_complet'];

    /**
     * Périodes de l'établissement, ordonnées. La grille historique du code
     * (`EmploiDuTemps::HEURES_DEBUT`) ne sert plus qu'à peupler la table à la
     * migration : la renvoyer ici produisait des périodes sans identifiant,
     * que les écrans affichaient sans pouvoir les modifier ni les supprimer.
     * Table vide = aucune période, et les formulaires le disent.
     */
    public static function grille(): Collection
    {
        return static::orderBy('ordre')->orderBy('heure_debut')->get();
    }

    /** « 08:10:00 » stocké en colonne `time` -> « 08:10 » attendu par l'API. */
    public static function versHeureMinute(?string $heure): string
    {
        return substr((string) $heure, 0, 5);
    }

    public function getHeureDebutAttribute($valeur): string
    {
        return static::versHeureMinute($valeur);
    }

    public function getHeureFinAttribute($valeur): string
    {
        return static::versHeureMinute($valeur);
    }

    /** « P1 · 07h30–08h10 » — libellé affiché dans les listes déroulantes. */
    public function getLibelleCompletAttribute(): string
    {
        $plage = str_replace(':', 'h', $this->heure_debut).'–'.str_replace(':', 'h', $this->heure_fin);

        return $this->libelle ? $this->libelle.' · '.$plage : $plage;
    }
}
