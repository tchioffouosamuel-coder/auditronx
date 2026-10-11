<?php

namespace Tests\Feature;

use App\Models\Central\Etablissement;
use App\Models\Central\Facture;
use App\Models\Central\Plan;
use App\Models\Central\PlatformUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CentralAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): PlatformUser
    {
        $admin = PlatformUser::create([
            'name' => 'Admin Auditron',
            'email' => 'admin@auditron.com',
            'password' => 'strong-pass-123',
            'role' => PlatformUser::ROLE_SUPER_ADMIN,
        ]);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_le_super_admin_peut_creer_un_compte_auditron_avec_un_mot_de_passe_initial_unique(): void
    {
        $this->admin();

        $response = $this->postJson('/api/central/utilisateurs', [
            'name' => 'Awa Support',
            'email' => 'awa@auditron.com',
            'role' => PlatformUser::ROLE_SUPPORT,
        ])->assertCreated();

        $password = $response->json('password_initial');
        $this->assertNotEmpty($password);
        $this->assertTrue(Hash::check($password, PlatformUser::where('email', 'awa@auditron.com')->firstOrFail()->password));
        $this->assertArrayNotHasKey('password', $response->json('data'));
    }

    public function test_le_support_ne_peut_pas_administrer_les_comptes_auditron(): void
    {
        Sanctum::actingAs(PlatformUser::create([
            'name' => 'Support',
            'email' => 'support@auditron.com',
            'password' => 'strong-pass-123',
            'role' => PlatformUser::ROLE_SUPPORT,
        ]));

        $this->getJson('/api/central/utilisateurs')->assertForbidden();
    }

    public function test_le_dernier_super_admin_actif_ne_peut_pas_etre_retrograde_ou_desactive(): void
    {
        $admin = $this->admin();

        $this->putJson("/api/central/utilisateurs/{$admin->id}", ['role' => PlatformUser::ROLE_SUPPORT])
            ->assertUnprocessable();
        $this->putJson("/api/central/utilisateurs/{$admin->id}", ['actif' => false])
            ->assertUnprocessable();
    }

    public function test_la_fiche_etablissement_peut_etre_modifiee_par_un_super_admin(): void
    {
        $this->admin();

        $this->putJson('/api/central/etablissements/' . Etablissement::firstOrFail()->id, [
            'contact_email' => 'direction@example.cm',
            'couleur_primaire' => '#0F766E',
        ])->assertOk();

        $this->assertDatabaseHas('etablissements', [
            'code' => $this->codeEtablissement,
            'contact_email' => 'direction@example.cm',
            'couleur_primaire' => '#0F766E',
        ], 'central');
    }

    public function test_une_facture_peut_etre_annulee_et_telechargee_en_pdf(): void
    {
        $this->admin();
        $facture = Facture::create([
            'etablissement_id' => Etablissement::firstOrFail()->id,
            'numero' => '2026-0001',
            'montant' => 25000,
            'devise' => 'XAF',
            'emise_le' => now()->toDateString(),
            'statut' => Facture::STATUT_EMISE,
        ]);

        $this->get("/api/central/factures/{$facture->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->postJson("/api/central/factures/{$facture->id}/annule")
            ->assertOk()
            ->assertJsonPath('data.statut', Facture::STATUT_ANNULEE);
    }

    public function test_une_facture_payee_ne_peut_pas_etre_annulee(): void
    {
        $this->admin();
        $facture = Facture::create([
            'etablissement_id' => Etablissement::firstOrFail()->id,
            'numero' => '2026-0002',
            'montant' => 25000,
            'devise' => 'XAF',
            'emise_le' => now()->toDateString(),
            'payee_le' => now()->toDateString(),
            'statut' => Facture::STATUT_PAYEE,
        ]);

        $this->postJson("/api/central/factures/{$facture->id}/annule")->assertUnprocessable();
    }

    public function test_les_indicateurs_historiques_du_tableau_de_bord_sont_exposes(): void
    {
        $this->admin();
        cache()->forget('plateforme:usage');
        cache()->forget('plateforme:historique-activite');

        $response = $this->getJson('/api/central/tableau-de-bord')->assertOk();

        $this->assertCount(6, $response->json('historique'));
        $this->assertArrayHasKey('pointages', $response->json('historique.5'));
        $this->assertArrayHasKey('factures_payees', $response->json('historique.5'));
        $this->assertArrayHasKey('etablissements_ouverts', $response->json('historique.5'));
    }
}
