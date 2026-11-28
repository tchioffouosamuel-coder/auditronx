<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inscription d'un élève dans une classe pour une année scolaire.
 *
 * C'est à elle que se rattachent les notes et les bulletins : un redoublant a
 * deux inscriptions, donc deux jeux de bulletins, et la seconde année
 * n'écrase pas la première.
 */
class Inscription extends Model
{
    public const STATUT_REGULIER = 'regulier';

    public const STATUT_REDOUBLANT = 'redoublant';

    /** Libellés imprimés : « Statut : (Régulier / Redoublant) ». */
    public const LIBELLES_STATUTS = [
        self::STATUT_REGULIER => 'Régulier',
        self::STATUT_REDOUBLANT => 'Redoublant',
    ];

    use HasFactory;

    protected $fillable = ['eleve_id', 'classe_id', 'annee_scolaire', 'statut', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /**
     * Les inscriptions qui comptent : celles d'une classe, pour une année,
     * et toujours actives.
     *
     * C'est le périmètre de l'effectif imprimé en en-tête du bulletin, et
     * celui des listes de saisie des notes. Un élève parti en cours d'année
     * garde ses bulletins mais ne doit plus apparaître dans une grille à
     * remplir.
     */
    public function scopeActivesDe(Builder $query, int $classeId, string $anneeScolaire): Builder
    {
        return $query
            ->where('classe_id', $classeId)
            ->where('annee_scolaire', $anneeScolaire)
            ->where('actif', true);
    }

    public function libelleStatut(): string
    {
        return self::LIBELLES_STATUTS[$this->statut] ?? $this->statut;
    }
}
