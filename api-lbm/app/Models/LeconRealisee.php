<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\ProgressionLecon;

class LeconRealisee extends Model
{
    use HasFactory;

    protected $table = 'lecons_realisees';

    protected $fillable = ['enseignant_id', 'emploi_du_temps_id', 'progression_lecon_id', 'date'];

    protected $casts = ['date' => 'date:Y-m-d'];

    public function lecon(): BelongsTo
    {
        return $this->belongsTo(ProgressionLecon::class, 'progression_lecon_id');
    }
}
