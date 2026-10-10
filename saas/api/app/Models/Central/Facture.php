<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Facture émise à un établissement pour un abonnement. */
class Facture extends ModeleCentral
{
    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_EMISE = 'emise';

    public const STATUT_PAYEE = 'payee';

    public const STATUT_ANNULEE = 'annulee';

    public const STATUTS = [
        self::STATUT_BROUILLON,
        self::STATUT_EMISE,
        self::STATUT_PAYEE,
        self::STATUT_ANNULEE,
    ];

    protected $table = 'factures';

    protected $fillable = [
        'etablissement_id',
        'abonnement_id',
        'numero',
        'montant',
        'devise',
        'emise_le',
        'echeance_le',
        'payee_le',
        'statut',
        'moyen_paiement',
        'reference_paiement',
        'notes',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'emise_le' => 'date',
        'echeance_le' => 'date',
        'payee_le' => 'date',
    ];

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class);
    }

    public function estEnRetard(): bool
    {
        return $this->statut === self::STATUT_EMISE
            && $this->echeance_le !== null
            && $this->echeance_le->endOfDay()->isPast();
    }
}
