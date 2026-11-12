<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Discipline;
use App\Models\EmploiDuTemps;
use App\Models\EmploiDuTempsSpecial;
use App\Models\Enseignant;
use App\Models\PeriodeCours;
use App\Models\Presence;
use App\Models\User;
use App\Services\BilanIndividuel;
use App\Services\GrilleHoraire;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Configuration de la grille horaire (§4.2) : périodes de cours de
 * l'établissement, emplois du temps spéciaux du personnel à activité
 * générale, et signalement des chevauchements déjà présents en base.
 */
class PeriodesEtEmploiSpecialTest extends TestCase
{
    use RefreshDatabase;

    private const MERCREDI = '2026-09-30';

    private function actingAsBackoffice(): User
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    public function test_la_grille_historique_sert_de_periodes_par_defaut(): void
    {
        $this->actingAsBackoffice();

        $periodes = $this->getJson('/api/periodes-cours')->assertOk()->json('data');

        $this->assertCount(count(EmploiDuTemps::HEURES_DEBUT), $periodes);
        $this->assertSame('07:30', $periodes[0]['heure_debut']);
        $this->assertSame('08:10', $periodes[0]['heure_fin']);
    }

    public function test_une_periode_se_cree_et_apparait_dans_la_grille(): void
    {
        $this->actingAsBackoffice();
        PeriodeCours::query()->delete();

        $this->postJson('/api/periodes-cours', [
            'ordre' => 1, 'libelle' => 'P1', 'heure_debut' => '08:00', 'heure_fin' => '08:55',
        ])->assertCreated();

        $periodes = $this->getJson('/api/periodes-cours')->assertOk()->json('data');

        $this->assertCount(1, $periodes);
        $this->assertSame('P1 · 08h00–08h55', $periodes[0]['libelle_complet']);
    }

    public function test_une_pause_ne_figure_pas_parmi_les_periodes_de_cours(): void
    {
        $this->actingAsBackoffice();

        $this->postJson('/api/periodes-cours', [
            'libelle' => 'Récréation', 'est_pause' => true, 'heure_debut' => '09:30', 'heure_fin' => '09:50',
        ])->assertCreated();

        $cours = $this->getJson('/api/periodes-cours')->assertOk()->json('data');
        $pauses = $this->getJson('/api/periodes-cours?type=pause')->assertOk()->json('data');

        $this->assertSame([], array_values(array_filter($cours, fn ($periode) => $periode['est_pause'])));
        $this->assertCount(1, $pauses);
        $this->assertSame('09:30', $pauses[0]['heure_debut']);
    }

    public function test_une_pause_enjambee_ne_compte_pas_comme_une_periode(): void
    {
        GrilleHoraire::oublierPauses();
        PeriodeCours::create([
            'ordre' => 50, 'libelle' => 'Récréation', 'est_pause' => true,
            'heure_debut' => '09:30', 'heure_fin' => '09:50',
        ]);
        GrilleHoraire::oublierPauses();

        // 07h30 -> 10h30 = 180 minutes, dont 20 de récréation : 4 périodes.
        $this->assertSame(160, GrilleHoraire::minutesHorsPause('07:30', '10:30'));
        $this->assertSame(4, GrilleHoraire::periodesEntre('07:30', '10:30'));

        // Et inversement : 4 périodes à partir de 07h30 finissent à 10h30,
        // la récréation étant enjambée. Trois périodes s'arrêtent net à son
        // début, sans la déborder.
        $this->assertSame('10:30', GrilleHoraire::finApresPeriodes('07:30', 4));
        $this->assertSame('09:30', GrilleHoraire::finApresPeriodes('07:30', 3));
    }

    public function test_la_duree_dune_periode_se_configure(): void
    {
        $this->actingAsBackoffice();

        $this->getJson('/api/parametres/grille-horaire')
            ->assertOk()
            ->assertJsonPath('duree_periode_minutes', GrilleHoraire::DUREE_PERIODE_MINUTES);

        $this->putJson('/api/parametres/grille-horaire', ['duree_periode_minutes' => 55])
            ->assertOk()
            ->assertJsonPath('duree_periode_minutes', 55);

        // La nouvelle durée vaut pour les deux sens du calcul : l'heure de
        // fin déduite d'un nombre de périodes, et le décompte d'une plage.
        $this->assertSame(55, GrilleHoraire::dureePeriodeMinutes());
        $this->assertSame('09:20', GrilleHoraire::finApresPeriodes('07:30', 2));
        $this->assertSame(2, GrilleHoraire::periodesEntre('07:30', '09:20'));

        $this->putJson('/api/parametres/grille-horaire', ['duree_periode_minutes' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('duree_periode_minutes');
    }

    public function test_le_refus_de_suppression_nomme_les_creneaux_en_cause(): void
    {
        $this->actingAsBackoffice();

        $periode = PeriodeCours::where('heure_debut', '07:30')->firstOrFail();
        $enseignant = Enseignant::factory()->create(['nom' => 'Mme Nkolo']);
        $classe = Classe::factory()->create(['nom' => '6e A']);
        $discipline = Discipline::factory()->create(['nom' => 'Mathématiques']);

        EmploiDuTemps::create([
            'enseignant_id' => $enseignant->id,
            'classe_id' => $classe->id,
            'discipline_id' => $discipline->id,
            'jour' => 2,
            'heure_debut' => '07:30',
            'heure_fin' => '08:10',
        ]);

        $message = $this->deleteJson("/api/periodes-cours/{$periode->id}")
            ->assertStatus(422)
            ->json('errors.heure_debut.0');

        foreach (['mardi', '07:30', '6e A', 'Mathématiques', 'Mme Nkolo'] as $attendu) {
            $this->assertStringContainsString($attendu, $message);
        }

        // Et l'écran de configuration peut les lister pour les déplacer.
        $this->getJson("/api/periodes-cours/{$periode->id}/creneaux")
            ->assertOk()
            ->assertJsonPath('data.0.classe', '6e A')
            ->assertJsonPath('data.0.jour_libelle', 'mardi');
    }

    public function test_une_periode_ne_peut_pas_doubler_une_plage_existante(): void
    {
        $this->actingAsBackoffice();

        $this->postJson('/api/periodes-cours', [
            'ordre' => 99, 'heure_debut' => '07:30', 'heure_fin' => '08:10',
        ])->assertStatus(422)->assertJsonValidationErrors('heure_debut');
    }

    public function test_une_periode_utilisee_par_un_creneau_ne_se_supprime_pas(): void
    {
        $this->actingAsBackoffice();

        $periode = PeriodeCours::where('heure_debut', '07:30')->firstOrFail();

        EmploiDuTemps::create([
            'enseignant_id' => Enseignant::factory()->create()->id,
            'classe_id' => Classe::factory()->create()->id,
            'discipline_id' => Discipline::factory()->create()->id,
            'jour' => 1,
            'heure_debut' => '07:30',
            'heure_fin' => '08:10',
        ]);

        $this->deleteJson("/api/periodes-cours/{$periode->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('heure_debut');

        $this->assertDatabaseHas('periodes_cours', ['id' => $periode->id]);
    }

    public function test_les_conflits_existants_nomment_les_deux_seances(): void
    {
        $this->actingAsBackoffice();

        $enseignant = Enseignant::factory()->create(['nom' => 'NGONO Paul']);
        $discipline = Discipline::factory()->create();
        $premiere = Classe::factory()->create(['nom' => '1ere D']);
        $terminale = Classe::factory()->create(['nom' => 'Tle D']);

        // Insertion directe : le contrôleur refuse de créer le second créneau,
        // mais un import Excel ou des données antérieures au contrôle en
        // laissent en base — c'est précisément ce que l'écran doit montrer.
        foreach ([$premiere, $terminale] as $classe) {
            EmploiDuTemps::create([
                'enseignant_id' => $enseignant->id,
                'classe_id' => $classe->id,
                'discipline_id' => $discipline->id,
                'jour' => 2,
                'heure_debut' => '08:00',
                'heure_fin' => '09:00',
            ]);
        }

        $conflits = $this->getJson('/api/emplois/conflits')->assertOk()->json('data');

        $this->assertCount(1, $conflits);
        $this->assertSame(2, $conflits[0]['jour']);
        $this->assertSame(['enseignant'], $conflits[0]['motifs']);
        $this->assertSame('NGONO Paul', $conflits[0]['a']['enseignant']['nom']);
        $this->assertSame(
            ['1ere D', 'Tle D'],
            [$conflits[0]['a']['classe']['nom'], $conflits[0]['b']['classe']['nom']],
        );
    }

    public function test_deux_creneaux_disjoints_ne_sont_pas_un_conflit(): void
    {
        $this->actingAsBackoffice();

        $enseignant = Enseignant::factory()->create();
        $discipline = Discipline::factory()->create();

        foreach ([['08:00', '09:00'], ['09:00', '10:00']] as [$debut, $fin]) {
            EmploiDuTemps::create([
                'enseignant_id' => $enseignant->id,
                'classe_id' => Classe::factory()->create()->id,
                'discipline_id' => $discipline->id,
                'jour' => 2,
                'heure_debut' => $debut,
                'heure_fin' => $fin,
            ]);
        }

        $this->getJson('/api/emplois/conflits')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_le_message_de_conflit_nomme_la_seance_en_cause(): void
    {
        $this->actingAsBackoffice();

        $enseignant = Enseignant::factory()->create(['nom' => 'ABENA Rose']);
        $classe = Classe::factory()->create(['nom' => 'Tle C']);
        $discipline = Discipline::factory()->create(['nom' => 'Physique']);

        $base = [
            'enseignant_id' => $enseignant->id,
            'classe_id' => $classe->id,
            'discipline_id' => $discipline->id,
            'jour' => 1,
            'heure_debut' => '08:00',
            'heure_fin' => '09:00',
        ];

        $this->postJson('/api/emplois', $base)->assertCreated();

        $message = $this->postJson('/api/emplois', [...$base, 'heure_debut' => '08:30', 'heure_fin' => '09:30'])
            ->assertStatus(422)
            ->json('errors.heure_debut.0');

        $this->assertStringContainsString('Tle C', $message);
        $this->assertStringContainsString('Physique', $message);
        $this->assertStringContainsString('ABENA Rose', $message);
        $this->assertStringContainsString('lundi', $message);
    }

    public function test_une_seule_plage_speciale_par_jour_et_par_personne(): void
    {
        $this->actingAsBackoffice();

        $enseignant = Enseignant::factory()->create();

        $this->postJson('/api/emplois-speciaux', [
            'enseignant_id' => $enseignant->id, 'jour' => 3, 'heure_debut' => '07:30', 'heure_fin' => '15:30',
        ])->assertCreated();

        $this->postJson('/api/emplois-speciaux', [
            'enseignant_id' => $enseignant->id, 'jour' => 3, 'heure_debut' => '08:00', 'heure_fin' => '12:00',
        ])->assertStatus(422)->assertJsonValidationErrors('jour');
    }

    public function test_lemploi_special_prime_sur_les_cours_et_annule_le_rattrapage(): void
    {
        $this->travelTo(Carbon::parse(self::MERCREDI.' 18:00:00'));

        $enseignant = Enseignant::factory()->create();

        // Des cours existent aussi : ils ne doivent plus décider de rien.
        EmploiDuTemps::create([
            'enseignant_id' => $enseignant->id,
            'classe_id' => Classe::factory()->create()->id,
            'discipline_id' => Discipline::factory()->create()->id,
            'jour' => 3,
            'heure_debut' => '07:30',
            'heure_fin' => '08:10',
        ]);

        EmploiDuTempsSpecial::create([
            'enseignant_id' => $enseignant->id, 'jour' => 3, 'heure_debut' => '07:00', 'heure_fin' => '16:00',
        ]);

        // Départ très anticipé : sans emploi du temps spécial, il vaudrait
        // plusieurs périodes à rattraper.
        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => self::MERCREDI,
            'heure_arrivee' => self::MERCREDI.' 07:00:00',
            'heure_depart' => self::MERCREDI.' 09:00:00',
        ]);

        $jour = Carbon::parse(self::MERCREDI);
        $fiche = app(BilanIndividuel::class)->donnees($enseignant, $jour->copy(), $jour->copy());

        $this->assertTrue($fiche['horaire_special']);
        $this->assertCount(1, $fiche['details']);
        $this->assertSame('07:00', $fiche['details'][0]['heure_debut_prevue']);
        $this->assertSame('16:00', $fiche['details'][0]['heure_fin_prevue']);
        $this->assertSame(0, $fiche['details'][0]['periodes_a_rattraper']);
        $this->assertSame(0, $fiche['total_periodes_a_rattraper']);
        $this->assertSame(0, $fiche['total_periodes_absence']);
        // Le départ anticipé reste compté : seules les périodes disparaissent.
        $this->assertGreaterThan(0, $fiche['details'][0]['anticipation_minutes']);
    }

    public function test_un_jour_hors_emploi_special_nest_pas_attendu(): void
    {
        // Le mercredi est la seule plage déclarée : le jeudi sort du calcul,
        // cours ou pas.
        $this->travelTo(Carbon::parse('2026-10-02 18:00:00'));

        $enseignant = Enseignant::factory()->create();

        EmploiDuTempsSpecial::create([
            'enseignant_id' => $enseignant->id, 'jour' => 3, 'heure_debut' => '07:00', 'heure_fin' => '16:00',
        ]);

        $jeudi = Carbon::parse('2026-10-01');
        $fiche = app(BilanIndividuel::class)->donnees($enseignant, $jeudi->copy(), $jeudi->copy());

        $this->assertSame([], $fiche['details']);
        $this->assertSame(0, $fiche['jours_attendus']);
    }
}
