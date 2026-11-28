<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Département d'un établissement d'enseignement technique (§bulletins APC).
 *
 * Aucune connexion n'est déclarée : le modèle suit la bascule de
 * TenantManager comme le reste du métier.
 */
class Departement extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'nom', 'ordre'];

    protected $casts = ['ordre' => 'integer'];

    public function specialites(): HasMany
    {
        return $this->hasMany(Specialite::class);
    }
}
