<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Spécialité de formation (SH, F2, MVT, F6-MIPE…), rattachée à un
 * département.
 */
class Specialite extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'nom', 'departement_id', 'cycle', 'actif', 'ordre'];

    protected $casts = ['actif' => 'boolean', 'ordre' => 'integer'];

    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }

    /**
     * Niveaux pour lesquels un référentiel a été importé. Sert aux filtres des
     * écrans : proposer « Première » à un établissement qui n'a saisi que la
     * Seconde n'aide personne.
     *
     * @return array<int, string>
     */
    public function niveaux(): array
    {
        return $this->modules()->distinct()->orderBy('niveau')->pluck('niveau')->all();
    }
}
