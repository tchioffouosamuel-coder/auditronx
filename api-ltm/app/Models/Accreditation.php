<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Accreditation extends Model
{
    use HasFactory;

    /**
     * Sections considérées comme administratives.
     *
     * Comparaison par préfixe (`admin%`) et non par égalité : la colonne
     * `enseignants.section` est saisie à la main depuis des années et contient
     * des variantes ("Administration", "Adminstration"). Une égalité stricte
     * laissait passer les fautes de frappe, donc laissait fuiter des fiches
     * administratives vers des rôles censés ne pas les voir.
     */
    public const PREFIXE_SECTION_ADMINISTRATIVE = 'admin';

    protected $fillable = ['label', 'groupe', 'niveau', 'exclut_administration'];

    protected $casts = ['exclut_administration' => 'boolean'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Accréditation à périmètre total (direction/admin). */
    public function estAccesTotal(): bool
    {
        return $this->groupe === '*';
    }

    /**
     * L'accréditation voit-elle les fiches du personnel administratif ?
     *
     * Les surveillants généraux ont un périmètre total sur le personnel
     * enseignant mais sont exclus de l'administration.
     */
    public function exclutAdministration(): bool
    {
        return (bool) $this->exclut_administration;
    }

    /** Une section donnée relève-t-elle de l'administration ? */
    public static function estSectionAdministrative(?string $section): bool
    {
        return str_starts_with(
            mb_strtolower(trim((string) $section)),
            self::PREFIXE_SECTION_ADMINISTRATIVE,
        );
    }
}
