<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Index « numéro de téléphone → établissement », pour l'enseignant qui a
 * perdu son code d'établissement et n'a que son téléphone sous la main.
 *
 * Seul un haché HMAC du numéro normalisé est stocké : la table centrale ne
 * permet donc pas de reconstituer l'annuaire téléphonique des clients, mais
 * répond parfaitement à la question « ce numéro-là appartient à quel
 * établissement ? ». Alimentée par App\Observers\AnnuaireObserver, et donc
 * toujours en retard d'un import effectué en base directement — c'est un
 * confort de connexion, jamais une source de vérité.
 */
class AnnuaireEntree extends ModeleCentral
{
    protected $table = 'annuaire_entrees';

    protected $fillable = ['tel_hash', 'etablissement_id', 'vu_le'];

    protected $casts = ['vu_le' => 'datetime'];

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    /**
     * Normalise puis hache un numéro. La normalisation (chiffres seuls, 9
     * derniers) absorbe les variantes de saisie : `+237 6 99 00 11 22`,
     * `699001122`, `00237699001122` donnent la même entrée.
     */
    public static function hachNumero(?string $tel): ?string
    {
        $chiffres = preg_replace('/\D+/', '', (string) $tel);

        if ($chiffres === null || strlen($chiffres) < 6) {
            return null;
        }

        $noyau = substr($chiffres, -9);

        return hash_hmac('sha256', $noyau, (string) config('auditron.annuaire.sel', ''));
    }
}
