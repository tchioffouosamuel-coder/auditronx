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
            'heure_arrivee' => now()->toDateString() . ' 08:25:00',
        ]);

        $response = $this->getJson('/api/retards')->assertOk();
        $ligne = collect($response->json())->firstWhere('enseignant_id', $enseignant->id);

        $this->assertSame(1, $ligne['nb_jours_retard']);
        $this->assertSame(15, $ligne['total_retard_minutes']); // 25 min - 10 min de tolérance
    }

    public function test_un_enseignant_arrive_dans_la_tolerance_nest_pas_en_retard(): void
    {
        $this->actingAsBackoffice();

        $enseignant = Enseignant::factory()->create();
        $this->creerCoursAujourdhui($enseignant);

        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => now()->toDateString(),
            'heure_arrivee' => now()->toDateString() . ' 08:05:00',
        ]);

        $response = $this->getJson('/api/retards')->assertOk();
        $ligne = collect($response->json())->firstWhere('enseignant_id', $enseignant->id);

        $this->assertSame(0, $ligne['nb_jours_retard']);
    }

    public function test_le_dashboard_compte_presents_absents_et_retardataires(): void
    {
        $this->actingAsBackoffice();

        $present = Enseignant::factory()->create(['section' => 'Sciences']);
        $this->creerCoursAujourdhui($present);
        Presence::create([
            'enseignant_id' => $present->id,
            'date' => now()->toDateString(),
            'heure_arrivee' => now()->toDateString() . ' 08:00:00',
        ]);

        $absent = Enseignant::factory()->create(['section' => 'Sciences']);
        $this->creerCoursAujourdhui($absent, '00:00', '01:00');

        $response = $this->getJson('/api/dashboard')->assertOk();

        $this->assertSame(2, $response->json('effectif'));
        $this->assertSame(1, $response->json('presents'));
        $this->assertSame(1, $response->json('absents'));
        $this->assertSame(0, $response->json('retardataires'));
    }

    public function test_le_dashboard_compte_aussi_les_presents_sans_cours_ce_jour(): void
    {
        $this->actingAsBackoffice();

        $sansCours = Enseignant::factory()->create(['section' => 'Sciences']);
        Presence::create([
            'enseignant_id' => $sansCours->id,
            'date' => now()->toDateString(),
            'heure_arrivee' => now()->toDateString() . ' 08:00:00',
        ]);
        // Sans cours ni pointage : ni présent, ni absent.
        Enseignant::factory()->create(['section' => 'Sciences']);

        $response = $this->getJson('/api/dashboard')->assertOk();

        $this->assertSame(1, $response->json('presents'));
        $this->assertSame(0, $response->json('absents'));
        $this->assertSame(0, $response->json('retardataires'));
        $this->assertTrue($response->json('scannes.0.hors_emploi_du_temps'));
    }

    public function test_le_classement_de_ponctualite_departage_deux_enseignants_a_jours_ponctuels_egaux(): void
    {
        $this->actingAsBackoffice();

        $hier = now()->subDay();

        // Les deux enseignants ont un cours hier (ponctuel) et aujourd'hui
        // (en retard) : même nombre de jours ponctuels (1/2), mais des
        // minutes de retard cumulées différentes.
        $peuEnRetard = Enseignant::factory()->create(['nom' => 'Peu En Retard']);
        EmploiDuTemps::create([
            'enseignant_id' => $peuEnRetard->id,
            'classe_id' => Classe::factory()->create()->id,
            'discipline_id' => Discipline::factory()->create()->id,
            'jour' => $hier->isoWeekday(),
            'heure_debut' => '08:00',
            'heure_fin' => '09:00',
        ]);
        $this->creerCoursAujourdhui($peuEnRetard);
        Presence::create([
            'enseignant_id' => $peuEnRetard->id,
            'date' => $hier->toDateString(),
            'heure_arrivee' => $hier->toDateString() . ' 08:00:00',
        ]);
        Presence::create([
            'enseignant_id' => $peuEnRetard->id,
            'date' => now()->toDateString(),
            'heure_arrivee' => now()->toDateString() . ' 08:30:00', // 20 min de retard après tolérance
        ]);

        $beaucoupEnRetard = Enseignant::factory()->create(['nom' => 'Beaucoup En Retard']);
        EmploiDuTemps::create([
            'enseignant_id' => $beaucoupEnRetard->id,
            'classe_id' => Classe::factory()->create()->id,
            'discipline_id' => Discipline::factory()->create()->id,
            'jour' => $hier->isoWeekday(),
            'heure_debut' => '08:00',
            'heure_fin' => '09:00',
        ]);
        $this->creerCoursAujourdhui($beaucoupEnRetard);
        Presence::create([
            'enseignant_id' => $beaucoupEnRetard->id,
            'date' => $hier->toDateString(),
            'heure_arrivee' => $hier->toDateString() . ' 08:00:00',
        ]);
        Presence::create([
            'enseignant_id' => $beaucoupEnRetard->id,
            'date' => now()->toDateString(),
            'heure_arrivee' => now()->toDateString() . ' 09:00:00', // 50 min de retard après tolérance
        ]);

        $response = $this->getJson('/api/retards/classement-ponctualite?debut=' . $hier->toDateString() . '&fin=' . now()->toDateString())
            ->assertOk();
        $classement = collect($response->json());
        $ligneA = $classement->firstWhere('enseignant_id', $peuEnRetard->id);
        $ligneB = $classement->firstWhere('enseignant_id', $beaucoupEnRetard->id);

        $this->assertSame(1, $ligneA['jours_ponctuels']);
        $this->assertSame(1, $ligneB['jours_ponctuels']);
        $this->assertSame($ligneA['taux_ponctualite'], $ligneB['taux_ponctualite']);
        $this->assertGreaterThan(0, $ligneB['total_retard_minutes']);
        $this->assertGreaterThan($ligneB['score_ponctualite'], $ligneA['score_ponctualite']);
        $this->assertSame($peuEnRetard->id, $classement->first()['enseignant_id']);
    }

    /**
     * Le document du classement ne porte que le classement : l'écran
     * téléchargeait jusqu'ici le bilan cumulé trié par ponctualité, où retards,
     * absences et périodes à rattraper noyaient le palmarès.
     */
    public function test_le_classement_de_ponctualite_a_son_propre_pdf(): void
    {
        $this->actingAsBackoffice();

        $reponse = $this->get('/api/retards/classement-ponctualite/pdf?debut='
            .now()->startOfMonth()->toDateString().'&fin='.now()->endOfMonth()->toDateString());

        $reponse->assertOk();
        $this->assertSame('application/pdf', $reponse->headers->get('content-type'));
        $this->assertStringContainsString(
            'classement-ponctualite',
            (string) $reponse->headers->get('content-disposition'),
        );
    }
}
