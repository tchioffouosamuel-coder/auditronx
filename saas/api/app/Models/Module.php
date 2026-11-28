<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Module du référentiel APC : une compétence ou un enseignement d'une
 * spécialité, pour un niveau donné.
 *
 * Les quatre blocs sont ceux des quatre sections du bulletin de compétences,
 * et leur ordre est celui de l'impression — c'est pourquoi [BLOCS] est une
 * liste ordonnée et non un simple ensemble de valeurs permises.
 */
class Module extends Model
{
    use HasFactory;

    public const BLOC_COMPETENCES_PARTICULIERES = 'competences_particulieres';

    public const BLOC_COMPETENCES_GENERALES = 'competences_generales';

    public const BLOC_ENSEIGNEMENTS_GENERAUX = 'enseignements_generaux';

    public const BLOC_ENSEIGNEMENTS_COMPLEMENTAIRES = 'enseignements_complementaires';

    /**
     * Blocs dans l'ordre d'impression du bulletin, avec le titre de section
     * tel qu'il y figure.
     *
     * @var array<string, string>
     */
    public const BLOCS = [
        self::BLOC_COMPETENCES_PARTICULIERES => 'Modules des compétences particulières',
        self::BLOC_COMPETENCES_GENERALES => 'Modules des compétences générales',
        self::BLOC_ENSEIGNEMENTS_GENERAUX => 'Modules des enseignements généraux',
        self::BLOC_ENSEIGNEMENTS_COMPLEMENTAIRES => 'Modules des enseignements complémentaires',
    ];

    protected $fillable = [
        'specialite_id',
        'niveau',
        'code',
        'intitule',
        'bloc',
        'volume_horaire',
        'credit',
        'ordre',
        'discipline_id',
    ];

    protected $casts = [
        'volume_horaire' => 'integer',
        'credit' => 'integer',
        'ordre' => 'integer',
    ];

    public function specialite(): BelongsTo
    {
        return $this->belongsTo(Specialite::class);
    }

    /** Lien facultatif vers la matière de l'emploi du temps, quand il existe. */
    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    /** Rang du bloc dans l'ordre d'impression ; les blocs inconnus passent en dernier. */
    public static function rangBloc(?string $bloc): int
    {
        $rang = array_search($bloc, array_keys(self::BLOCS), true);

        return $rang === false ? count(self::BLOCS) : $rang;
    }

    public static function libelleBloc(?string $bloc): ?string
    {
        return $bloc === null ? null : (self::BLOCS[$bloc] ?? null);
    }

    /** Tri d'impression : par bloc, puis par l'ordre du référentiel, puis par code. */
    public function scopeOrdreBulletin($query)
    {
        return $query
            ->orderByRaw(self::triBlocSql())
            ->orderBy('ordre')
            ->orderBy('code');
    }

    /**
     * Tri par bloc exprimé en SQL : l'ordre d'impression n'est pas l'ordre
     * alphabétique des valeurs stockées (« competences_generales » précéderait
     * « competences_particulieres », ce que le bulletin ne fait pas).
     */
    private static function triBlocSql(): string
    {
        $cas = '';
        foreach (array_keys(self::BLOCS) as $rang => $bloc) {
            $cas .= " when '{$bloc}' then {$rang}";
        }

        return 'case bloc'.$cas.' else '.count(self::BLOCS).' end';
    }
}
