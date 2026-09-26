<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande d'activation d'un enseignant non-admin : créée à l'identification
 * (téléphone + mot de passe) et soumise à l'approbation de l'administration.
 */
class DeviceActivationRequest extends Model
{
    use HasFactory;

    protected $fillable = ['enseignant_id', 'device_uuid', 'device_type', 'fcm_token', 'requested_at', 'fulfilled_at', 'completed_at', 'rejected_at', 'otp_id'];

    protected $casts = [
        'requested_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'completed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function otp(): BelongsTo
    {
        return $this->belongsTo(Otp::class);
    }

    public function estEnAttente(): bool
    {
        return is_null($this->fulfilled_at);
    }
}
