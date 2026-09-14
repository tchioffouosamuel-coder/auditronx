<?php

namespace App\Http\Controllers\Traits;

use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Périmètre d'accès aux enseignants par accréditation (groupe/section, niveau).
 *
 * Une accréditation de groupe '*' donne accès à tous les enseignants (direction/
 * administration). Toute autre valeur de groupe restreint l'accès aux enseignants
 * de la même section (§3 du cahier des charges).
 */
trait AccessibleEnseignants
{
    /**
     * Applique le périmètre d'accès de $user à une requête Enseignant.
     *
     * Comparaison insensible à la casse (`LOWER`) : `enseignants.section` et
     * `accreditations.groupe` sont saisis à la main depuis des années et
     * portent des variantes de casse pour une même section ("Industrielle"
     * / "industrielle", "STT" / "stt"...) — une égalité stricte ne verrait
     * qu'une partie des enseignants réellement de la section (ex. un censeur
     * industriel ne voyait que la moitié de son effectif).
     *
     * Même restreint à une section, $user voit toujours sa propre fiche
     * enseignant liée (`User::enseignant`, §admin-mobile) : sinon un admin à
     * accès restreint ne verrait jamais ses propres scans personnels (journal,
     * stats, etc.) dès que sa fiche n'a pas la section de son accréditation
     * (cas courant pour le personnel administratif, sans section).
     */
    protected function scopeAccessiblePar(Builder $query, User $user): Builder
    {
        $accreditation = $user->accreditation;

        if (! $accreditation || $accreditation->estAccesTotal()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($accreditation, $user) {
            $q->whereRaw('LOWER(section) = LOWER(?)', [$accreditation->groupe]);

            if ($user->enseignant_id) {
                $q->orWhere('id', $user->enseignant_id);
            }
        });
    }

    /** L'utilisateur peut-il agir sur cet enseignant (consultation, procuration, correction) ? */
    protected function peutAccederA(User $user, Enseignant $enseignant): bool
    {
        if ($user->enseignant_id === $enseignant->id) {
            return true;
        }

        $accreditation = $user->accreditation;

        if (! $accreditation || $accreditation->estAccesTotal()) {
            return true;
        }

        return mb_strtolower((string) $enseignant->section) === mb_strtolower((string) $accreditation->groupe);
    }

    /** Query des enseignants accessibles par $user (base pour listes, exports, etc.). */
    protected function enseignantsAccessibles(User $user): Builder
    {
        return $this->scopeAccessiblePar(Enseignant::query(), $user);
    }
}
