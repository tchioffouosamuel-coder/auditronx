<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ligne du moniteur série d'une borne relais (§hardware, diagnostic à distance). */
class DeviceLog extends Model
{
    /** Au-delà, les lignes sont purgées (diagnostic, pas un historique métier). */
    public const RETENTION_DAYS = 7;

    public const UPDATED_AT = null;

    protected $fillable = ['device_id', 'message', 'logged_at', 'uptime_ms'];

    protected $casts = [
        'logged_at' => 'datetime',
        'uptime_ms' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
