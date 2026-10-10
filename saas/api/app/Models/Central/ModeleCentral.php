<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/**
 * Base des modèles de la plateforme (annuaire commercial).
 *
 * La connexion est épinglée sur `central` : ces modèles doivent rester
 * lisibles même quand l'application est basculée sur la base d'un
 * établissement (un contrôleur locataire qui vérifie l'abonnement, par
 * exemple). Les modèles métier, eux, ne déclarent rien et suivent la
 * connexion par défaut — voir App\Tenancy\TenantManager.
 */
abstract class ModeleCentral extends Model
{
    protected $connection = 'central';
}
