<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbsenceCheckpoint extends Model
{
    use HasFactory;

    protected $fillable = ['enseignant_id', 'date', 'absences_consecutives'];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function alertLogs(): HasMany
    {
        return $this->hasMany(AbsenceAlertLog::class);
    }
}
