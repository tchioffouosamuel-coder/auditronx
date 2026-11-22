<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmploiDuTemps extends Model
{
    use HasFactory;

    /**
     * Jours de la semaine, tels qu'ils s'écrivent dans les classeurs
     * d'import/export de l'emploi du temps. La colonne `jour` reste un entier
     * en base et dans l'API JSON ; seuls les fichiers parlent en clair, parce
     * qu'un numéro de 1 à 7 obligeait l'utilisateur à retenir une convention.
     */
    public const NOMS_JOURS = [
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
        7 => 'Dimanche',
    ];

    public const HEURES_DEBUT = [
        '07:30',
        '08:10',
        '08:50',
        '09:30',
        '10:10',
        '10:20',
        '11:00',
        '11:40',
        '12:00',
        '12:40',
        '13:20',
        '13:30',
        '14:10',
        '14:50',
        '15:30',
    ];

    public const HEURES_FIN = [
        '08:10',
        '08:50',
        '09:30',
        '10:10',
        '10:20',
        '11:00',
        '11:40',
        '12:00',
        '12:40',
        '13:20',
        '13:30',
        '14:10',
        '14:50',
        '15:30',
        '16:10',
    ];

    protected $table = 'emploi_du_temps';

    protected $fillable = [
        'enseignant_id',
        'classe_id',
        'discipline_id',
        'jour',
        'heure_debut',
        'heure_fin',
        'salle',
        'type_cours',
    ];

    /** « Lundi » pour 1, null si le numéro n'est pas un jour de la semaine. */
    public static function nomJour(mixed $jour): ?string
    {
        return self::NOMS_JOURS[(int) $jour] ?? null;
    }

    /**
     * Numéro de jour depuis ce qu'écrit l'utilisateur : « Lundi », « lundi »,
     * « MARDI », « mer. ». Les anciens classeurs portant un numéro de 1 à 7
     * restent acceptés, sinon un fichier déjà rempli deviendrait illisible.
     * Renvoie null si la valeur ne désigne aucun jour.
     */
    public static function jourDepuisLibelle(mixed $valeur): ?int
    {
        // Espaces et points sautés : « mer. » et « Mer » désignent le mercredi.
        $texte = preg_replace('/[\s.]+/u', '', mb_strtolower(trim((string) $valeur), 'UTF-8')) ?? '';

        if ($texte === '') {
            return null;
        }

        if (ctype_digit($texte)) {
            return isset(self::NOMS_JOURS[(int) $texte]) ? (int) $texte : null;
        }

        foreach (self::NOMS_JOURS as $numero => $nom) {
            $nom = mb_strtolower($nom, 'UTF-8');

            if ($texte === $nom || $texte === mb_substr($nom, 0, 3, 'UTF-8')) {
                return $numero;
            }
        }

        return null;
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function coursValidations(): HasMany
    {
        return $this->hasMany(CoursValidation::class);
    }

    public function cahierTexteEntrees(): HasMany
    {
        return $this->hasMany(CahierTexteEntree::class);
    }

    public function leconsRealisees(): HasMany
    {
        return $this->hasMany(LeconRealisee::class);
    }
}
