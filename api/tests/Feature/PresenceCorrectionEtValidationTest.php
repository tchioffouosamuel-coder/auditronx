<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\CoursValidation;
use App\Models\Discipline;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresenceCorrectionEtValidationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsBackoffice(): User
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    public function test_les_anomalies_detectent_un_depart_manquant_et_un_pointage_manquant(): void
    {
        $this->actingAsBackoffice();

        $classe = Classe::factory()->create();
        $discipline = Discipline::factory()->create();

        $avecDepartManquant = Enseignant::factory()->create();
        EmploiDuTemps::create([
            'enseignant_id' => $avecDepartManquant->id, 'classe_id' => $classe->id, 'discipline_id' => $discipline->id,
            'jour' => now()->isoWeekday(), 'heure_debut' => '08:00', 'heure_fin' => '09:00',
        ]);
        Presence::create([
            'enseignant_id' => $avecDepartManquant->id,
            'date' => now()->toDateString(),
            'heure_arrivee' => now()->toDateString().' 08:00:00',
        ]);

        $sansPointage = Enseignant::factory()->create();
        EmploiDuTemps::create([
            'enseignant_id' => $sansPointage->id, 'classe_id' => $classe->id, 'discipline_id' => $discipline->id,
            'jour' => now()->isoWeekday(), 'heure_debut' => '10:00', 'heure_fin' => '11:00',
        ]);

        $response = $this->getJson('/api/presences/anomalies')->assertOk();
        $types = collect($response->json('anomalies'))->pluck('type', 'enseignant_id');

        $this->assertSame('depart_manquant', $types[$avecDepartManquant->id]);
        $this->assertSame('pointage_manquant', $types[$sansPointage->id]);
    }

    public function test_la_correction_groupee_journalise_lauteur_et_le_motif(): void
    {
        $user = $this->actingAsBackoffice();
        $enseignant = Enseignant::factory()->create();

        $this->postJson('/api/presences/corriger', [
            'corrections' => [[
                'enseignant_id' => $enseignant->id,
                'date' => now()->toDateString(),
                'heure_arrivee' => '08:00',
                'heure_depart' => '16:00',
                'motif' => 'Oubli de badge',
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('presences', [
            'enseignant_id' => $enseignant->id,
            'source' => 'manuel',
            'recorded_by' => $user->id,
            'on_behalf_of' => $enseignant->id,
            'reason' => 'Oubli de badge',
        ]);
    }

    public function test_le_basculement_de_validation_alterne_fait_et_non_fait(): void
    {
        $this->actingAsBackoffice();

        $classe = Classe::factory()->create();
        $discipline = Discipline::factory()->create();
        $enseignant = Enseignant::factory()->create();

        $cours = EmploiDuTemps::create([
            'enseignant_id' => $enseignant->id, 'classe_id' => $classe->id, 'discipline_id' => $discipline->id,
            'jour' => now()->isoWeekday(), 'heure_debut' => '08:00', 'heure_fin' => '09:00',
        ]);

        $premier = $this->postJson('/api/presences/validation/toggle', [
            'emploi_du_temps_id' => $cours->id,
            'date' => now()->toDateString(),
        ])->assertOk();
        $this->assertSame('fait', $premier->json('status'));

        $second = $this->postJson('/api/presences/validation/toggle', [
            'emploi_du_temps_id' => $cours->id,
            'date' => now()->toDateString(),
        ])->assertOk();
        $this->assertSame('non_fait', $second->json('status'));

        $this->assertDatabaseCount('cours_validation', 1);
    }
}
