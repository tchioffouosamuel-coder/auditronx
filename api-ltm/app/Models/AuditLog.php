<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Entrée du journal d'audit (§4.2).
 *
 * Écrit seulement, jamais modifié : il n'existe volontairement ni `update`
 * exposé ni route de suppression. Un journal qu'on peut réécrire ne prouve
 * plus rien.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'auteur_type', 'auteur_id', 'auteur_nom', 'auteur_email', 'auteur_accreditation',
        'action', 'sujet_type', 'sujet_id', 'sujet_libelle',
        'changements', 'contexte',
        'methode', 'url', 'route', 'ip', 'user_agent', 'statut',
    ];

    protected $casts = [
        'changements' => 'array',
        'contexte' => 'array',
        'created_at' => 'datetime',
    ];
}
