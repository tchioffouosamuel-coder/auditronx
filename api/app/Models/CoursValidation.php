<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoursValidation extends Model
{
    use HasFactory;

    protected $table = 'cours_validation';

    protected $fillable = ['enseignant_id', 'emploi_du_temps_id', 'date', 'status'];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function emploiDuTemps(): BelongsTo
    {
        return $this->belongsTo(EmploiDuTemps::class, 'emploi_du_temps_id');
    }
}
