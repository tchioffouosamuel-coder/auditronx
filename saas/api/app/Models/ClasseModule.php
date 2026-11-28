<?php

namespace App\Models;

use App\Services\BaremeApc;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne de la maquette d'une classe : un module du référentiel tel que
 * *cette* classe le suit cette année.
 *
 * C'est l'unité que parcourt un bulletin — ses notes s'y rattachent, et c'est
 * d'ici que sortent les colonnes crédit, volume horaire et signature.
 */
class ClasseModule extends Model
{
    use HasFactory;

    protected $table = 'classe_module';

    protected $fillable = [
        'classe_id',
        'module_id',
        'credit',
        'volume_horaire',
        'enseignant_id',
        'trimestres',
        'ordre',
    ];

    protected $casts = [
        'credit' => 'integer',
        'volume_horaire' => 'integer',
        'trimestres' => 'array',
        'ordre' => 'integer',
    ];

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /** L'enseignant dont les initiales figurent dans la colonne « SIGN » du bulletin. */
    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    /**
     * Tous les trimestres de l'année, dans l'ordre — la programmation par
     * défaut d'un module qu'on vient d'ajouter à une maquette.
     *
     * @return array<int, int>
     */
    public static function tousLesTrimestres(): array
    {
        return range(1, BaremeApc::nombreTrimestres());
    }

    /**
     * Les trimestres où ce module est programmé.
     *
     * `null` en base vaut « tous » et non « aucun » : une maquette dont
     * personne n'a encore détaillé la programmation doit produire un bulletin
     * complet, pas un bulletin vide.
     *
     * @return array<int, int>
     */
    public function trimestresProgrammes(): array
    {
        $trimestres = $this->trimestres;

        if (! is_array($trimestres)) {
            return self::tousLesTrimestres();
        }

        $trimestres = array_values(array_unique(array_map('intval', $trimestres)));
        sort($trimestres);

        return $trimestres;
    }

    public function estProgrammeAu(int $trimestre): bool
    {
        return in_array($trimestre, $this->trimestresProgrammes(), true);
    }

    /**
     * Un module non programmé reste sur le bulletin — le stage de deuxième
     * année y figure — mais hors des totaux : son crédit s'imprime entre
     * parenthèses et ne pèse ni sur la moyenne, ni sur le total d'heures.
     */
    public function compteDansLesTotaux(): bool
    {
        return $this->trimestresProgrammes() !== [];
    }
}
