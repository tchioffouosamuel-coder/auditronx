<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande d'activation d'un enseignant non-admin (§4.1 revu) : créée à
 * l'identification (tel + mot de passe) lorsque l'enseignant n'a pas d'accès
 * direct ; l'administration génère alors un OTP à lui remettre en personne.
 */
class DeviceActivationRequest extends Model
{
    use HasFactory;

    protected $fillable = ['enseignant_id', 'device_uuid', 'device_type', 'requested_at', 'fulfilled_at', 'otp_id'];

    protected $casts = [
        'requested_at' => 'datetime',
        'fulfilled_at' => 'datetime',
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
