<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CahierTexteEntree extends Model
{
    use HasFactory;

    protected $table = 'cahier_texte_entrees';

    protected $fillable = [
        'enseignant_id', 'emploi_du_temps_id', 'date', 'contenu', 'reference_programme', 'created_by',
    ];

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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
