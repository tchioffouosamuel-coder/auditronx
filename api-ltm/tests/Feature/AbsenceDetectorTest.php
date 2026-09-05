<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Discipline;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Services\AbsenceDetectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsenceDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_absences_consecutives_saccumulent_et_declenchent_une_alerte_au_seuil(): void
    {
        $enseignant = Enseignant::factory()->create();
        $classe = Classe::factory()->create();
        $discipline = Discipline::factory()->create();

        // Cours tous les jours de la semaine, pour que chaque date testée soit "un jour avec cours".
        foreach (range(1, 7) as $jour) {
            EmploiDuTemps::create([
                'enseignant_id' => $enseignant->id, 'classe_id' => $classe->id, 'discipline_id' => $discipline->id,
                'jour' => $jour, 'heure_debut' => '08:00', 'heure_fin' => '09:00',
            ]);
        }

        $service = app(AbsenceDetectorService::class);
        $debut = now()->startOfDay();

        $service->detecterPour($debut->copy());
        $this->assertDatabaseHas('absence_checkpoints', ['enseignant_id' => $enseignant->id, 'absences_consecutives' => 1]);
        $this->assertDatabaseCount('absence_alert_logs', 0);

        $service->detecterPour($debut->copy()->addDay());
        $this->assertDatabaseHas('absence_checkpoints', ['enseignant_id' => $enseignant->id, 'absences_consecutives' => 2]);
        $this->assertDatabaseCount('absence_alert_logs', 0);

        $service->detecterPour($debut->copy()->addDays(2));
        $this->assertDatabaseHas('absence_checkpoints', ['enseignant_id' => $enseignant->id, 'absences_consecutives' => 3]);
        $this->assertDatabaseCount('absence_alert_logs', 1);
    }
}
