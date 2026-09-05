<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmploiDuTemps extends Model
{
    use HasFactory;

    protected $table = 'emploi_du_temps';

    protected $fillable = [
        'enseignant_id', 'classe_id', 'discipline_id', 'jour', 'heure_debut', 'heure_fin', 'salle', 'type_cours',
    ];

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function coursValidations(): HasMany
    {
        return $this->hasMany(CoursValidation::class);
    }

    public function cahierTexteEntrees(): HasMany
    {
        return $this->hasMany(CahierTexteEntree::class);
    }
}
