<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Abonnement d'un établissement à un plan, sur une période donnée. */
class Abonnement extends ModeleCentral
{
    public const STATUT_ACTIF = 'actif';

    public const STATUT_EXPIRE = 'expire';

    public const STATUT_RESILIE = 'resilie';

    public const STATUTS = [self::STATUT_ACTIF, self::STATUT_EXPIRE, self::STATUT_RESILIE];

    protected $table = 'abonnements';

    protected $fillable = [
        'etablissement_id',
        'plan_id',
        'debut_le',
        'fin_le',
        'statut',
        'montant',
        'devise',
        'periodicite',
        'notes',
    ];

    protected $casts = [
        'debut_le' => 'date',
        'fin_le' => 'date',
        'montant' => 'decimal:2',
    ];

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    public function estEnCours(): bool
    {
        if ($this->statut !== self::STATUT_ACTIF) {
            return false;
        }

        if ($this->debut_le && $this->debut_le->isAfter(now())) {
            return false;
        }

        return $this->fin_le === null || $this->fin_le->endOfDay()->isFuture();
    }

    /** Jours restants avant échéance, null si abonnement sans fin. */
    public function joursRestants(): ?int
    {
        return $this->fin_le ? now()->startOfDay()->diffInDays($this->fin_le, false) : null;
    }
}
