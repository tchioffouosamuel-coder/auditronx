<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Élève (§bulletins APC) — l'identité seule : la classe, l'année et le statut
 * vivent dans [Inscription].
 */
class Eleve extends Model
{
    use HasFactory;

    public const SEXE_FEMININ = 'F';

    public const SEXE_MASCULIN = 'M';

    public const SEXES = [self::SEXE_FEMININ, self::SEXE_MASCULIN];

    protected $fillable = [
        'matricule',
        'matricule_national',
        'nom',
        'prenoms',
        'sexe',
        'date_naissance',
        'lieu_naissance',
        'telephone',
        'photo_path',
    ];

    protected $casts = ['date_naissance' => 'date'];

    protected $appends = ['nom_complet'];

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    /**
     * L'inscription d'une année donnée, celle en cours par défaut. Un élève
     * n'en a qu'une par année (clé naturelle de la table).
     */
    public function inscription(?string $anneeScolaire = null): HasOne
    {
        return $this->hasOne(Inscription::class)
            ->where('annee_scolaire', $anneeScolaire ?? \App\Services\AnneeScolaire::courante());
    }

    /**
     * Ce qu'imprime la ligne « Nom de l'Élève » du bulletin : le nom de
     * famille d'abord, comme sur les listes d'appel.
     */
    public function getNomCompletAttribute(): string
    {
        return trim($this->nom.' '.(string) $this->prenoms);
    }
}
