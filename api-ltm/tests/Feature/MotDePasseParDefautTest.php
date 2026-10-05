<?php

namespace Tests\Feature;

use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Toute fiche créée sans mot de passe reçoit celui d'amorçage : sans lui,
 * l'activation de l'app mobile refuse la fiche et l'enseignant lit
 * « Identifiants invalides » sans comprendre pourquoi.
 */
class MotDePasseParDefautTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsBackoffice(): User
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    private function assertMotDePasseParDefaut(Enseignant $enseignant): void
    {
        $this->assertNotNull($enseignant->password);
        $this->assertTrue(
            Hash::check(Enseignant::MOT_DE_PASSE_PAR_DEFAUT, $enseignant->password),
        );
    }

    public function test_une_creation_sans_mot_de_passe_recoit_celui_par_defaut(): void
    {
        $this->actingAsBackoffice();

        $cree = $this->postJson('/api/personnel', [
            'nom' => 'AYOMBO FRANCK OCTAVE',
            'matricule' => '26SVT02',
        ])->assertCreated()->json();

        $this->assertMotDePasseParDefaut(Enseignant::find($cree['id']));
    }

    public function test_un_mot_de_passe_saisi_est_conserve(): void
    {
        $this->actingAsBackoffice();

        $cree = $this->postJson('/api/personnel', [
            'nom' => 'CHOISI',
            'matricule' => 'TEST-002',
            'password' => 'SonPropreSecret1',
        ])->assertCreated()->json();

        $enseignant = Enseignant::find($cree['id']);

        $this->assertTrue(Hash::check('SonPropreSecret1', $enseignant->password));
        $this->assertFalse(
            Hash::check(Enseignant::MOT_DE_PASSE_PAR_DEFAUT, $enseignant->password),
        );
    }

    public function test_un_compte_administrateur_exige_toujours_un_mot_de_passe(): void
    {
        $this->actingAsBackoffice();

        // Le mot de passe est recopié sur un compte backoffice : lui attribuer
        // une valeur publique ouvrirait l'administration à qui la connaît.
        $this->postJson('/api/personnel', [
            'nom' => 'FUTUR ADMIN',
            'matricule' => 'TEST-003',
            'email' => 'futur.admin@auditron.ltm',
            'est_admin' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->assertDatabaseMissing('enseignants', ['matricule' => 'TEST-003']);
    }

    public function test_une_modification_sans_mot_de_passe_ne_change_rien(): void
    {
        $enseignant = Enseignant::factory()->create(['password' => 'AncienSecret1']);
        $this->actingAsBackoffice();

        $this->putJson("/api/personnel/{$enseignant->id}", ['fonction' => 'Censeur'])
            ->assertOk();

        $this->assertTrue(Hash::check('AncienSecret1', $enseignant->fresh()->password));
    }

    public function test_limport_json_attribue_le_mot_de_passe_par_defaut(): void
    {
        $this->actingAsBackoffice();

        $this->postJson('/api/personnel/import', [
            'enseignants' => [
                ['nom' => 'IMPORTE UN', 'matricule' => 'IMP-001'],
                ['nom' => 'IMPORTE DEUX', 'matricule' => 'IMP-002'],
            ],
        ])->assertCreated();

        foreach (['IMP-001', 'IMP-002'] as $matricule) {
            $this->assertMotDePasseParDefaut(Enseignant::where('matricule', $matricule)->sole());
        }
    }

    public function test_une_fiche_creee_peut_activer_lapp_mobile(): void
    {
        $this->actingAsBackoffice();

        $this->postJson('/api/personnel', [
            'nom' => 'NOUVEAU',
            'matricule' => 'TEST-004',
            'tel' => '699000111',
        ])->assertCreated();

        // Sans mot de passe, cette requête répondait « Identifiants invalides ».
        $this->postJson('/api/devices/request-activation', [
            'tel' => '699000111',
            'password' => Enseignant::MOT_DE_PASSE_PAR_DEFAUT,
            'device_uuid' => 'test-device-0001',
        ])->assertSuccessful();
    }
}
