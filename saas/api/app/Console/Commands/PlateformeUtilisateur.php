<?php

namespace App\Console\Commands;

use App\Models\Central\PlatformUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

/**
 * Crée ou met à jour un compte du personnel Auditron (portail éditeur).
 *
 *   php artisan plateforme:utilisateur "Awa N." awa@auditronx.com --role=support
 */
class PlateformeUtilisateur extends Command
{
    protected $signature = 'plateforme:utilisateur
        {nom}
        {email}
        {--role=support : super_admin|support|commercial}
        {--password= : Mot de passe imposé (sinon tiré au hasard et affiché)}
        {--desactive : Créer le compte désactivé}';

    protected $description = 'Crée ou met à jour un compte du personnel Auditron.';

    public function handle(): int
    {
        $validation = Validator::make([
            'email' => $this->argument('email'),
            'role' => $this->option('role'),
        ], [
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in(PlatformUser::ROLES)],
        ]);

        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $erreur) {
                $this->error($erreur);
            }

            return self::FAILURE;
        }

        $password = $this->option('password') ?: Str::password(14, symbols: false);

        $utilisateur = PlatformUser::updateOrCreate(
            ['email' => $this->argument('email')],
            [
                'name' => $this->argument('nom'),
                'password' => Hash::make($password),
                'role' => $this->option('role'),
                'actif' => ! $this->option('desactive'),
                'email_verified_at' => now(),
            ]
        );

        $this->info("Compte {$utilisateur->email} ({$utilisateur->role}) enregistré.");

        if (! $this->option('password')) {
            $this->line("Mot de passe : {$password}  (affiché une seule fois)");
        }

        return self::SUCCESS;
    }
}
