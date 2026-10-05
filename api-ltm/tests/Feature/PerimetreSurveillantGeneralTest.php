<?php

namespace Tests\Feature;

use App\Models\Accreditation;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un surveillant général voit tout le personnel enseignant, toutes sections
 * confondues, mais aucune fiche du personnel administratif.
 */
class PerimetreSurveillantGeneralTest extends TestCase
{
    use RefreshDatabase;

    private function surveillantGeneral(array $attributsUser = []): User
    {
        $accreditation = Accreditation::create([
            'label' => 'Surveillant Général',
            'groupe' => '*',
            'exclut_administration' => true,
        ]);

        $user = User::factory()->create([...$attributsUser, 'accreditation_id' => $accreditation->id]);
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    public function test_il_voit_toutes_les_sections_enseignantes(): void
    {
        $industrielle = Enseignant::factory()->create(['nom' => 'PROF INDUS', 'section' => 'Industrielle']);
        $generale = Enseignant::factory()->create(['nom' => 'PROF GENERAL', 'section' => 'Générale']);
        $stt = Enseignant::factory()->create(['nom' => 'PROF STT', 'section' => 'STT']);

        $this->surveillantGeneral();

        $noms = collect($this->getJson('/api/personnel')->assertOk()->json('data'))->pluck('nom');

        $this->assertEqualsCanonicalizing(
            [$industrielle->nom, $generale->nom, $stt->nom],
            $noms->all(),
        );
    }

    public function test_il_ne_voit_pas_le_personnel_administratif(): void
    {
        Enseignant::factory()->create(['nom' => 'CENSEUR', 'section' => 'Administration']);
        // Faute de frappe réellement présente en base : la comparaison par
        // préfixe doit l'attraper aussi.
        Enseignant::factory()->create(['nom' => 'SURVEILLANT GENERAL', 'section' => 'Adminstration']);
        $enseignant = Enseignant::factory()->create(['nom' => 'PROF', 'section' => 'Industrielle']);

        $this->surveillantGeneral();

        $this->getJson('/api/personnel')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nom', $enseignant->nom);
    }

    public function test_une_fiche_administrative_est_inaccessible_en_lecture_et_en_suppression(): void
    {
        $censeur = Enseignant::factory()->create(['section' => 'Administration']);

        $this->surveillantGeneral();

        $this->getJson("/api/personnel/{$censeur->id}")->assertForbidden();
        $this->deleteJson("/api/personnel/{$censeur->id}")->assertForbidden();

        $this->assertNotSoftDeleted($censeur);
    }

    public function test_il_ne_peut_pas_verser_une_fiche_dans_ladministration(): void
    {
        $this->surveillantGeneral();

        $this->postJson('/api/personnel', [
            'nom' => 'NOUVEAU',
            'matricule' => 'SG-001',
            'section' => 'Administration',
        ])->assertForbidden();

        $this->assertDatabaseMissing('enseignants', ['matricule' => 'SG-001']);
    }

    public function test_il_ne_peut_pas_deplacer_une_fiche_vers_ladministration(): void
    {
        $enseignant = Enseignant::factory()->create(['section' => 'Industrielle']);

        $this->surveillantGeneral();

        $this->putJson("/api/personnel/{$enseignant->id}", ['section' => 'Administration'])
            ->assertForbidden();

        $this->assertSame('Industrielle', $enseignant->fresh()->section);
    }

    public function test_il_garde_acces_a_sa_propre_fiche_meme_administrative(): void
    {
        $fiche = Enseignant::factory()->create(['nom' => 'MOI', 'section' => 'Administration']);

        $this->surveillantGeneral(['enseignant_id' => $fiche->id]);

        $this->getJson('/api/personnel')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nom', 'MOI');

        $this->getJson("/api/personnel/{$fiche->id}")->assertOk();
    }

    public function test_il_ne_peut_pas_lever_sa_propre_restriction(): void
    {
        $this->surveillantGeneral();

        $this->getJson('/api/accreditations')->assertForbidden();
        $this->postJson('/api/accreditations', ['label' => 'Tout voir', 'groupe' => '*'])
            ->assertForbidden();
    }

    public function test_une_accreditation_a_acces_total_voit_bien_ladministration(): void
    {
        Enseignant::factory()->create(['nom' => 'CENSEUR', 'section' => 'Administration']);
        Enseignant::factory()->create(['nom' => 'PROF', 'section' => 'Industrielle']);

        $direction = Accreditation::create(['label' => 'Direction', 'groupe' => '*']);
        $user = User::factory()->create(['accreditation_id' => $direction->id]);
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        $this->getJson('/api/personnel')->assertOk()->assertJsonCount(2, 'data');
    }
}
