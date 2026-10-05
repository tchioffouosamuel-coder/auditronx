<?php

namespace App\Http\Controllers\Traits;

use App\Models\Discipline;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Périmètre d'accès aux disciplines par accréditation (§3, §4.2).
 *
 * Même principe que `AccessibleEnseignants`, appliqué au `departement` de la
 * discipline : un censeur industriel ne doit ni voir, ni modifier, ni attribuer
 * à un enseignant une matière relevant de la section générale ou STT.
 * L'accréditation ne décrivait jusqu'ici que le périmètre sur les personnes ;
 * les matières restaient ouvertes à tous, ce qui permettait à n'importe quel
 * censeur de renommer ou de supprimer les disciplines d'une autre section.
 *
 * Les disciplines sans département sont considérées comme transversales :
 * visibles et attribuables par tous, mais modifiables uniquement par une
 * accréditation à accès total. Sans cette nuance, les matières communes déjà
 * en base (département vide) deviendraient invisibles pour tout le monde.
 */
trait AccessiblesDisciplines
{
    protected function scopeDisciplinesAccessibles(Builder $query, User $user): Builder
    {
        $accreditation = $user->accreditation;

        if (! $accreditation || $accreditation->estAccesTotal()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($accreditation) {
            $q->whereRaw('LOWER(departement) = LOWER(?)', [$accreditation->groupe])
                ->orWhereNull('departement')
                ->orWhere('departement', '');
        });
    }

    /** Query des disciplines consultables et attribuables par $user. */
    protected function disciplinesAccessibles(User $user): Builder
    {
        return $this->scopeDisciplinesAccessibles(Discipline::query(), $user);
    }

    /** $user peut-il consulter cette discipline et l'attribuer à un enseignant ? */
    protected function peutUtiliserDiscipline(User $user, Discipline $discipline): bool
    {
        $accreditation = $user->accreditation;

        if (! $accreditation || $accreditation->estAccesTotal()) {
            return true;
        }

        if (trim((string) $discipline->departement) === '') {
            return true;
        }

        return mb_strtolower((string) $discipline->departement) === mb_strtolower((string) $accreditation->groupe);
    }

    /**
     * $user peut-il créer, modifier ou supprimer cette discipline ?
     *
     * Plus strict que l'attribution : une matière transversale (sans
     * département) est utilisable par tous mais ne se modifie qu'avec un accès
     * total, sinon deux sections se marcheraient dessus sur la même fiche.
     */
    protected function peutModifierDiscipline(User $user, ?string $departement): bool
    {
        $accreditation = $user->accreditation;

        if (! $accreditation || $accreditation->estAccesTotal()) {
            return true;
        }

        return mb_strtolower(trim((string) $departement)) === mb_strtolower((string) $accreditation->groupe);
    }

    /** Interrompt la requête si $user n'a pas la main sur ce département. */
    protected function assertPeutModifierDiscipline(User $user, ?string $departement): void
    {
        abort_unless(
            $this->peutModifierDiscipline($user, $departement),
            403,
            'Votre accréditation ne couvre pas ce département.',
        );
    }
}
