<?php

namespace App\Models;

use App\Services\BaremeApc;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bulletin de compétences d'une inscription, pour un trimestre ou pour
 * l'année (§bulletins APC, lot 5).
 *
 * Voir la migration pour les versions, la décision du conseil et l'empreinte.
 */
class Bulletin extends Model
{
    public const TYPE_TRIMESTRE = 'trimestre';

    public const TYPE_ANNUEL = 'annuel';

    /** Valeur de `periode` pour le bulletin annuel ; les trimestres valent « 1 », « 2 »… */
    public const PERIODE_ANNUELLE = 'annuel';

    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_VALIDE = 'valide';

    /** Réservé à l'impression (lot 6) : remis aux familles. */
    public const STATUT_PUBLIE = 'publie';

    protected $fillable = [
        'inscription_id',
        'classe_id',
        'annee_scolaire',
        'periode',
        'type',
        'trimestre',
        'version',
        'classe_code',
        'classe_nom',
        'statut_inscription',
        'moyenne_generale',
        'total_credits',
        'total_volume_horaire',
        'modules_notes',
        'competences_non_validees',
        'notes_manquantes',
        'mention',
        'validation_competences',
        'rang',
        'ex_aequo',
        'effectif',
        'effectif_classe',
        'position_groupe',
        'moyenne_classe',
        'moyenne_max',
        'moyenne_min',
        'decision_proposee',
        'decision_conseil',
        'decision_conseil_par',
        'decision_conseil_at',
        'observation',
        'statut',
        'empreinte',
        'bareme',
        'genere_at',
        'genere_par',
        'valide_at',
        'valide_par',
        'remplace_at',
        'remplace_par',
        'motif_reouverture',
    ];

    protected $casts = [
        'trimestre' => 'integer',
        'version' => 'integer',
        'moyenne_generale' => 'float',
        'total_credits' => 'integer',
        'total_volume_horaire' => 'integer',
        'modules_notes' => 'integer',
        'competences_non_validees' => 'integer',
        'notes_manquantes' => 'integer',
        'rang' => 'integer',
        'ex_aequo' => 'boolean',
        'effectif' => 'integer',
        'effectif_classe' => 'integer',
        'moyenne_classe' => 'float',
        'moyenne_max' => 'float',
        'moyenne_min' => 'float',
        'decision_conseil_at' => 'datetime',
        'bareme' => 'array',
        'genere_at' => 'datetime',
        'valide_at' => 'datetime',
        'remplace_at' => 'datetime',
    ];

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(BulletinLigne::class)->orderBy('ordre');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function decisionConseilPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_conseil_par');
    }

    /** Les bulletins en vigueur : ni remplacés par une version suivante, ni rouverts. */
    public function scopeCourants(Builder $query): Builder
    {
        return $query->whereNull('remplace_at');
    }

    /** Les bulletins d'un conseil de classe : une classe, une année, une période. */
    public function scopeDuConseil(Builder $query, int $classeId, string $anneeScolaire, string $periode): Builder
    {
        return $query
            ->where('classe_id', $classeId)
            ->where('annee_scolaire', $anneeScolaire)
            ->where('periode', $periode);
    }

    public function estAnnuel(): bool
    {
        return $this->type === self::TYPE_ANNUEL;
    }

    public function estValide(): bool
    {
        return $this->statut !== self::STATUT_BROUILLON;
    }

    /** Un bulletin auquel il manque une note n'est ni classé, ni validable. */
    public function estComplet(): bool
    {
        return $this->notes_manquantes === 0;
    }

    /** Ce que le bulletin imprime : la décision du conseil, à défaut celle du barème. */
    public function decisionRetenue(): ?string
    {
        return $this->decision_conseil ?? $this->decision_proposee;
    }

    /**
     * Décisions entre lesquelles le conseil peut choisir pour ce bulletin.
     *
     * @return array<int, string>
     */
    public function decisionsPossibles(): array
    {
        return self::decisionsDuType($this->type);
    }

    /** @return array<int, string> */
    public static function decisionsDuType(string $type): array
    {
        return $type === self::TYPE_ANNUEL
            ? [BaremeApc::DECISION_ADMIS, BaremeApc::DECISION_SOUS_RESERVE, BaremeApc::DECISION_REDOUBLEMENT]
            : [BaremeApc::DECISION_SUCCES, BaremeApc::DECISION_REMEDIATION, BaremeApc::DECISION_ECHEC];
    }

    /** « 1 » → type trimestre, trimestre 1 ; « annuel » → type annuel, sans trimestre. */
    public static function decoderPeriode(string $periode): array
    {
        return $periode === self::PERIODE_ANNUELLE
            ? ['type' => self::TYPE_ANNUEL, 'trimestre' => null]
            : ['type' => self::TYPE_TRIMESTRE, 'trimestre' => (int) $periode];
    }
}
