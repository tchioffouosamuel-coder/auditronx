<?php

namespace Tests\Feature;

use App\Models\Central\CentralAuditLog;
use App\Models\Central\PlatformUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsPlatformAdmin(): PlatformUser
    {
        $admin = PlatformUser::create([
            'name' => 'Admin Auditron',
            'email' => 'admin@auditronx.test',
            'password' => 'MotDePasseSecurise123',
            'role' => PlatformUser::ROLE_SUPER_ADMIN,
        ]);
        config(['database.default' => 'central']);
        DB::setDefaultConnection('central');
        $this->withToken($admin->createToken('plateforme')->plainTextToken);

        return $admin;
    }

    public function test_les_actions_de_la_plateforme_sont_journalisees_sans_le_mot_de_passe(): void
    {
        $admin = $this->actingAsPlatformAdmin();

        $this->postJson('/api/central/utilisateurs', [
            'name' => 'Nouvelle collègue',
            'email' => 'nouvelle@auditronx.test',
            'role' => 'support',
            'password' => 'SecretInitial987',
        ])->assertCreated();

        $entry = CentralAuditLog::where('action', 'central.utilisateurs.post')->sole();

        $this->assertSame($admin->id, $entry->auteur_id);
        $this->assertSame('platform_user', $entry->auteur_type);
        $this->assertSame('•••', $entry->contexte['donnees']['password']);
        $this->assertStringNotContainsString('SecretInitial987', $entry->toJson());
    }

    public function test_une_connexion_auditron_refusee_est_journalisee(): void
    {
        $this->postJson('/api/central/login', [
            'email' => 'absent@auditronx.test',
            'password' => 'MotDePasseSaisi123',
        ])->assertUnprocessable();

        $entry = CentralAuditLog::where('action', 'central.login.refuse')->sole();

        $this->assertSame('anonyme', $entry->auteur_type);
        $this->assertSame('•••', $entry->contexte['donnees']['password']);
        $this->assertStringNotContainsString('MotDePasseSaisi123', $entry->toJson());
    }

    public function test_une_connexion_reussie_est_attribuee_au_compte_auditron(): void
    {
        $admin = PlatformUser::create([
            'name' => 'Admin Auditron',
            'email' => 'admin@auditronx.test',
            'password' => 'MotDePasseSecurise123',
            'role' => PlatformUser::ROLE_SUPER_ADMIN,
        ]);

        $this->postJson('/api/central/login', [
            'email' => $admin->email,
            'password' => 'MotDePasseSecurise123',
        ])->assertOk();

        $entry = CentralAuditLog::where('action', 'central.login.post')->sole();
        $this->assertSame($admin->id, $entry->auteur_id);
        $this->assertSame($admin->email, $entry->auteur_email);
    }

    public function test_le_journal_central_est_lisible_uniquement_par_un_super_admin(): void
    {
        $this->actingAsPlatformAdmin();

        $this->getJson('/api/central/audit-logs')->assertOk()->assertJsonStructure(['data']);
    }
}
