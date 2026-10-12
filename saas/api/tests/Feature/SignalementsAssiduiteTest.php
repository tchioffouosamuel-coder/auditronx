<?php

namespace Tests\Feature;

use App\Models\AbsenceCheckpoint;
use App\Models\Classe;
use App\Models\Discipline;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Ferie;
use App\Models\Presence;
use App\Models\Signalement;
use App\Models\User;
use App\Services\AbsenceDetectorService;
use App\Services\BilanIndividuel;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignalementsAssiduiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-06 12:00:00'));
    }

    private function backoffice(): void
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('backoffice')->plainTextToken);
    }

    private function enseignant(): Enseignant
    {
        $enseignant = Enseignant::factory()->create(['section' => 'Sciences']);
        foreach ([1, 2] as $jour) {
            EmploiDuTemps::create([
                'enseignant_id' => $enseignant->id,
                'classe_id' => Classe::factory()->create()->id,
                'discipline_id' => Discipline::factory()->create()->id,
                'jour' => $jour,
                'heure_debut' => '08:00',
                'heure_fin' => '10:00',
            ]);
        }

        return $enseignant;
    }

    private function signaler(Enseignant $enseignant, string $date, ?int $duree = 1): void
    {
        Signalement::create([
            'enseignant_id' => $enseignant->id,
            'date' => $date,
            'motif' => 'Mission',
            ...($duree === null ? [] : ['duree_jours' => $duree]),
        ]);
    }

    public function test_les_taux_web_mobile_et_hebdomadaires_comptent_les_signalements_sans_doublons(): void
    {
        $enseignant = $this->enseignant();
        // Commence hors des jours de cours, puis couvre lundi et mardi inclus.
        $this->signaler($enseignant, '2026-10-04', 3);
        $this->signaler($enseignant, '2026-10-05', 2);
        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => '2026-10-05',
            'heure_arrivee' => '2026-10-05 08:00:00',
        ]);
        $this->backoffice();

        $stats = $this->getJson('/api/assiduite/stats?debut=2026-10-01&fin=2026-10-31')
            ->assertOk()->json('0');
        $fiche = $this->getJson("/api/personnel/{$enseignant->id}/assiduite")
            ->assertOk()->json();
        $semaine = $this->getJson('/api/assiduite/journal-hebdomadaire?semaine=2026-10-05')
            ->assertOk()->json('lignes.0');

        $this->app['auth']->forgetGuards();
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);
        $mobile = $this->getJson('/api/mon-assiduite')->assertOk()->json();
        foreach ([$stats, $fiche, $semaine, $mobile] as $bilan) {
            $this->assertSame(2, $bilan['jours_attendus']);
            $this->assertSame(2, $bilan['jours_presents']);
            $this->assertEquals(100, $bilan['taux_assiduite']);
        }
        $this->assertSame(2, $mobile['jours_signales']);
        $this->assertSame(1, $mobile['jours_scannes']);
        $this->assertFalse($semaine['jours'][1]['present']);
        $this->assertTrue($semaine['jours'][1]['est_signale']);
        $this->assertSame('Mission', $semaine['jours'][1]['type_signalement']);
        $this->assertDatabaseCount('presences', 1);
    }

    public function test_les_signalements_anterieurs_a_la_periode_couvrent_aussi_l_administration(): void
    {
        $agent = Enseignant::factory()->create(['section' => 'Administration']);
        $this->signaler($agent, '2026-09-30', 7);
        Ferie::create(['date' => '2026-10-02', 'libelle' => 'Ferie']);
        $this->backoffice();

        $this->getJson("/api/personnel/{$agent->id}/assiduite")
            ->assertOk()
            ->assertJsonPath('jours_attendus', 3)
            ->assertJsonPath('jours_presents', 3)
            ->assertJsonPath('jours_signales', 3)
            ->assertJsonPath('taux_assiduite', 100);

        // Les jours futurs, feries et sans cours restent exclus de la grille.
        $enseignant = $this->enseignant();
        $this->signaler($enseignant, '2026-10-07', 10);
        $this->signaler($enseignant, '2026-10-03');
        $this->getJson("/api/personnel/{$enseignant->id}/assiduite")
            ->assertOk()->assertJsonPath('jours_presents', 0)->assertJsonPath('jours_signales', 0);
        $sansCours = Enseignant::factory()->create(['section' => 'Sciences']);
        $this->signaler($sansCours, '2026-10-01', 31);
        $this->getJson("/api/personnel/{$sansCours->id}/assiduite")
            ->assertOk()->assertJsonPath('jours_attendus', 0)->assertJsonPath('jours_presents', 0);
    }

    public function test_un_signalement_sans_duree_ne_valide_que_sa_date_et_reste_propre_a_la_personne(): void
    {
        $enseignant = $this->enseignant();
        $this->signaler($enseignant, '2026-10-05', null);
        $autre = $this->enseignant();
        $this->signaler($autre, '2026-10-06');
        $this->backoffice();

        $this->getJson("/api/personnel/{$enseignant->id}/assiduite")
            ->assertOk()->assertJsonPath('jours_presents', 1)->assertJsonPath('taux_assiduite', 50);
        $this->getJson("/api/personnel/{$autre->id}/assiduite")
            ->assertOk()->assertJsonPath('jours_presents', 1)->assertJsonPath('taux_assiduite', 50);
    }

    public function test_un_pointage_de_depart_et_un_jour_signale_sont_valides_partout(): void
    {
        $enseignant = $this->enseignant();
        $this->signaler($enseignant, '2026-10-06');
        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => '2026-10-05',
            'heure_depart' => '2026-10-05 10:00:00',
        ]);
        $this->backoffice();
        $this->getJson("/api/personnel/{$enseignant->id}/assiduite")
            ->assertOk()->assertJsonPath('jours_presents', 2)->assertJsonPath('taux_assiduite', 100);
        $this->getJson('/api/assiduite/journal-hebdomadaire?semaine=2026-10-05')
            ->assertOk()->assertJsonPath('lignes.0.jours_presents', 2)
            ->assertJsonPath('lignes.0.taux_assiduite', 100);
    }

    public function test_le_dashboard_valide_un_signalement_et_ne_le_compte_pas_absent_ni_scanne(): void
    {
        $enseignant = $this->enseignant();
        $this->signaler($enseignant, '2026-10-05', 2);
        $this->signaler($enseignant, '2026-10-06');
        $sansCours = Enseignant::factory()->create(['section' => 'Sciences']);
        $this->signaler($sansCours, '2026-10-06');
        $this->backoffice();

        $this->getJson('/api/dashboard?date=2026-10-06')->assertOk()
            ->assertJsonPath('presents', 0)->assertJsonPath('signales', 1)
            ->assertJsonPath('absents', 0)->assertJsonCount(0, 'scannes')
            ->assertJsonPath('classement_par_section.0.taux_assiduite', 100);

        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => '2026-10-06',
            'heure_arrivee' => '2026-10-06 08:00:00',
        ]);
        $this->getJson('/api/dashboard?date=2026-10-06')->assertOk()
            ->assertJsonPath('presents', 1)->assertJsonPath('signales', 0)
            ->assertJsonPath('absents', 0)->assertJsonPath('retardataires', 0)
            ->assertJsonPath('classement_par_section.0.taux_assiduite', 100);
        $this->signaler($enseignant, '2026-10-12');
        $this->getJson('/api/dashboard?date=2026-10-12')->assertOk()
            ->assertJsonPath('signales', 0)->assertJsonPath('absents', 0)
            ->assertJsonPath('classement_par_section.0.taux_assiduite', 0);
    }

    public function test_un_jour_signale_interrompt_les_absences_consecutives_et_les_alertes(): void
    {
        $agent = Enseignant::factory()->create(['section' => 'Administration']);
        AbsenceCheckpoint::create([
            'enseignant_id' => $agent->id,
            'date' => '2026-10-05',
            'absences_consecutives' => 2,
        ]);
        $this->signaler($agent, '2026-10-05', 2);

        app(AbsenceDetectorService::class)->detecterPour(Carbon::parse('2026-10-06 12:00:00'));
        $this->assertDatabaseHas('absence_checkpoints', [
            'enseignant_id' => $agent->id,
            'date' => '2026-10-06',
            'absences_consecutives' => 0,
        ]);
        $this->assertDatabaseCount('absence_alert_logs', 0);
    }

    public function test_les_bilans_pdf_et_zip_utilisent_le_meme_taux_pour_un_mois_en_cours(): void
    {
        $enseignant = $this->enseignant();
        $this->signaler($enseignant, '2026-10-05', 2);
        $this->backoffice();
        // Les parametres peuvent contenir une heure : le dernier jour reste inclus.
        $bilan = app(BilanIndividuel::class)->donnees($enseignant, Carbon::parse('2026-10-01 12:00:00'), Carbon::parse('2026-10-31'));
        $this->assertSame(2, $bilan['jours_attendus']);
        $this->assertSame(2, $bilan['presences_valides']);
        $this->assertEquals(100, $bilan['taux_assiduite']);

        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('download')->twice()->andReturn(response()->noContent());
        $pdf->shouldReceive('output')->once()->andReturn('pdf');
        Pdf::shouldReceive('loadView')->twice()
            ->with('pdf.retards-individuel', \Mockery::on(function (array $data): bool {
                $this->assertSame(2, $data['jours_attendus']);
                $this->assertSame(2, $data['presences_valides']);
                $this->assertEquals(100, $data['taux_assiduite']);
                return true;
            }))->andReturn($pdf);
        Pdf::shouldReceive('loadView')->once()
            ->with('pdf.retards-cumule', \Mockery::on(function (array $data): bool {
                $this->assertEquals(100, $data['data'][0]['taux_assiduite']);
                $this->assertSame(0, $data['data'][0]['nb_jours_absence']);
                return true;
            }))->andReturn($pdf);

        $this->get("/api/retards/bilan/{$enseignant->id}")->assertNoContent();
        $this->get('/api/retards/bilan-cumule')->assertNoContent();
        $this->get('/api/statistiques/export-zip')->assertOk();
    }

    public function test_le_pdf_hebdomadaire_affiche_sig_avec_un_taux_valide(): void
    {
        $enseignant = $this->enseignant();
        $this->signaler($enseignant, '2026-10-05', 2);
        $this->backoffice();
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response()->noContent());
        Pdf::shouldReceive('loadView')->once()
            ->with('pdf.journal-hebdomadaire', \Mockery::on(function (array $data): bool {
                $this->assertEquals(100, $data['lignes'][0]['taux_assiduite']);
                $html = view('pdf.journal-hebdomadaire', $data)->render();
                $this->assertSame(2, substr_count($html, 'class="signale"'));
                $this->assertStringNotContainsString('class="absent"', $html);
                return true;
            }))->andReturn($pdf);

        $this->get('/api/assiduite/journal-hebdomadaire/pdf?semaine=2026-10-05')->assertNoContent();
    }
}
