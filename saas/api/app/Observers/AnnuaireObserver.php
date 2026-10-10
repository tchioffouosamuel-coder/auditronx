<?php

namespace App\Observers;

use App\Models\Central\AnnuaireEntree;
use App\Models\Enseignant;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tient à jour l'index central « numéro → établissement » au fil des fiches
 * de personnel créées dans chaque base.
 *
 * Deux garde-fous :
 *
 *  - rien n'est écrit en clair : seul un haché HMAC du numéro part en base
 *    centrale (voir AnnuaireEntree::hachNumero) ;
 *  - un échec d'écriture centrale ne doit jamais faire échouer la création
 *    d'une fiche de personnel. L'annuaire est un confort de connexion, la
 *    fiche est la donnée métier : en cas de conflit, c'est la fiche qui gagne
 *    et l'incident part dans les logs.
 */
class AnnuaireObserver
{
    public function saved(Enseignant $enseignant): void
    {
        if (! config('auditron.annuaire.actif')) {
            return;
        }

        $etablissement = app(TenantManager::class)->courant();

        if (! $etablissement) {
            return;
        }

        try {
            // Numéro modifié : l'ancienne entrée ne doit pas survivre, sinon
            // l'annuaire renverrait l'établissement d'un numéro recyclé.
            if ($enseignant->wasChanged('tel')) {
                $this->oublie($enseignant->getOriginal('tel'), $etablissement->id);
            }

            $hash = AnnuaireEntree::hachNumero($enseignant->tel);

            if ($hash === null) {
                return;
            }

            AnnuaireEntree::updateOrCreate(
                ['tel_hash' => $hash, 'etablissement_id' => $etablissement->id],
                ['vu_le' => now()],
            );
        } catch (Throwable $e) {
            Log::warning('annuaire: synchronisation centrale impossible', [
                'etablissement' => $etablissement->code,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function deleted(Enseignant $enseignant): void
    {
        if (! config('auditron.annuaire.actif')) {
            return;
        }

        $etablissement = app(TenantManager::class)->courant();

        if (! $etablissement) {
            return;
        }

        try {
            $this->oublie($enseignant->tel, $etablissement->id);
        } catch (Throwable $e) {
            Log::warning('annuaire: suppression centrale impossible', [
                'etablissement' => $etablissement->code,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function oublie(?string $tel, int $etablissementId): void
    {
        $hash = AnnuaireEntree::hachNumero($tel);

        if ($hash === null) {
            return;
        }

        AnnuaireEntree::where('tel_hash', $hash)
            ->where('etablissement_id', $etablissementId)
            ->delete();
    }
}
