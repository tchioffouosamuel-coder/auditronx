<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Discipline;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresenceValidationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsBackoffice(): User
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
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
