<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signalement extends Model
{
    use HasFactory;

    protected $fillable = ['enseignant_id', 'date', 'motif', 'duree_jours'];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function couvre(Carbon $jour): bool
    {
        $debut = $this->date->copy()->startOfDay();
        $fin = $debut->copy()->addDays(max(1, (int) $this->duree_jours) - 1);

        return $jour->copy()->startOfDay()->betweenIncluded($debut, $fin);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }
}
