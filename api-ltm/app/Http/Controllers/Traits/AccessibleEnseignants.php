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
    /** Applique le périmètre d'accès de $user à une requête Enseignant. */
    protected function scopeAccessiblePar(Builder $query, User $user): Builder
    {
        $accreditation = $user->accreditation;

        if (! $accreditation || $accreditation->estAccesTotal()) {
            return $query;
        }

        return $query->where('section', $accreditation->groupe);
    }

    /** L'utilisateur peut-il agir sur cet enseignant (consultation, procuration, correction) ? */
    protected function peutAccederA(User $user, Enseignant $enseignant): bool
    {
        $accreditation = $user->accreditation;

        if (! $accreditation || $accreditation->estAccesTotal()) {
            return true;
        }

        return $enseignant->section === $accreditation->groupe;
    }

    /** Query des enseignants accessibles par $user (base pour listes, exports, etc.). */
    protected function enseignantsAccessibles(User $user): Builder
    {
        return $this->scopeAccessiblePar(Enseignant::query(), $user);
    }
}
