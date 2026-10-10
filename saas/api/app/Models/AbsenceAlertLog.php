<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceAlertLog extends Model
{
    use HasFactory;

    protected $fillable = ['enseignant_id', 'absence_checkpoint_id', 'sent_at', 'canal'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function absenceCheckpoint(): BelongsTo
    {
        return $this->belongsTo(AbsenceCheckpoint::class);
    }
}
