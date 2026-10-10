<?php

namespace App\Console\Commands;

use App\Models\Central\Etablissement;
use App\Tenancy\ProvisionneurEtablissement;
use Illuminate\Console\Command;

/**
 * Suppression définitive d'un établissement : sa base, ses fichiers, sa ligne
 * centrale. Irréversible.
 *
 * Exige la saisie du code en clair, et non un simple oui/non : la commande est
 * exécutée sur un serveur où cohabitent les données de tous les clients, et la
 * seule protection qui tienne contre un mauvais copier-coller est de devoir
 * réécrire le nom de la victime. Pour un non-paiement, c'est
 * `etablissement:suspend` qu'il faut, pas celle-ci.
 */
class EtablissementSupprime extends Command
{
    protected $signature = 'etablissement:supprime
        {code : Code de l\'établissement à supprimer}
        {--confirmer= : Retaper le code pour confirmer (évite la question interactive)}';

    protected $description = 'Supprime définitivement un établissement, sa base et ses fichiers.';

    public function handle(ProvisionneurEtablissement $provisionneur): int
    {
        $code = Etablissement::normaliseCode($this->argument('code'));
        $etablissement = Etablissement::where('code', $code)->first();

        if (! $etablissement) {
            $this->error("Établissement {$code} inconnu.");

            return self::FAILURE;
        }

        $saisi = $this->option('confirmer')
            ?? $this->ask("Taper le code {$code} pour confirmer la suppression DÉFINITIVE de « {$etablissement->nom} »");

        if (Etablissement::normaliseCode($saisi) !== $code) {
            $this->warn('Confirmation incorrecte : rien n’a été supprimé.');

            return self::FAILURE;
        }

        $provisionneur->supprime($etablissement);
        $etablissement->delete();

        $this->info("Établissement {$code} supprimé (base, fichiers et ligne centrale).");

        return self::SUCCESS;
    }
}
