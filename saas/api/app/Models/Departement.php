<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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

    /** Mots qui ne donnent pas d'initiale : « Industrie de l'Habillement » -> « IH ». */
    private const MOTS_VIDES = ['DE', 'DU', 'DES', 'LA', 'LE', 'LES', 'L', 'D', 'ET', 'EN', 'AU', 'AUX', 'A'];

    /**
     * Code tiré du nom, l'utilisateur n'ayant à saisir que ce dernier : les
     * initiales des mots pleins (« Génie Électrique » -> « GE »), les trois
     * premières lettres d'un nom d'un seul mot (« Mécanique » -> « MEC »).
     * Un suffixe numérique départage deux noms aux mêmes initiales
     * (« Génie Énergétique » -> « GE2 »).
     */
    public static function genererCode(string $nom): string
    {
        $mots = array_values(array_filter(
            preg_split('/[^A-Z0-9]+/', strtoupper(Str::ascii($nom))) ?: [],
            fn (string $mot) => $mot !== '' && ! in_array($mot, self::MOTS_VIDES, true),
        ));

        $base = match (count($mots)) {
            0 => 'DEP',
            1 => substr($mots[0], 0, 3),
            default => implode('', array_map(fn (string $mot) => $mot[0], $mots)),
        };
        $base = substr($base, 0, 20);

        $code = $base;
        for ($n = 2; static::where('code', $code)->exists(); $n++) {
            $code = $base.$n;
        }

        return $code;
    }

    public function specialites(): HasMany
    {
        return $this->hasMany(Specialite::class);
    }
}
