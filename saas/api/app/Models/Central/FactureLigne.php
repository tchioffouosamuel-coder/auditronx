<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ligne d'une facture (libellé, montant) — voir Facture::lignes(). */
class FactureLigne extends ModeleCentral
{
    protected $table = 'facture_lignes';

    protected $fillable = [
        'facture_id',
        'libelle',
        'montant',
        'ordre',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }
}
