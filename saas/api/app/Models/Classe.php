<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classe extends Model
{
    use HasFactory;

    protected $fillable = ['nom', 'code', 'niveau', 'specialite', 'specialite_id', 'effectif'];

    public function emploiDuTemps(): HasMany
    {
        return $this->hasMany(EmploiDuTemps::class);
    }

    public function programmes(): HasMany
    {
        return $this->hasMany(Programme::class);
    }

    /** Spécialité du référentiel APC suivie par la classe. */
    public function specialiteApc(): BelongsTo
    {
        return $this->belongsTo(Specialite::class, 'specialite_id');
    }

    /**
     * Maquette de la classe : les modules qu'elle suit, avec leurs crédits,
     * leurs heures et les trimestres où ils sont programmés. C'est elle que
     * parcourt un bulletin, et non le référentiel de la spécialité.
     */
    public function maquette(): HasMany
    {
        return $this->hasMany(ClasseModule::class);
    }
}
