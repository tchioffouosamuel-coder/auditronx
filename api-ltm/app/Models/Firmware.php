<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Firmware OTA d'une borne relais (§hardware) — voir FirmwareService. */
class Firmware extends Model
{
    protected $table = 'firmwares';

    protected $fillable = [
        'device_id',
        'version',
        'path',
        'sha256',
        'size_bytes',
        'release_notes',
        'is_active',
        'published_at',
        'uploaded_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'size_bytes' => 'integer',
    ];

    protected $hidden = ['path'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
