<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presence extends Model
{
    use HasFactory;

    protected $fillable = [
        'enseignant_id', 'date', 'heure_arrivee', 'heure_depart',
        'source', 'access_point_id', 'device_id', 'recorded_by', 'on_behalf_of', 'reason',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'heure_arrivee' => 'datetime',
        'heure_depart' => 'datetime',
    ];

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
