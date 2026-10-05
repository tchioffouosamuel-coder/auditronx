<?php

namespace Tests\Feature;

use App\Models\Accreditation;
use App\Models\Classe;
use App\Models\Discipline;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Une matière relève d'un département : seule une accréditation couvrant ce
 * département peut la consulter, la modifier et l'attribuer à un enseignant.
 */
class PerimetreDisciplinesTest extends TestCase
{
    use RefreshDatabase;

    private function censeur(string $groupe = 'Industrielle'): User
    {
        $accreditation = Accreditation::create([
            'label' => "Censeur {$groupe}",
            'groupe' => $groupe,
        ]);

        $user = User::factory()->create(['accreditation_id' => $accreditation->id]);
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    private function direction(): User
    {
        $accreditation = Accreditation::create(['label' => 'Direction', 'groupe' => '*']);
        $user = User::factory()->create(['accreditation_id' => $accreditation->id]);
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    /** Crée un créneau dont l'enseignant et la matière relèvent de $section. */
    private function creneau(string $section): EmploiDuTemps
    {
        return EmploiDuTemps::create([
            'enseignant_id' => Enseignant::factory()->create(['section' => $section])->id,
            'classe_id' => Classe::factory()->create()->id,
            'discipline_id' => Discipline::factory()->create(['departement' => $section])->id,
            'jour' => 1,
            'heure_debut' => '08:00',
            'heure_fin' => '09:00',
        ]);
    }

    public function test_il_ne_liste_que_son_departement_et_les_matieres_transversales(): void
    {
        Discipline::factory()->create(['nom' => 'AUTOMATISME', 'departement' => 'Industrielle']);
        Discipline::factory()->create(['nom' => 'ANGLAIS', 'departement' => 'Générale']);
        Discipline::factory()->create(['nom' => 'BUREAUTIQUE', 'departement' => 'STT']);
        Discipline::factory()->create(['nom' => 'TRANSVERSALE', 'departement' => null]);

        $this->censeur();

        $noms = collect($this->getJson('/api/disciplines')->assertOk()->json('data'))->pluck('nom');

        $this->assertEqualsCanonicalizing(['AUTOMATISME', 'TRANSVERSALE'], $noms->all());
    }

    public function test_il_ne_peut_pas_lire_une_matiere_dun_autre_departement(): void
    {
        $anglais = Discipline::factory()->create(['departement' => 'Générale']);

        $this->censeur();

        $this->getJson("/api/disciplines/{$anglais->id}")->assertForbidden();
    }

    public function test_il_ne_peut_ni_modifier_ni_supprimer_une_matiere_dun_autre_departement(): void
    {
        $anglais = Discipline::factory()->create(['nom' => 'ANGLAIS', 'departement' => 'Générale']);

        $this->censeur();

        $this->putJson("/api/disciplines/{$anglais->id}", ['nom' => 'DETOURNEE'])->assertForbidden();
        $this->deleteJson("/api/disciplines/{$anglais->id}")->assertForbidden();

        $this->assertSame('ANGLAIS', $anglais->fresh()->nom);
    }

    public function test_il_ne_peut_pas_creer_une_matiere_hors_de_son_departement(): void
    {
        $this->censeur();

        $this->postJson('/api/disciplines', [
            'nom' => 'PHILOSOPHIE',
            'code' => 'PHILO',
            'departement' => 'Générale',
        ])->assertForbidden();

        $this->assertDatabaseMissing('disciplines', ['code' => 'PHILO']);
    }

    public function test_il_ne_peut_pas_deplacer_une_matiere_vers_un_autre_departement(): void
    {
        $automatisme = Discipline::factory()->create(['departement' => 'Industrielle']);

        $this->censeur();

        $this->putJson("/api/disciplines/{$automatisme->id}", ['departement' => 'STT'])
            ->assertForbidden();

        $this->assertSame('Industrielle', $automatisme->fresh()->departement);
    }

    public function test_il_gere_bien_les_matieres_de_son_departement(): void
    {
        $this->censeur();

        $creee = $this->postJson('/api/disciplines', [
            'nom' => 'SOUDURE',
            'code' => 'SOUD',
            'departement' => 'Industrielle',
        ])->assertCreated()->json();

        $this->putJson("/api/disciplines/{$creee['id']}", ['coefficient' => 3])->assertOk();
        $this->deleteJson("/api/disciplines/{$creee['id']}")->assertNoContent();
    }

    public function test_il_ne_peut_pas_attribuer_une_matiere_dun_autre_departement(): void
    {
        $enseignant = Enseignant::factory()->create(['section' => 'Industrielle']);
        $classe = Classe::factory()->create();
        $anglais = Discipline::factory()->create(['departement' => 'Générale']);

        $this->censeur();

        $this->postJson('/api/emplois', [
            'enseignant_id' => $enseignant->id,
            'classe_id' => $classe->id,
            'discipline_id' => $anglais->id,
            'jour' => 1,
            'heure_debut' => '08:00',
            'heure_fin' => '09:00',
        ])->assertForbidden();

        $this->assertDatabaseCount('emploi_du_temps', 0);
    }

    public function test_il_ne_peut_pas_attribuer_un_cours_a_un_enseignant_hors_perimetre(): void
    {
        $autreSection = Enseignant::factory()->create(['section' => 'STT']);
        $classe = Classe::factory()->create();
        $automatisme = Discipline::factory()->create(['departement' => 'Industrielle']);

        $this->censeur();

        $this->postJson('/api/emplois', [
            'enseignant_id' => $autreSection->id,
            'classe_id' => $classe->id,
            'discipline_id' => $automatisme->id,
            'jour' => 1,
            'heure_debut' => '08:00',
            'heure_fin' => '09:00',
        ])->assertForbidden();
    }

    public function test_il_attribue_bien_une_matiere_de_son_departement(): void
    {
        $enseignant = Enseignant::factory()->create(['section' => 'Industrielle']);
        $classe = Classe::factory()->create();
        $automatisme = Discipline::factory()->create(['departement' => 'Industrielle']);

        $this->censeur();

        $this->postJson('/api/emplois', [
            'enseignant_id' => $enseignant->id,
            'classe_id' => $classe->id,
            'discipline_id' => $automatisme->id,
            'jour' => 1,
            'heure_debut' => '08:00',
            'heure_fin' => '09:00',
        ])->assertCreated();
    }

    public function test_il_ne_voit_pas_les_creneaux_dun_autre_departement(): void
    {
        $sien = $this->creneau('Industrielle');
        $this->creneau('Générale');

        $this->censeur();

        $this->getJson('/api/emplois')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $sien->id);
    }

    public function test_la_direction_garde_la_main_sur_tous_les_departements(): void
    {
        Discipline::factory()->create(['departement' => 'Industrielle']);
        $anglais = Discipline::factory()->create(['nom' => 'ANGLAIS', 'departement' => 'Générale']);

        $this->direction();

        $this->getJson('/api/disciplines')->assertOk()->assertJsonCount(2, 'data');
        $this->putJson("/api/disciplines/{$anglais->id}", ['coefficient' => 4])->assertOk();
    }
}
