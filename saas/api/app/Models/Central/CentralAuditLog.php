<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/** Entrée immuable du journal d'audit de la plateforme Auditron. */
class CentralAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'central';

    protected $table = 'central_audit_logs';

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
