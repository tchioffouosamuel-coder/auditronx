<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inventaire central des bornes ESP32, par établissement.
 *
 * Le pointage, lui, reste intégralement dans la base de l'établissement
 * (`devices`, `presences`) : cette table ne sert qu'au parc matériel côté
 * éditeur — savoir quelle borne est chez quel client, quel firmware elle
 * porte, et quand elle s'est manifestée pour la dernière fois. C'est ce qui
 * permet de répondre « la borne 7 de LCM ne synchronise plus depuis mardi »
 * sans ouvrir la base du client.
 */
class Borne extends ModeleCentral
{
    protected $table = 'bornes';

    protected $fillable = [
        'etablissement_id',
        'device_uuid',
        'libelle',
        'emplacement',
        'firmware_version',
        'derniere_vue_le',
    ];

    protected $casts = [
        'derniere_vue_le' => 'datetime',
    ];

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /** Silencieuse depuis plus de 24 h : à signaler au support. */
    public function estSilencieuse(): bool
    {
        return $this->derniere_vue_le === null
            || $this->derniere_vue_le->lt(now()->subDay());
    }
}
