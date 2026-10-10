<?php

namespace App\Console\Commands;

use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Relie chaque compte admin (`User`) à sa fiche enseignant (`Enseignant`) par
 * correspondance d'email, quand elle existe et n'est pas déjà renseignée.
 *
 * Sans ce lien, un admin qui a aussi une fiche enseignant ne peut jamais
 * scanner sa propre présence (§admin-mobile) : RelaySyncController rejette le
 * paquet ("Token enseignant invalide.") faute d'Enseignant associé au token
 * User. `LegacyImportSeeder` n'a jamais renseigné `enseignant_id` à l'import ;
 * cette commande corrige les comptes existants et peut être rejouée après un
 * réimport.
 */
class LinkUsersToEnseignants extends Command
{
    protected $signature = 'auditron:link-users-enseignants';

    protected $description = 'Relie les comptes admin sans enseignant_id à la fiche enseignant de même email.';

    public function handle(): int
    {
        $users = User::whereNull('enseignant_id')->whereNotNull('email')->get();
        $linked = 0;

        foreach ($users as $user) {
            $enseignant = Enseignant::where('email', $user->email)->first();

            if (! $enseignant) {
                continue;
            }

            $user->update(['enseignant_id' => $enseignant->id]);
            $linked++;
            $this->line("{$user->email} -> enseignant #{$enseignant->id}");
        }

        $this->info("{$linked} compte(s) lié(s) sur {$users->count()} sans enseignant_id.");

        return self::SUCCESS;
    }
}
