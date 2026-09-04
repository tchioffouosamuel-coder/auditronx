<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Discipline;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetardsAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsBackoffice(): User
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    private function creerCoursAujourdhui(Enseignant $enseignant, string $heureDebut = '08:00', string $heureFin = '09:00'): EmploiDuTemps
    {
        return EmploiDuTemps::create([
            'enseignant_id' => $enseignant->id,
            'classe_id' => Classe::factory()->create()->id,
            'discipline_id' => Discipline::factory()->create()->id,
            'jour' => now()->isoWeekday(),
            'heure_debut' => $heureDebut,
            'heure_fin' => $heureFin,
        ]);
    }

    public function test_le_seuil_de_tolerance_est_configurable_et_utilise_par_le_calcul(): void
    {
        $this->actingAsBackoffice();

        $this->getJson('/api/retards/parametres')->assertOk()->assertJson(['tolerance_minutes' => 10]);

        $this->putJson('/api/retards/parametres', ['tolerance_minutes' => 5])
            ->assertOk()->assertJson(['tolerance_minutes' => 5]);
    }

    public function test_un_enseignant_arrive_au_dela_de_la_tolerance_est_compte_en_retard(): void
    {
        $this->actingAsBackoffice();

        $enseignant = Enseignant::factory()->create();
        $this->creerCoursAujourdhui($enseignant);

        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => now()->toDateString(),
            'heure_arrivee' => now()->toDateString().' 08:25:00',
        ]);

        $response = $this->getJson('/api/retards')->assertOk();
        $ligne = collect($response->json())->firstWhere('enseignant_id', $enseignant->id);

        $this->assertSame(1, $ligne['jours_retard']);
        $this->assertSame(15, $ligne['minutes_retard_total']); // 25 min - 10 min de tolérance
    }

    public function test_un_enseignant_arrive_dans_la_tolerance_nest_pas_en_retard(): void
    {
        $this->actingAsBackoffice();

        $enseignant = Enseignant::factory()->create();
        $this->creerCoursAujourdhui($enseignant);

        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => now()->toDateString(),
            'heure_arrivee' => now()->toDateString().' 08:05:00',
        ]);

        $response = $this->getJson('/api/retards')->assertOk();
        $ligne = collect($response->json())->firstWhere('enseignant_id', $enseignant->id);

        $this->assertSame(0, $ligne['jours_retard']);
    }

    public function test_le_dashboard_compte_presents_absents_et_retardataires(): void
    {
        $this->actingAsBackoffice();

        $present = Enseignant::factory()->create(['section' => 'Sciences']);
        $this->creerCoursAujourdhui($present);
        Presence::create([
            'enseignant_id' => $present->id,
            'date' => now()->toDateString(),
            'heure_arrivee' => now()->toDateString().' 08:00:00',
        ]);

        $absent = Enseignant::factory()->create(['section' => 'Sciences']);

        $response = $this->getJson('/api/dashboard')->assertOk();

        $this->assertSame(2, $response->json('effectif'));
        $this->assertSame(1, $response->json('presents'));
        $this->assertSame(1, $response->json('absents'));
        $this->assertSame(0, $response->json('retardataires'));
    }
}
