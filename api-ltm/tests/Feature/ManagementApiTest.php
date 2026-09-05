<?php

namespace Tests\Feature;

use App\Models\Accreditation;
use App\Models\Classe;
use App\Models\Discipline;
use App\Models\Enseignant;
use App\Models\Ferie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsBackoffice(?Accreditation $accreditation = null): User
    {
        $user = User::factory()->create(['accreditation_id' => $accreditation?->id]);
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    public function test_creation_et_lecture_dune_classe_et_dune_discipline(): void
    {
        $this->actingAsBackoffice();

        $classe = $this->postJson('/api/classes', [
            'nom' => 'Terminale D', 'code' => 'TD1',
        ])->assertCreated()->json();

        $this->getJson("/api/classes/{$classe['id']}")->assertOk()->assertJsonFragment(['code' => 'TD1']);

        $discipline = $this->postJson('/api/disciplines', [
            'nom' => 'Mathématiques', 'code' => 'MATH',
        ])->assertCreated()->json();

        $this->getJson("/api/disciplines/{$discipline['id']}")->assertOk()->assertJsonFragment(['code' => 'MATH']);
    }

    public function test_emploi_du_temps_detecte_les_conflits_de_creneaux(): void
    {
        $this->actingAsBackoffice();

        $enseignant = Enseignant::factory()->create();
        $classe = Classe::factory()->create();
        $discipline = Discipline::factory()->create();

        $base = [
            'enseignant_id' => $enseignant->id,
            'classe_id' => $classe->id,
            'discipline_id' => $discipline->id,
            'jour' => 1,
            'heure_debut' => '08:00',
            'heure_fin' => '09:00',
        ];

        $this->postJson('/api/emplois', $base)->assertCreated();

        // Même enseignant, créneau chevauchant → conflit
        $conflit = $this->postJson('/api/emplois', array_merge($base, ['heure_debut' => '08:30', 'heure_fin' => '09:30']));
        $conflit->assertUnprocessable();

        // Créneau disjoint → pas de conflit
        $this->postJson('/api/emplois', array_merge($base, ['heure_debut' => '09:00', 'heure_fin' => '10:00']))
            ->assertCreated();
    }

    public function test_un_role_restreint_ne_voit_que_les_enseignants_de_sa_section(): void
    {
        $accreditation = Accreditation::create(['label' => 'Chef de section Sciences', 'groupe' => 'Sciences']);
        $this->actingAsBackoffice($accreditation);

        Enseignant::factory()->create(['section' => 'Sciences']);
        Enseignant::factory()->create(['section' => 'Lettres']);

        $response = $this->getJson('/api/personnel')->assertOk();

        $sections = collect($response->json('data'))->pluck('section')->unique()->values();
        $this->assertEquals(['Sciences'], $sections->all());
    }

    public function test_un_role_total_voit_tous_les_enseignants(): void
    {
        $accreditation = Accreditation::create(['label' => 'Direction', 'groupe' => '*']);
        $this->actingAsBackoffice($accreditation);

        Enseignant::factory()->create(['section' => 'Sciences']);
        Enseignant::factory()->create(['section' => 'Lettres']);

        $response = $this->getJson('/api/personnel')->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_signalement_groupe_cree_une_entree_par_enseignant(): void
    {
        $this->actingAsBackoffice();

        $enseignants = Enseignant::factory()->count(3)->create();

        $this->postJson('/api/signalements/bulk', [
            'enseignant_ids' => $enseignants->pluck('id')->all(),
            'date' => now()->toDateString(),
            'motif' => 'Réunion pédagogique',
        ])->assertCreated();

        $this->assertDatabaseCount('signalements', 3);
    }

    public function test_creation_ferie_rejette_une_date_dupliquee(): void
    {
        $this->actingAsBackoffice();

        Ferie::create(['date' => '2026-05-01', 'libelle' => 'Fête du travail']);

        $this->postJson('/api/feries', ['date' => '2026-05-01', 'libelle' => 'Doublon'])
            ->assertUnprocessable();
    }
}
