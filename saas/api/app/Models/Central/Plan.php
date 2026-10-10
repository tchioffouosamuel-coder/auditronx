<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** Offre commerciale : quotas et fonctionnalités d'un abonnement. */
class Plan extends ModeleCentral
{
    protected $table = 'plans';

    protected $fillable = [
        'code',
        'nom',
        'description',
        'prix',
        'devise',
        'periodicite',
        'max_personnel',
        'max_bornes',
        'fonctionnalites',
        'actif',
    ];

    protected $casts = [
        'prix' => 'decimal:2',
        'fonctionnalites' => 'array',
        'actif' => 'boolean',
    ];

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }

    /** `max_personnel`/`max_bornes` à null = illimité. */
    public function quotaDepasse(?int $valeur, ?int $maximum): bool
    {
        return $maximum !== null && $valeur !== null && $valeur > $maximum;
    }

    public function aFonctionnalite(string $cle): bool
    {
        return in_array($cle, $this->fonctionnalites ?? [], strict: true);
    }
}
