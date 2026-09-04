<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;

/** Principal authentifiable côté poste kiosque (reconnaissance faciale, §5.4). */
class Device extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens, HasFactory;

    protected $fillable = ['teacher_id', 'device_uuid', 'device_type', 'activated_at', 'otp_id', 'revoked_at'];

    protected $casts = [
        'activated_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class, 'teacher_id');
    }

    public function otp(): BelongsTo
    {
        return $this->belongsTo(Otp::class);
    }

    public function isRevoked(): bool
    {
        return ! is_null($this->revoked_at);
    }
}
