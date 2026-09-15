<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressionLecon extends Model
{
    use HasFactory;

    protected $table = 'progression_lecons';

    protected $fillable = [
        'programme_id',
        'trimestre',
        'semaine_numero',
        'periode',
        'unite_apprentissage',
        'unite_enseignement',
        'theorique',
        'pratique',
        'duree',
        'digitalisee',
        'ordre',
    ];

    protected $casts = [
        'theorique' => 'boolean',
        'pratique' => 'boolean',
        'digitalisee' => 'boolean',
    ];

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }
}
