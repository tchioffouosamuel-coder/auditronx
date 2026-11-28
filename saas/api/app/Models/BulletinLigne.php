<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne de bulletin : l'instantané d'un module au moment de la génération.
 * Voir la migration pour la raison de la recopie.
 */
class BulletinLigne extends Model
{
    protected $table = 'bulletin_lignes';

    protected $fillable = [
        'bulletin_id',
        'classe_module_id',
        'module_id',
        'ordre',
        'code',
        'intitule',
        'bloc',
        'credit',
        'volume_horaire',
        'trimestres',
        'hors_totaux',
        'moyenne_uea',
        'note_integratrice',
        'notes_trimestres',
        'note_finale',
        'note_manquante',
        'mention',
        'sanction',
        'appreciation',
        'signataire',
    ];

    protected $casts = [
        'ordre' => 'integer',
        'credit' => 'integer',
        'volume_horaire' => 'integer',
        'trimestres' => 'array',
        'hors_totaux' => 'boolean',
        'moyenne_uea' => 'float',
        'note_integratrice' => 'float',
        'notes_trimestres' => 'array',
        'note_finale' => 'float',
        'note_manquante' => 'boolean',
    ];

    public function bulletin(): BelongsTo
    {
        return $this->belongsTo(Bulletin::class);
    }
}
