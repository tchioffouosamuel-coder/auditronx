<?php

namespace Tests\Feature;

use App\Models\Accreditation;
use App\Models\Classe;
use App\Models\Discipline;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Journal hebdomadaire des présences (§4.2) — grille semaine et export PDF. */
class JournalHebdomadaireTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $lundi;

    protected function setUp(): void
    {
        parent::setUp();
        // Semaine figée : la grille court du lundi au samedi, les tests doivent
        // pouvoir désigner un jour précis sans dépendre du jour d'exécution.
        $this->lundi = Carbon::parse('2026-10-05')->startOfWeek(Carbon::MONDAY);
    }

    private function actingAsBackoffice(?Accreditation $accreditation = null): User
    {
        $user = User::factory()->create(['accreditation_id' => $accreditation?->id]);
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    private function coursLe(Enseignant $enseignant, Carbon $jour): void
    {
        EmploiDuTemps::create([
            'enseignant_id' => $enseignant->id,
            'classe_id' => Classe::factory()->create()->id,
            'discipline_id' => Discipline::factory()->create()->id,
            'jour' => $jour->isoWeekday(),
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
        ]);
    }

    public function test_la_grille_couvre_le_lundi_au_samedi(): void
    {
        Enseignant::factory()->create();
        $this->actingAsBackoffice();

        $reponse = $this->getJson('/api/assiduite/journal-hebdomadaire?semaine=' . $this->lundi->copy()->addDays(3)->toDateString())
            ->assertOk();

        $this->assertCount(6, $reponse->json('jours'));
        $this->assertSame($this->lundi->toDateString(), $reponse->json('debut'));
        $this->assertSame($this->lundi->toDateString(), $reponse->json('jours.0.date'));
    }

    public function test_un_absent_apparait_alors_que_le_journal_du_jour_lignore(): void
    {
        $enseignant = Enseignant::factory()->create(['nom' => 'ABSENT DU LUNDI']);
        $this->coursLe($enseignant, $this->lundi);

        $this->actingAsBackoffice();

        $ligne = $this->getJson('/api/assiduite/journal-hebdomadaire?semaine=' . $this->lundi->toDateString())
            ->assertOk()
            ->json('lignes.0');

        $lundi = collect($ligne['jours'])->firstWhere('date', $this->lundi->toDateString());

        $this->assertTrue($lundi['attendu']);
        $this->assertFalse($lundi['present']);
        $this->assertSame(1, $ligne['jours_attendus']);
        $this->assertSame(0, $ligne['jours_presents']);

        // Le journal quotidien ne liste que les présences : l'absent n'y est pas.
        $this->getJson('/api/assiduite/journal?date=' . $this->lundi->toDateString())
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_une_presence_remonte_les_heures_et_le_taux(): void
    {
        $enseignant = Enseignant::factory()->create();
        $this->coursLe($enseignant, $this->lundi);

        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => $this->lundi->toDateString(),
            'heure_arrivee' => $this->lundi->copy()->setTime(7, 55),
            'heure_depart' => $this->lundi->copy()->setTime(15, 30),
            'source' => 'app_mobile',
        ]);

        $this->actingAsBackoffice();

        $ligne = $this->getJson('/api/assiduite/journal-hebdomadaire?semaine=' . $this->lundi->toDateString())
            ->assertOk()
            ->json('lignes.0');

        $lundi = collect($ligne['jours'])->firstWhere('date', $this->lundi->toDateString());

        $this->assertTrue($lundi['present']);
        $this->assertSame('07:55', $lundi['heure_arrivee']);
        $this->assertSame('15:30', $lundi['heure_depart']);
        $this->assertEquals(100, $ligne['taux_assiduite']);
    }

    public function test_un_enseignant_sans_emploi_du_temps_na_pas_de_taux(): void
    {
        Enseignant::factory()->create(['section' => 'Industrielle']);
        $this->actingAsBackoffice();

        $ligne = $this->getJson('/api/assiduite/journal-hebdomadaire?semaine=' . $this->lundi->toDateString())
            ->assertOk()
            ->json('lignes.0');

        $this->assertSame(0, $ligne['jours_attendus']);
        $this->assertNull($ligne['taux_assiduite']);
    }

    public function test_la_grille_respecte_le_perimetre_de_laccreditation(): void
    {
        Enseignant::factory()->create(['nom' => 'PROF INDUS', 'section' => 'Industrielle']);
        Enseignant::factory()->create(['nom' => 'PROF STT', 'section' => 'STT']);

        $censeur = Accreditation::create(['label' => 'Censeur Industriel', 'groupe' => 'Industrielle']);
        $this->actingAsBackoffice($censeur);

        $this->getJson('/api/assiduite/journal-hebdomadaire?semaine=' . $this->lundi->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'lignes')
            ->assertJsonPath('lignes.0.nom', 'PROF INDUS');
    }

    public function test_le_journal_hebdomadaire_separe_enseignants_et_administration(): void
    {
        Enseignant::factory()->create(['nom' => 'ENSEIGNANT', 'section' => 'STT']);
        Enseignant::factory()->create(['nom' => 'ADMINISTRATION', 'section' => 'Administration']);
        $this->actingAsBackoffice();

        $this->getJson('/api/assiduite/journal-hebdomadaire?semaine=' . $this->lundi->toDateString() . '&categorie=enseignant')
            ->assertOk()
            ->assertJsonCount(1, 'lignes')
            ->assertJsonPath('lignes.0.nom', 'ENSEIGNANT');

        $this->getJson('/api/assiduite/journal-hebdomadaire?semaine=' . $this->lundi->toDateString() . '&categorie=administration')
            ->assertOk()
            ->assertJsonCount(1, 'lignes')
            ->assertJsonPath('lignes.0.nom', 'ADMINISTRATION');
    }

    public function test_le_journal_quotidien_separe_enseignants_et_administration(): void
    {
        $enseignant = Enseignant::factory()->create(['nom' => 'ENSEIGNANT', 'section' => 'STT']);
        $administratif = Enseignant::factory()->create(['nom' => 'ADMINISTRATION', 'section' => 'Administration']);

        foreach ([$enseignant, $administratif] as $personnel) {
            Presence::create([
                'enseignant_id' => $personnel->id,
                'date' => $this->lundi->toDateString(),
                'heure_arrivee' => $this->lundi->copy()->setTime(7, 55),
                'source' => 'app_mobile',
            ]);
        }

        $this->actingAsBackoffice();

        $this->getJson('/api/assiduite/journal?date=' . $this->lundi->toDateString() . '&categorie=enseignant')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.enseignant.nom', 'ENSEIGNANT');

        $this->getJson('/api/assiduite/journal?date=' . $this->lundi->toDateString() . '&categorie=administration')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.enseignant.nom', 'ADMINISTRATION');
    }

    public function test_lexport_pdf_est_servi_et_journalise(): void
    {
        Enseignant::factory()->create();
        $this->actingAsBackoffice();

        $this->get('/api/assiduite/journal-hebdomadaire/pdf?semaine=' . $this->lundi->toDateString())
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'assiduite.journal-hebdomadaire.pdf.get',
        ]);
    }
}
