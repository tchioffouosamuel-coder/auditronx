<?php

namespace App\Console\Commands;

use App\Models\Central\Etablissement;
use Illuminate\Console\Command;

/**
 * Suspension / réactivation d'un abonné (non-paiement, fin de contrat).
 *
 * Un établissement suspendu reste intact — base, fichiers et abonnements ne
 * sont pas touchés — mais le middleware d'identification refuse ses requêtes
 * avec un 402, que le portail et l'app traduisent en message d'abonnement
 * échu. Le jour où le client règle, une réactivation suffit.
 */
class EtablissementStatut extends Command
{
    protected $signature = 'etablissement:statut
        {code : Code de l\'établissement}
        {statut : en_attente|actif|suspendu|archive}';

    protected $description = 'Change le statut d’un établissement (suspension, réactivation, archivage).';

    public function handle(): int
    {
        $code = Etablissement::normaliseCode($this->argument('code'));
        $statut = (string) $this->argument('statut');

        if (! in_array($statut, Etablissement::STATUTS, strict: true)) {
            $this->error('Statut inconnu. Valeurs : ' . implode(', ', Etablissement::STATUTS));

            return self::FAILURE;
        }

        $etablissement = Etablissement::where('code', $code)->first();

        if (! $etablissement) {
            $this->error("Établissement {$code} inconnu.");

            return self::FAILURE;
        }

        $etablissement->forceFill([
            'statut' => $statut,
            'suspendu_le' => $statut === Etablissement::STATUT_SUSPENDU ? now() : null,
        ])->save();

        $this->info("{$code} : statut passé à « {$statut} ».");

        return self::SUCCESS;
    }
}
