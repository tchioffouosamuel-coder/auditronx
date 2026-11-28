<?php

namespace App\Models;

use App\Services\BaremeApc;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notes d'un élève sur un module, à un trimestre.
 *
 * `note_finale` est recalculée à chaque enregistrement, ici et nulle part
 * ailleurs : un appelant qui oublierait de le faire — un import, une commande,
 * un futur écran — laisserait en base une note finale en désaccord avec ses
 * deux composantes, c'est-à-dire un bulletin faux que rien ne signale.
 */
class NoteModule extends Model
{
    use HasFactory;

    protected $table = 'notes_modules';

    protected $fillable = [
        'inscription_id',
        'classe_module_id',
        'trimestre',
        'moyenne_uea',
        'note_integratrice',
        'appreciation',
        'saisie_par',
        'verrouillee_at',
    ];

    protected $casts = [
        'trimestre' => 'integer',
        'moyenne_uea' => 'float',
        'note_integratrice' => 'float',
        'note_finale' => 'float',
        'verrouillee_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (NoteModule $note): void {
            $note->note_finale = BaremeApc::noteFinaleTrimestre(
                $note->moyenne_uea,
                $note->note_integratrice,
            );
        });
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function ligneMaquette(): BelongsTo
    {
        return $this->belongsTo(ClasseModule::class, 'classe_module_id');
    }

    public function saisiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisie_par');
    }

    /** Une note verrouillée a servi à imprimer un bulletin validé. */
    public function estVerrouillee(): bool
    {
        return $this->verrouillee_at !== null;
    }

    /**
     * Les deux parts sont renseignées — donc la note finale existe.
     *
     * Une saisie à moitié faite n'est pas une note : l'épreuve intégratrice
     * manquante ne vaut pas zéro, elle n'a pas encore eu lieu.
     */
    public function estComplete(): bool
    {
        return $this->note_finale !== null;
    }

    /** @param array<int, int> $lignesMaquette */
    public function scopeDeLaClasse(Builder $query, array $lignesMaquette, int $trimestre): Builder
    {
        return $query->whereIn('classe_module_id', $lignesMaquette)->where('trimestre', $trimestre);
    }
}
