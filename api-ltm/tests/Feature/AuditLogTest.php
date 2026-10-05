<?php

namespace Tests\Feature;

use App\Models\Accreditation;
use App\Models\AuditLog;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function accesTotal(): Accreditation
    {
        return Accreditation::create(['label' => 'Direction', 'groupe' => '*']);
    }

    private function actingAsBackoffice(?Accreditation $accreditation = null): User
    {
        $user = User::factory()->create([
            'name' => 'Mireille Direction',
            'accreditation_id' => ($accreditation ?? $this->accesTotal())->id,
        ]);
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    public function test_la_suppression_dun_enseignant_nomme_son_auteur(): void
    {
        $user = $this->actingAsBackoffice();
        $enseignant = Enseignant::factory()->create(['nom' => 'HAMASSAMBO MBELE BELL BLAISE']);

        $this->deleteJson("/api/personnel/{$enseignant->id}")->assertNoContent();

        $entree = AuditLog::where('action', 'enseignant.supprime')->sole();

        $this->assertSame($user->id, $entree->auteur_id);
        $this->assertSame('Mireille Direction', $entree->auteur_nom);
        $this->assertSame('Direction', $entree->auteur_accreditation);
        $this->assertSame('HAMASSAMBO MBELE BELL BLAISE', $entree->sujet_libelle);
        $this->assertSame($enseignant->id, $entree->sujet_id);
        $this->assertTrue($entree->contexte['suppression_logique']);
        $this->assertSame('DELETE', $entree->methode);
    }

    public function test_une_modification_conserve_la_valeur_avant_et_apres(): void
    {
        $this->actingAsBackoffice();
        $enseignant = Enseignant::factory()->create(['section' => 'Industrielle']);

        $this->putJson("/api/personnel/{$enseignant->id}", ['section' => 'STT'])->assertOk();

        $entree = AuditLog::where('action', 'enseignant.modifie')->sole();

        $this->assertSame('Industrielle', $entree->changements['section']['avant']);
        $this->assertSame('STT', $entree->changements['section']['apres']);
    }

    public function test_le_mot_de_passe_nest_jamais_recopie_en_clair(): void
    {
        $this->actingAsBackoffice();

        $this->postJson('/api/personnel', [
            'nom' => 'NOUVEAU VENU',
            'matricule' => 'TEST-001',
            'password' => 'SuperSecret123',
        ])->assertCreated();

        $journal = AuditLog::all()->toJson();

        $this->assertStringNotContainsString('SuperSecret123', $journal);
    }

    public function test_une_tentative_refusee_est_journalisee(): void
    {
        $restreinte = Accreditation::create(['label' => 'Censeur Industriel', 'groupe' => 'Industrielle']);
        $this->actingAsBackoffice($restreinte);

        $autreSection = Enseignant::factory()->create(['section' => 'STT']);

        $this->deleteJson("/api/personnel/{$autreSection->id}")->assertForbidden();

        $entree = AuditLog::where('action', 'personnel.refuse')->sole();

        $this->assertSame('Censeur Industriel', $entree->auteur_accreditation);
        $this->assertSame(403, (int) $entree->statut);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'enseignant.supprime']);
    }

    public function test_un_export_du_personnel_est_journalise(): void
    {
        $this->actingAsBackoffice();
        Enseignant::factory()->count(2)->create();

        $this->get('/api/spreadsheet/personnel/export')->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'spreadsheet.export.get']);
    }

    public function test_le_journal_est_refuse_a_une_accreditation_restreinte(): void
    {
        $restreinte = Accreditation::create(['label' => 'Censeur Général', 'groupe' => 'Générale']);
        $this->actingAsBackoffice($restreinte);

        $this->getJson('/api/audit-logs')->assertForbidden();
    }

    public function test_le_journal_est_refuse_a_un_surveillant_general(): void
    {
        // Groupe '*', donc accès total au personnel enseignant, mais le
        // journal reste hors de portée : il sert à contrôler ce rôle.
        $surveillant = Accreditation::create([
            'label' => 'Surveillant Général',
            'groupe' => '*',
            'exclut_administration' => true,
        ]);
        $this->actingAsBackoffice($surveillant);

        $this->getJson('/api/audit-logs')->assertForbidden();
    }

    public function test_le_journal_est_ouvert_a_un_acces_total(): void
    {
        $this->actingAsBackoffice();

        $this->getJson('/api/audit-logs')->assertOk()->assertJsonStructure(['data']);
    }

    public function test_une_connexion_refusee_laisse_une_trace_avec_son_adresse_ip(): void
    {
        User::factory()->create(['email' => 'direction@auditron.ltm']);

        $this->postJson('/api/login', [
            'email' => 'direction@auditron.ltm',
            'password' => 'mauvais-mot-de-passe',
        ])->assertStatus(422);

        $entree = AuditLog::where('action', 'login.refuse')->sole();

        $this->assertSame('anonyme', $entree->auteur_type);
        $this->assertNotNull($entree->ip);
        $this->assertSame('direction@auditron.ltm', $entree->contexte['donnees']['email']);
        $this->assertNotSame('mauvais-mot-de-passe', $entree->contexte['donnees']['password'] ?? null);
    }

    public function test_le_journal_se_filtre_par_auteur_et_par_action(): void
    {
        $this->actingAsBackoffice();
        $enseignant = Enseignant::factory()->create();
        $this->deleteJson("/api/personnel/{$enseignant->id}")->assertNoContent();

        $this->getJson('/api/audit-logs?action=enseignant.supprime')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/audit-logs?auteur=Mireille')
            ->assertOk()
            ->assertJsonPath('data.0.auteur_nom', 'Mireille Direction');

        $this->getJson('/api/audit-logs?auteur=Introuvable')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
