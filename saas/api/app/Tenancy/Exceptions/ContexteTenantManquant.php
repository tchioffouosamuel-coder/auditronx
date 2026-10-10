<?php

namespace App\Tenancy\Exceptions;

use RuntimeException;

/**
 * Une opération métier a été tentée hors contexte établissement.
 *
 * Erreur franche plutôt que requête silencieuse sur la connexion centrale :
 * dans une architecture une-base-par-locataire, une requête métier sans
 * locataire est toujours un bug, jamais une valeur par défaut acceptable.
 */
class ContexteTenantManquant extends RuntimeException
{
    public function __construct(string $message = 'Aucun établissement actif pour cette requête.')
    {
        parent::__construct($message);
    }
}
