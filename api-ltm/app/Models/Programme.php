<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\ProgressionLecon;

class Programme extends Model
{
    use HasFactory;

    protected $fillable = ['discipline_id', 'classe_id', 'annee_scolaire', 'nb_seances_prevues'];

    public function lecons(): HasMany
    {
        return $this->hasMany(ProgressionLecon::class);
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }
}
