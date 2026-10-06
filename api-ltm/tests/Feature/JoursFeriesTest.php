<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Discipline;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Ferie;
use App\Models\Presence;
use App\Models\User;
use App\Services\AbsenceDetectorService;
use App\Services\HoraireAttendu;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un jour déclaré férié neutralise l'assiduité (§4.2) : personne n'y est
 * attendu, donc personne n'y est porté absent ni en retard, et le jour sort du
 * dénominateur des taux. La table `feries` n'était auparavant qu'un CRUD sans
 * lecteur, ces tests verrouillent chacun de ses consommateurs.
 */
class JoursFeriesTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $lundi;

    protected function setUp(): void
    {
        parent::setUp();
        // Semaine figée : la grille court du lundi au samedi, les assertions
        // doivent désigner un jour précis sans dépendre du jour d'exécution.
        $this->lundi = Carbon::parse('2026-10-05')->startOfWeek(Carbon::MONDAY);
    }

    private function actingAsBackoffice(): User
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    private function coursLe(Enseignant $enseignant, Carbon $jour, string $heureDebut = '08:00'): void
    {
        EmploiDuTemps::create([
            'enseignant_id' => $enseignant->id,
            'classe_id' => Classe::factory()->create()->id,
            'discipline_id' => Discipline::factory()->create()->id,
            'jour' => $jour->isoWeekday(),
            'heure_debut' => $heureDebut,
            'heure_fin' => '10:00',
        ]);
    }

    private function ferier(Carbon $jour, string $libelle = 'J.M.E'): Ferie
    {
        return Ferie::create(['date' => $jour->toDateString(), 'libelle' => $libelle]);
    }

    public function test_un_enseignant_nest_pas_attendu_un_jour_ferie(): void
    {
        $enseignant = Enseignant::factory()->create();
        $this->coursLe($enseignant, $this->lundi);
        $this->ferier($this->lundi);

        $horaires = app(HoraireAttendu::class);

        $this->assertFalse($horaires->estAttendu($enseignant, $this->lundi));
        $this->assertSame('J.M.E', $horaires->ferie($this->lundi));
        $this->assertNull($horaires->ferie($this->lundi->copy()->addDay()));
    }

    public function test_un_administratif_nest_pas_attendu_un_jour_ferie(): void
    {
        $administratif = Enseignant::factory()->create(['section' => 'Administration']);
        $this->ferier($this->lundi);

        $horaires = app(HoraireAttendu::class);

        // Le lundi comme le mardi sont ouvrés dans l'horaire fixe par défaut
        // (1 à 5) : seul le férié distingue les deux.
        $this->assertTrue($horaires->estAttendu($administratif, $this->lundi->copy()->addDay()));
        $this->assertFalse($horaires->estAttendu($administratif, $this->lundi));
    }

    public function test_la_grille_hebdomadaire_affiche_ferie_au_lieu_dabsent(): void
    {
        $enseignant = Enseignant::factory()->create(['nom' => 'PROF DU LUNDI']);
        $this->coursLe($enseignant, $this->lundi);
        $this->ferier($this->lundi);

        $this->actingAsBackoffice();

        $reponse = $this->getJson('/api/assiduite/journal-hebdomadaire?semaine='.$this->lundi->toDateString())
            ->assertOk();

        $ligne = $reponse->json('lignes.0');
        $cellule = collect($ligne['jours'])->firstWhere('date', $this->lundi->toDateString());

        $this->assertFalse($cellule['attendu']);
        $this->assertFalse($cellule['present']);
        $this->assertSame('J.M.E', $cellule['ferie']);

        // L'en-tête de colonne porte aussi le libellé.
        $this->assertSame('J.M.E', $reponse->json('jours.0.ferie'));
        $this->assertNull($reponse->json('jours.1.ferie'));
    }

    public function test_un_jour_ferie_sort_du_denominateur_du_taux(): void
    {
        $enseignant = Enseignant::factory()->create();
        $this->coursLe($enseignant, $this->lundi);
        $this->coursLe($enseignant, $this->lundi->copy()->addDay());

        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => $this->lundi->copy()->addDay()->toDateString(),
            'heure_arrivee' => $this->lundi->copy()->addDay()->setTime(7, 55),
            'source' => 'app_mobile',
        ]);

        $this->actingAsBackoffice();
        $url = '/api/assiduite/journal-hebdomadaire?semaine='.$this->lundi->toDateString();

        // Sans férié : deux jours attendus, un seul pointage, donc 50 %.
        $avant = $this->getJson($url)->assertOk()->json('lignes.0');
        $this->assertSame(2, $avant['jours_attendus']);
        $this->assertEquals(50, $avant['taux_assiduite']);

        $this->ferier($this->lundi);

        // Le lundi férié disparaît du calcul : il reste un jour attendu, pointé.
        $apres = $this->getJson($url)->assertOk()->json('lignes.0');
        $this->assertSame(1, $apres['jours_attendus']);
        $this->assertSame(1, $apres['jours_presents']);
        $this->assertEquals(100, $apres['taux_assiduite']);
    }

    public function test_les_stats_dassiduite_ignorent_les_jours_feries(): void
    {
        $enseignant = Enseignant::factory()->create();
        $this->coursLe($enseignant, $this->lundi);
        $this->ferier($this->lundi);

        $this->actingAsBackoffice();

        $debut = $this->lundi->toDateString();
        $fin = $this->lundi->copy()->addDays(5)->toDateString();

        $ligne = collect($this->getJson("/api/assiduite/stats?debut={$debut}&fin={$fin}")->assertOk()->json())
            ->firstWhere('enseignant_id', $enseignant->id);

        $this->assertSame(0, $ligne['jours_attendus']);
    }

    public function test_aucun_retard_nest_compte_un_jour_ferie(): void
    {
        $this->actingAsBackoffice();

        $enseignant = Enseignant::factory()->create();
        $this->coursLe($enseignant, now(), '08:00');

        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => now()->toDateString(),
            'heure_arrivee' => now()->toDateString().' 08:25:00',
        ]);

        // Arrivée 25 min après le cours, tolérance 10 min : 15 min de retard.
        $avant = collect($this->getJson('/api/retards')->assertOk()->json())
            ->firstWhere('enseignant_id', $enseignant->id);
        $this->assertSame(15, $avant['minutes_retard_total']);

        $this->ferier(now(), 'Fête de la jeunesse');

        // La ligne reste listée (l'endpoint couvre tout le périmètre) mais le
        // retard est ramené à zéro : la personne n'était pas attendue.
        $apres = collect($this->getJson('/api/retards')->assertOk()->json())
            ->firstWhere('enseignant_id', $enseignant->id);
        $this->assertSame(0, $apres['jours_retard']);
        $this->assertSame(0, $apres['minutes_retard_total']);
    }

    public function test_un_jour_ferie_ne_declenche_pas_dalerte_dabsence(): void
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

        $this->ferier($this->lundi);

        $service = app(AbsenceDetectorService::class);
        $service->detecterPour($this->lundi->copy());

        // Aucun pointage ce jour-là, mais il est férié : pas de compteur.
        $this->assertDatabaseCount('absence_checkpoints', 0);
        $this->assertDatabaseCount('absence_alert_logs', 0);
    }
}
