<?php

namespace Database\Seeders;

use App\Models\Accreditation;
use App\Models\Central\Etablissement;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Amorçage d'un établissement qui vient d'être provisionné : le minimum pour
 * que la direction puisse se connecter au portail et commencer à saisir son
 * personnel.
 *
 * Volontairement pauvre : aucune donnée de démonstration (ni enseignant, ni QR
 * de test, ni borne factice) ne doit se retrouver dans la base d'un vrai
 * client — c'est le rôle de DatabaseSeeder, réservé au développement.
 *
 * Doit être exécuté dans un contexte établissement (voir
 * App\Tenancy\ProvisionneurEtablissement).
 */
class TenantSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Accès total : voit l'ensemble du personnel, administration incluse.
        Accreditation::updateOrCreate(
            ['label' => 'Direction'],
            ['groupe' => '*', 'niveau' => null, 'exclut_administration' => false]
        );

        // Périmètre total sur le personnel enseignant, mais pas sur les fiches
        // administratives (voir Accreditation::exclutAdministration).
        Accreditation::updateOrCreate(
            ['label' => 'Surveillance générale'],
            ['groupe' => '*', 'niveau' => null, 'exclut_administration' => true]
        );
    }

    /**
     * Crée (ou remet à niveau) le compte de direction de l'établissement.
     *
     * Le mot de passe est tiré au hasard quand il n'est pas fourni : un mot de
     * passe commun à tous les clients provisionnés serait un défaut de
     * sécurité, pas un confort.
     *
     * @param  array{nom?: string, email?: string, password?: string}  $direction
     * @return array{email: string, password: string}
     */
    public function creeLaDirection(Etablissement $etablissement, array $direction = []): array
    {
        $accreditation = Accreditation::where('label', 'Direction')->firstOrFail();

        $email = $direction['email']
            ?? 'direction@' . strtolower($etablissement->code) . '.auditronx.com';
        $password = $direction['password'] ?? Str::password(14, symbols: false);

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $direction['nom'] ?? 'Direction ' . ($etablissement->nom_court ?: $etablissement->code),
                'password' => Hash::make($password),
                'accreditation_id' => $accreditation->id,
                'email_verified_at' => now(),
            ]
        );

        return ['email' => $email, 'password' => $password];
    }
}
