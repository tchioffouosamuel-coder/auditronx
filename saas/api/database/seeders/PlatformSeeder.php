<?php

namespace Database\Seeders;

use App\Models\Central\Plan;
use App\Models\Central\PlatformUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Amorçage de la base centrale : le compte éditeur initial et le catalogue
 * d'offres de départ. À jouer une fois, à l'installation de la plateforme.
 *
 * Idempotent (updateOrCreate partout), donc rejouable sans dupliquer. Le mot
 * de passe du compte initial est volontairement connu et faible : il est à
 * changer à la première connexion, comme pour l'amorçage d'un établissement.
 */
class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $admin = PlatformUser::updateOrCreate(
            ['email' => env('AUDITRON_ADMIN_EMAIL', 'admin@auditronx.com')],
            [
                'name' => 'Super administrateur',
                'password' => Hash::make(env('AUDITRON_ADMIN_PASSWORD', 'ChangeMe123!')),
                'role' => PlatformUser::ROLE_SUPER_ADMIN,
                'actif' => true,
                'email_verified_at' => now(),
            ]
        );

        // Trois paliers calés sur la taille de l'établissement : le quota de
        // bornes est la vraie variable de coût (matériel + support), celui du
        // personnel la vraie variable de valeur.
        $plans = [
            [
                'code' => 'essai',
                'nom' => 'Essai',
                'description' => 'Découverte limitée dans le temps, une borne, un seul site.',
                'prix' => 0,
                'periodicite' => 'mensuel',
                'max_personnel' => 25,
                'max_bornes' => 1,
                'fonctionnalites' => ['pointage_qr', 'journal_assiduite'],
            ],
            [
                'code' => 'standard',
                'nom' => 'Standard',
                'description' => 'Un établissement de taille moyenne : pointage, assiduité, rapports.',
                'prix' => 75000,
                'periodicite' => 'annuel',
                'max_personnel' => 120,
                'max_bornes' => 3,
                'fonctionnalites' => [
                    'pointage_qr', 'pointage_borne', 'journal_assiduite',
                    'rapports_pdf', 'cahier_texte', 'notifications_push',
                ],
            ],
            [
                'code' => 'premium',
                'nom' => 'Premium',
                'description' => 'Grand établissement : personnel et bornes illimités, audit complet.',
                'prix' => 150000,
                'periodicite' => 'annuel',
                'max_personnel' => null,
                'max_bornes' => null,
                'fonctionnalites' => [
                    'pointage_qr', 'pointage_borne', 'journal_assiduite',
                    'rapports_pdf', 'cahier_texte', 'notifications_push',
                    'journal_audit', 'ota_bornes', 'export_zip',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['code' => $plan['code']],
                [...$plan, 'devise' => 'XAF', 'actif' => true]
            );
        }

        $this->command?->info('--- Plateforme amorcée ---');
        $this->command?->info("Compte éditeur : {$admin->email}");
        $this->command?->info('Plans : ' . implode(', ', array_column($plans, 'code')));
    }
}
