<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Discipline;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Models\User;
use App\Services\AbsenceDetectorService;
use App\Services\HoraireAttendu;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un jour qui n'a pas encore eu lieu ne peut pas être jugé : personne n'y est
 * absent et il ne compte pas au dénominateur des taux. Sans cela, le taux d'une
 * semaine ou d'un mois en cours démarre au plus bas puis remonte au fil des
 * jours, ce qui le rend inexploitable.
 */
class JoursAVenirTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $lundi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lundi = Carbon::parse('2026-10-05')->startOfWeek(Carbon::MONDAY);
        // On se place le mardi en milieu de journée : lundi et mardi sont
        // écoulés, mercredi à samedi sont à venir.
        Carbon::setTestNow($this->lundi->copy()->addDay()->setTime(13, 21));
    }

    private function actingAsBackoffice(): User
    {
        $user = User::factory()->create();
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

    public function test_aujourdhui_nest_pas_un_jour_a_venir(): void
    {
        $horaires = app(HoraireAttendu::class);

        $this->assertFalse($horaires->estAVenir($this->lundi));
        $this->assertFalse($horaires->estAVenir($this->lundi->copy()->addDay()));
        $this->assertTrue($horaires->estAVenir($this->lundi->copy()->addDays(2)));
    }

    public function test_un_jour_a_venir_nest_pas_porte_absent_dans_la_grille(): void
    {
        $enseignant = Enseignant::factory()->create();
        $this->coursLe($enseignant, $this->lundi);
        $mercredi = $this->lundi->copy()->addDays(2);
        $this->coursLe($enseignant, $mercredi);

        $this->actingAsBackoffice();

        $ligne = $this->getJson('/api/assiduite/journal-hebdomadaire?semaine='.$this->lundi->toDateString())
            ->assertOk()
            ->json('lignes.0');

        $cellules = collect($ligne['jours']);

        // Lundi est écoulé et sans pointage : c'est une absence.
        $celluleLundi = $cellules->firstWhere('date', $this->lundi->toDateString());
        $this->assertTrue($celluleLundi['attendu']);
        $this->assertFalse($celluleLundi['a_venir']);

        // Mercredi reste « attendu » (l'emploi du temps le dit) mais est marqué
        // à venir, ce qui permet à la grille de ne pas l'afficher en ABS.
        $celluleMercredi = $cellules->firstWhere('date', $mercredi->toDateString());
        $this->assertTrue($celluleMercredi['attendu']);
        $this->assertTrue($celluleMercredi['a_venir']);
        $this->assertFalse($celluleMercredi['present']);

        // Seul lundi compte : 0 / 1 et non 0 / 2.
        $this->assertSame(1, $ligne['jours_attendus']);
        $this->assertSame(0, $ligne['jours_presents']);
    }

    public function test_le_taux_hebdomadaire_ignore_les_jours_restants(): void
    {
        $enseignant = Enseignant::factory()->create();
        foreach (range(0, 5) as $decalage) {
            $this->coursLe($enseignant, $this->lundi->copy()->addDays($decalage));
        }

        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => $this->lundi->toDateString(),
            'heure_arrivee' => $this->lundi->copy()->setTime(7, 55),
            'source' => 'app_mobile',
        ]);

        $this->actingAsBackoffice();

        $ligne = $this->getJson('/api/assiduite/journal-hebdomadaire?semaine='.$this->lundi->toDateString())
            ->assertOk()
            ->json('lignes.0');

        // Six jours de cours, mais seuls lundi et mardi sont écoulés : 1 / 2.
        $this->assertSame(2, $ligne['jours_attendus']);
        $this->assertSame(1, $ligne['jours_presents']);
        $this->assertEquals(50, $ligne['taux_assiduite']);
    }

    public function test_les_stats_de_periode_ignorent_les_jours_a_venir(): void
    {
        $enseignant = Enseignant::factory()->create();
        $this->coursLe($enseignant, $this->lundi);
        $this->coursLe($enseignant, $this->lundi->copy()->addDays(2));

        $this->actingAsBackoffice();

        $debut = $this->lundi->toDateString();
        $fin = $this->lundi->copy()->addDays(5)->toDateString();

        $ligne = collect($this->getJson("/api/assiduite/stats?debut={$debut}&fin={$fin}")->assertOk()->json())
            ->firstWhere('enseignant_id', $enseignant->id);

        $this->assertSame(1, $ligne['jours_attendus']);
    }

    public function test_mon_assiduite_ignore_la_fin_du_mois_a_venir(): void
    {
        $enseignant = Enseignant::factory()->create();
        // Cours tous les jours : sans garde, tout octobre compterait.
        foreach (range(1, 7) as $jour) {
            EmploiDuTemps::create([
                'enseignant_id' => $enseignant->id,
                'classe_id' => Classe::factory()->create()->id,
                'discipline_id' => Discipline::factory()->create()->id,
                'jour' => $jour,
                'heure_debut' => '08:00',
                'heure_fin' => '10:00',
            ]);
        }

        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $reponse = $this->getJson('/api/mon-assiduite')->assertOk();

        // Du 1er au 6 octobre inclus : 6 jours écoulés, pas les 31 du mois.
        $this->assertSame(6, $reponse->json('jours_attendus'));
    }

    public function test_aucune_alerte_dabsence_nest_generee_pour_un_jour_a_venir(): void
    {
        $enseignant = Enseignant::factory()->create();
        foreach (range(1, 7) as $jour) {
            EmploiDuTemps::create([
                'enseignant_id' => $enseignant->id,
                'classe_id' => Classe::factory()->create()->id,
                'discipline_id' => Discipline::factory()->create()->id,
                'jour' => $jour,
                'heure_debut' => '08:00',
                'heure_fin' => '09:00',
            ]);
        }

        $service = app(AbsenceDetectorService::class);

        $service->detecterPour($this->lundi->copy()->addDays(2));
        $this->assertDatabaseCount('absence_checkpoints', 0);

        // Contrôle négatif : un jour écoulé alimente bien le compteur.
        $service->detecterPour($this->lundi->copy());
        $this->assertDatabaseCount('absence_checkpoints', 1);
    }
}
