<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Presence extends Model
{
    use HasFactory;

    protected $fillable = [
        'enseignant_id', 'date', 'heure_arrivee', 'heure_depart',
        'source', 'access_point_id', 'device_id', 'recorded_by', 'on_behalf_of', 'reason',
        'device_capture_at', 'photo_path_arrivee', 'photo_path_depart',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'heure_arrivee' => 'datetime',
        'heure_depart' => 'datetime',
        'device_capture_at' => 'datetime',
    ];

    protected $appends = ['photo_url_arrivee', 'photo_url_depart'];

    /** Photo prise par la borne au moment du scan d'arrivée (§hardware, ESP32-S3 + OV5640), ou null. */
    public function getPhotoUrlArriveeAttribute(): ?string
    {
        return $this->photo_path_arrivee ? Storage::disk('public_direct')->url($this->photo_path_arrivee) : null;
    }

    /** Photo prise par la borne au moment du scan de départ, ou null. */
    public function getPhotoUrlDepartAttribute(): ?string
    {
        return $this->photo_path_depart ? Storage::disk('public_direct')->url($this->photo_path_depart) : null;
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function accessPoint(): BelongsTo
    {
        return $this->belongsTo(AccessPoint::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** Utilisateur du backoffice ayant enregistré la présence (procuration/correction). */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** Enseignant pour le compte duquel la présence a été scannée par procuration. */
    public function onBehalfOf(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class, 'on_behalf_of');
    }
}
