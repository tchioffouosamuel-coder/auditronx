<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

/**
 * Principal authentifiable côté device (app mobile) — distinct de User (backoffice).
 * Implémente Authenticatable pour un fonctionnement correct avec le guard Sanctum
 * (Sanctum::actingAs, Auth::user(), etc.), pas seulement la résolution de token brute.
 */
class Enseignant extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens, HasFactory, SoftDeletes;

    protected $fillable = [
        'nom', 'matricule', 'email', 'fonction', 'section', 'grade', 'tel', 'poste', 'rfid_uid',
        'password', 'est_admin', 'photo_path', 'photo_updated_at',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'hashed',
        'est_admin' => 'boolean',
        'photo_updated_at' => 'datetime',
    ];

    protected $appends = ['photo_url'];

    /** Photo de référence chargée depuis la plateforme web pour l'enrôlement facial (§5), ou null. */
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::disk('public_direct')->url($this->photo_path) : null;
    }

    public function activationRequests(): HasMany
    {
        return $this->hasMany(DeviceActivationRequest::class);
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    public function emploiDuTemps(): HasMany
    {
        return $this->hasMany(EmploiDuTemps::class);
    }

    public function signalements(): HasMany
    {
        return $this->hasMany(Signalement::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'teacher_id');
    }

    public function otps(): HasMany
    {
        return $this->hasMany(Otp::class, 'teacher_id');
    }

    public function cahierTexteEntrees(): HasMany
    {
        return $this->hasMany(CahierTexteEntree::class);
    }

    public function visageEmbedding(): HasOne
    {
        return $this->hasOne(VisageEmbedding::class)->whereNull('revoked_at');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(TeacherNotification::class);
    }
}
