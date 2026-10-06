<?php

namespace Tests\Feature;

use App\Models\Enseignant;
use App\Models\Presence;
use App\Models\User;
use App\Services\AbsenceDetectorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le personnel administratif (section « Administration ») est évalué selon un
 * horaire fixe configurable — défaut lundi–vendredi, 07:30–15:30 — et non
 * selon un emploi du temps.
 */
class HoraireAdministratifTest extends TestCase
{
    use RefreshDatabase;

    private const MERCREDI = '2026-09-30';

    private const SAMEDI = '2026-10-03';

    // Semaine entièrement écoulée au regard de la date figée ci-dessous : les
    // statistiques n'évaluent que les jours survenus, une fenêtre à cheval sur
    // « aujourd'hui » ne couvrirait plus les cinq jours ouvrés.
    private const MERCREDI_PRECEDENT = '2026-09-23';

    private const SAMEDI_PRECEDENT = '2026-09-26';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse(self::MERCREDI.' 12:00:00'));
    }

    private function actingAsBackoffice(): User
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    private function administratif(array $attributs = []): Enseignant
    {
        return Enseignant::factory()->create(['section' => 'Administration', ...$attributs]);
    }

    private function pointer(Enseignant $enseignant, string $date, string $arrivee, ?string $depart = null): Presence
    {
        return Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => $date,
            'heure_arrivee' => "{$date} {$arrivee}:00",
            'heure_depart' => $depart ? "{$date} {$depart}:00" : null,
        ]);
    }

    public function test_les_horaires_par_defaut_sont_lundi_vendredi_07h30_15h30(): void
    {
        $this->actingAsBackoffice();

        $this->getJson('/api/parametres/horaires-administratifs')
            ->assertOk()
            ->assertExactJson(['jours' => [1, 2, 3, 4, 5], 'heure_debut' => '07:30', 'heure_fin' => '15:30']);
    }

    public function test_les_horaires_sont_configurables(): void
    {
        $this->actingAsBackoffice();

        $this->putJson('/api/parametres/horaires-administratifs', ['jours' => [6, 1, 2], 'heure_debut' => '08:00', 'heure_fin' => '16:00'])
            ->assertOk()
            ->assertExactJson(['jours' => [1, 2, 6], 'heure_debut' => '08:00', 'heure_fin' => '16:00']);

        $this->getJson('/api/parametres/horaires-administratifs')
            ->assertExactJson(['jours' => [1, 2, 6], 'heure_debut' => '08:00', 'heure_fin' => '16:00']);
    }

    public function test_des_horaires_incoherents_sont_refuses(): void
    {
        $this->actingAsBackoffice();

        $this->putJson('/api/parametres/horaires-administratifs', ['jours' => [1], 'heure_debut' => '15:30', 'heure_fin' => '07:30'])
            ->assertJsonValidationErrors('heure_fin');
        $this->putJson('/api/parametres/horaires-administratifs', ['jours' => [], 'heure_debut' => '07:30', 'heure_fin' => '15:30'])
            ->assertJsonValidationErrors('jours');
        $this->putJson('/api/parametres/horaires-administratifs', ['jours' => [8], 'heure_debut' => '07:30', 'heure_fin' => '15:30'])
            ->assertJsonValidationErrors('jours.0');
    }

    public function test_un_enseignant_ne_peut_pas_modifier_les_horaires(): void
    {
        $enseignant = Enseignant::factory()->create();
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $this->putJson('/api/parametres/horaires-administratifs', ['jours' => [1], 'heure_debut' => '07:30', 'heure_fin' => '15:30'])
            ->assertForbidden();
    }

    public function test_le_retard_dun_administratif_se_calcule_depuis_le_debut_de_journee(): void
    {
        $this->actingAsBackoffice();
        $enRetard = $this->administratif();
        $ponctuel = $this->administratif(['section' => 'administration']);
        $this->pointer($enRetard, self::MERCREDI, '07:55');
        $this->pointer($ponctuel, self::MERCREDI, '07:40');

        $lignes = collect($this->getJson('/api/retards?debut='.self::MERCREDI.'&fin='.self::MERCREDI)->assertOk()->json());

        // 25 min après 07:30, moins 10 min de tolérance.
        $this->assertSame(15, $lignes->firstWhere('enseignant_id', $enRetard->id)['minutes_retard_total']);
        $this->assertSame(1, $lignes->firstWhere('enseignant_id', $enRetard->id)['jours_retard']);
        $this->assertSame(0, $lignes->firstWhere('enseignant_id', $ponctuel->id)['jours_retard']);
    }

    public function test_un_administratif_est_attendu_du_lundi_au_vendredi_sans_emploi_du_temps(): void
    {
        $this->actingAsBackoffice();
        $administratif = $this->administratif();
        $enseignantSansCours = Enseignant::factory()->create(['section' => 'Sciences']);
        $this->pointer($administratif, self::MERCREDI_PRECEDENT, '07:30');
        // Un pointage le samedi ne compte pas : jour non attendu.
        $this->pointer($administratif, self::SAMEDI_PRECEDENT, '07:30');

        // Semaine complète, du lundi 21/09 au dimanche 27/09.
        $lignes = collect($this->getJson('/api/assiduite/stats?debut=2026-09-21&fin=2026-09-27')->assertOk()->json());

        $ligne = $lignes->firstWhere('enseignant_id', $administratif->id);
        $this->assertSame(5, $ligne['jours_attendus']);
        $this->assertSame(1, $ligne['jours_presents']);
        $this->assertEquals(20.0, $ligne['taux_assiduite']);
        $this->assertSame(0, $lignes->firstWhere('enseignant_id', $enseignantSansCours->id)['jours_attendus']);

        $this->getJson("/api/personnel/{$administratif->id}/assiduite")
            ->assertOk()
            ->assertJsonPath('jours_attendus', 22); // jours ouvrés de septembre 2026
    }

    public function test_les_horaires_configures_pilotent_le_calcul(): void
    {
        $this->actingAsBackoffice();
        $administratif = $this->administratif();
        $this->pointer($administratif, self::MERCREDI, '07:55');

        $this->putJson('/api/parametres/horaires-administratifs', ['jours' => [3, 6], 'heure_debut' => '08:00', 'heure_fin' => '14:00'])
            ->assertOk();

        $retards = collect($this->getJson('/api/retards?debut='.self::MERCREDI.'&fin='.self::MERCREDI)->json());
        $this->assertSame(0, $retards->firstWhere('enseignant_id', $administratif->id)['jours_retard']);

        $stats = collect($this->getJson('/api/assiduite/stats?debut=2026-09-21&fin=2026-09-27')->json());
        $this->assertSame(2, $stats->firstWhere('enseignant_id', $administratif->id)['jours_attendus']);
    }

    public function test_le_dashboard_compte_les_administratifs_absents_et_retardataires(): void
    {
        $this->actingAsBackoffice();
        $absent = $this->administratif();
        $retardataire = $this->administratif();
        $this->pointer($retardataire, self::MERCREDI, '08:00');

        $response = $this->getJson('/api/dashboard')->assertOk();

        $this->assertSame(1, $response->json('presents'));
        $this->assertSame(1, $response->json('absents'));
        $this->assertSame(1, $response->json('retardataires'));
        $this->assertSame($absent->id, $response->json('absents_liste.0.enseignant_id'));
        $this->assertFalse($response->json('absents_liste.0.hors_emploi_du_temps'));
        $this->assertSame(['heure_debut' => '07:30', 'heure_fin' => '15:30'], $response->json('absents_liste.0.horaire_administratif'));
        $this->assertSame(20, $response->json('retardataires_liste.0.minutes_retard'));
        $this->assertSame(2, $response->json('classement_par_section.0.effectif'));

        // Le samedi, personne n'est attendu.
        $samedi = $this->getJson('/api/dashboard?date='.self::SAMEDI)->assertOk();
        $this->assertSame(0, $samedi->json('absents'));
    }

    public function test_un_administratif_nest_pas_absent_avant_le_debut_de_sa_journee(): void
    {
        $this->actingAsBackoffice();
        $this->administratif();
        $this->travelTo(Carbon::parse(self::MERCREDI.' 07:00:00'));

        $this->assertSame(0, $this->getJson('/api/dashboard')->assertOk()->json('absents'));
    }

    public function test_la_fiche_individuelle_dun_administratif_suit_lhoraire_fixe(): void
    {
        $this->actingAsBackoffice();
        $administratif = $this->administratif();
        // Mercredi : 20 min de retard (tolérance déduite), départ 60 min avant 15:30.
        $this->pointer($administratif, self::MERCREDI, '08:00', '14:30');

        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('download')->once()->andReturn(response()->noContent());
        Pdf::shouldReceive('loadView')
            ->once()
            ->with('pdf.retards-individuel', \Mockery::on(function (array $data): bool {
                $this->assertSame(5, $data['jours_attendus']);
                $this->assertSame(1, $data['presences_valides']);
                $this->assertSame(20, $data['total_retard_minutes']);
                $this->assertSame(60, $data['total_anticipation_minutes']);

                $mercredi = collect($data['details'])->firstWhere('date', '30/09/2026');
                $this->assertSame('07:30', $mercredi['heure_debut_prevue']);
                $this->assertSame('15:30', $mercredi['heure_fin_prevue']);
                $this->assertTrue(collect($data['details'])->firstWhere('date', '28/09/2026')['absent']);

                $html = view('pdf.retards-individuel', $data)->render();
                $this->assertStringContainsString('HORAIRE DE TRAVAIL (PERSONNEL ADMINISTRATIF)', $html);
                $this->assertStringNotContainsString('EMPLOI DU TEMPS HEBDOMADAIRE', $html);

                return true;
            }))
            ->andReturn($pdf);

        $this->get("/api/retards/bilan/{$administratif->id}?debut=2026-09-28&fin=2026-10-04")->assertNoContent();
    }

    /**
     * Régression : le bilan cumulé écartait le personnel administratif même
     * quand la fiche demandée était celle de la section « Administration »
     * (fiche vide). Tous ses membres doivent y figurer, quelle que soit la
     * casse de la section saisie, avec l'horaire fixe comme référence.
     */
    public function test_le_bilan_cumule_de_la_section_administration_liste_tout_son_personnel(): void
    {
        $this->actingAsBackoffice();
        $this->administratif(['nom' => 'Bello']);
        $this->administratif(['nom' => 'Abdou', 'section' => 'administration']);
        $retardataire = $this->administratif(['nom' => 'Chantal', 'section' => ' ADMINISTRATION ']);
        Enseignant::factory()->create(['nom' => 'Zulu Enseignant', 'section' => 'Sciences']);
        // Mercredi : 20 min de retard (tolérance déduite), départ 60 min avant 15:30.
        $this->pointer($retardataire, self::MERCREDI, '08:00', '14:30');

        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('download')->once()->andReturn(response()->noContent());
        Pdf::shouldReceive('loadView')
            ->once()
            ->with('pdf.retards-cumule', \Mockery::on(function (array $viewData): bool {
                $this->assertSame(['Abdou', 'Bello', 'Chantal'], array_column($viewData['data'], 'nom'));

                // Du lundi 28/09 au mercredi 30/09 (jours futurs ignorés).
                $absent = $viewData['data'][0];
                $this->assertSame(3, $absent['nb_jours_absence']);
                $this->assertSame(36, $absent['periodes_absence']); // 3 journées de 8 h = 3 × 12 périodes

                $retardataire = $viewData['data'][2];
                $this->assertSame(2, $retardataire['nb_jours_absence']);
                $this->assertSame(20, $retardataire['total_retard_minutes']);
                $this->assertSame(60, $retardataire['total_anticipation_minutes']);
                $this->assertEquals(33.3, $retardataire['taux_assiduite']);

                return true;
            }))
            ->andReturn($pdf);

        $this->get('/api/retards/bilan-cumule?debut=2026-09-28&fin=2026-10-04&section=ADMINISTRATION')->assertNoContent();
    }

    /**
     * Régression : l'export ZIP remplissait chaque bilan avec des zéros en dur
     * (taux d'assiduité 0 % pour tout le monde, détail vide). Il doit porter
     * les mêmes chiffres que la fiche individuelle, horaire fixe compris.
     */
    public function test_lexport_zip_porte_le_meme_bilan_que_la_fiche_individuelle(): void
    {
        $this->actingAsBackoffice();
        $administratif = $this->administratif();
        $this->pointer($administratif, self::MERCREDI, '07:30', '15:30');

        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('output')->once()->andReturn('%PDF-test');
        Pdf::shouldReceive('loadView')
            ->once()
            ->with('pdf.retards-individuel', \Mockery::on(function (array $data) use ($administratif): bool {
                $this->assertSame($administratif->id, $data['enseignant']->id);
                $this->assertSame(5, $data['jours_attendus']);
                $this->assertSame(1, $data['presences_valides']);
                $this->assertEqualsWithDelta(20.0, $data['taux_assiduite'], 0.01);
                $this->assertCount(5, $data['details']);

                return true;
            }))
            ->andReturn($pdf);

        $response = $this->get('/api/statistiques/export-zip?debut=2026-09-28&fin=2026-10-04')->assertOk();
        $this->assertSame('application/zip', $response->headers->get('content-type'));
    }

    public function test_la_liste_sans_presence_ne_retient_que_ceux_qui_nont_jamais_pointe(): void
    {
        $this->actingAsBackoffice();
        $jamaisPointe = Enseignant::factory()->create(['nom' => 'Bello', 'section' => 'Sciences']);
        $administratif = $this->administratif(['nom' => 'Abdou']);
        $dejaPointe = Enseignant::factory()->create(['nom' => 'Chantal']);
        $this->pointer($dejaPointe, '2026-01-12', '07:30');

        $this->getJson('/api/assiduite/sans-presence')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.enseignant_id', $administratif->id)
            ->assertJsonPath('1.enseignant_id', $jamaisPointe->id)
            ->assertJsonPath('1.matricule', $jamaisPointe->matricule);
    }

    public function test_la_detection_dabsences_couvre_les_administratifs_les_jours_ouvres(): void
    {
        $administratif = $this->administratif();
        $service = app(AbsenceDetectorService::class);

        $service->detecterPour(Carbon::parse(self::SAMEDI));
        $this->assertDatabaseCount('absence_checkpoints', 0);

        $service->detecterPour(Carbon::parse(self::MERCREDI));
        $this->assertDatabaseHas('absence_checkpoints', ['enseignant_id' => $administratif->id, 'absences_consecutives' => 1]);
    }
}
