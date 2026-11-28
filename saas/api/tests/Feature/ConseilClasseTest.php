<?php

namespace Tests\Feature;

use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\ClasseModule;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Inscription;
use App\Models\Module;
use App\Models\NoteModule;
use App\Models\Specialite;
use App\Models\User;
use App\Services\AnneeScolaire;
use App\Services\BaremeApc;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Calcul des bulletins et conseil de classe (§bulletins APC, lot 5).
 *
 * Les notes de NGONO sont celles du bulletin de référence (3ᵉ trimestre,
 * 1re année TPV) : C10 52+35=87, C12 54+36=90, C5 47+31=78. Les crédits
 * (10, 8, 4) donnent (870 + 720 + 312) / 22 = 86,45… → 86,5, mention B.
 */
class ConseilClasseTest extends TestCase
{
    use RefreshDatabase;

    private Classe $classe;

    private Specialite $specialite;

    /** @var array<string, ClasseModule> */
    private array $maquette = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-20 09:00:00'));

        $this->specialite = Specialite::create(['code' => 'TPV', 'nom' => 'Transformation des produits végétaux']);
        $this->classe = Classe::create([
            'nom' => '1re année TPV',
            'code' => '1TPV',
            'niveau' => 'Seconde',
            'specialite_id' => $this->specialite->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function actingAsBackoffice(): User
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    private function module(string $code, ?int $credit, array $trimestres = [1, 2, 3], int $ordre = 0, int $vh = 150): ClasseModule
    {
        $module = Module::create([
            'specialite_id' => $this->specialite->id,
            'niveau' => 'Seconde',
            'code' => $code,
            'intitule' => "Module {$code}",
            'bloc' => Module::BLOC_COMPETENCES_PARTICULIERES,
            'credit' => $credit,
            'ordre' => $ordre,
        ]);

        return $this->maquette[$code] = ClasseModule::create([
            'classe_id' => $this->classe->id,
            'module_id' => $module->id,
            'credit' => $credit,
            'volume_horaire' => $vh,
            'trimestres' => $trimestres,
            'ordre' => $ordre,
        ]);
    }

    private function eleve(string $matricule, string $nom): Inscription
    {
        $eleve = Eleve::create(['matricule' => $matricule, 'nom' => $nom]);

        return Inscription::create([
            'eleve_id' => $eleve->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire' => AnneeScolaire::courante(),
        ]);
    }

    private function note(Inscription $inscription, string $code, int $trimestre, float $uea, float $integratrice): NoteModule
    {
        return NoteModule::updateOrCreate(
            ['inscription_id' => $inscription->id, 'classe_module_id' => $this->maquette[$code]->id, 'trimestre' => $trimestre],
            ['moyenne_uea' => $uea, 'note_integratrice' => $integratrice],
        );
    }

    /** NGONO au 3ᵉ trimestre, d'après le bulletin de référence. */
    private function ngono(int $trimestre = 3): Inscription
    {
        $ngono = $this->eleve('TPV25-0142', 'NGONO');
        $this->note($ngono, 'C10', $trimestre, 52, 35);
        $this->note($ngono, 'C12', $trimestre, 54, 36);
        $this->note($ngono, 'C5', $trimestre, 47, 31);

        return $ngono;
    }

    private function maquetteDeReference(): void
    {
        $this->module('C10', 10, ordre: 1);
        $this->module('C12', 8, ordre: 2);
        $this->module('C5', 4, ordre: 3);
    }

    private function conseil(string $action, string|int $periode = 3, array $corps = [])
    {
        return $this->postJson("/api/conseil/{$action}", [
            'classe_id' => $this->classe->id,
            'periode' => $periode,
            ...$corps,
        ]);
    }

    private function bulletinDe(Inscription $inscription, string $periode = '3'): Bulletin
    {
        return Bulletin::where('inscription_id', $inscription->id)->where('periode', $periode)->courants()->sole();
    }

    /* ------------------------------------------------------------------ */
    /* Calcul                                                              */
    /* ------------------------------------------------------------------ */

    public function test_le_bulletin_de_reference_est_recalcule_a_lidentique(): void
    {
        $this->actingAsBackoffice();
        $this->maquetteDeReference();
        $ngono = $this->ngono();

        $etat = $this->conseil('generer')->assertOk()->json();

        $this->assertSame('brouillon', $etat['statut']);
        $this->assertTrue($etat['a_jour']);
        $this->assertSame([], $etat['obstacles']);

        $bulletin = $this->bulletinDe($ngono);
        $this->assertSame(86.5, $bulletin->moyenne_generale);
        $this->assertSame('B', $bulletin->mention);
        $this->assertSame(22, $bulletin->total_credits);
        $this->assertSame(450, $bulletin->total_volume_horaire);
        $this->assertSame(0, $bulletin->competences_non_validees);
        $this->assertSame(BaremeApc::VALIDATION_OUI, $bulletin->validation_competences);
        $this->assertSame(BaremeApc::DECISION_SUCCES, $bulletin->decision_proposee);
        $this->assertSame(1, $bulletin->rang);
        $this->assertSame('1TPV', $bulletin->classe_code);

        $lignes = $bulletin->lignes;
        $this->assertSame(['C10', 'C12', 'C5'], $lignes->pluck('code')->all());
        $this->assertSame(87.0, $lignes[0]->note_finale);
        $this->assertSame(52.0, $lignes[0]->moyenne_uea);
        $this->assertSame(35.0, $lignes[0]->note_integratrice);
        $this->assertSame('B', $lignes[0]->mention);
        $this->assertSame('S', $lignes[0]->sanction);
        $this->assertSame('Très bien', $lignes[0]->appreciation);
        $this->assertSame('A', $lignes[1]->mention);
        $this->assertSame('D', $lignes[2]->mention);
        $this->assertSame('Assez bien', $lignes[2]->appreciation);
    }

    /** Le tiret du bulletin : hors totaux, jamais un zéro. */
    public function test_un_module_non_programme_au_trimestre_est_imprime_hors_totaux(): void
    {
        $this->actingAsBackoffice();
        $this->maquetteDeReference();
        $this->module('C7', 6, trimestres: [2, 3], ordre: 4);
        $ngono = $this->eleve('TPV25-0142', 'NGONO');
        $this->note($ngono, 'C10', 1, 52, 35);
        $this->note($ngono, 'C12', 1, 54, 36);
        $this->note($ngono, 'C5', 1, 47, 31);

        $this->conseil('generer', 1)->assertOk();

        $bulletin = $this->bulletinDe($ngono, '1');
        $c7 = $bulletin->lignes->firstWhere('code', 'C7');

        $this->assertTrue($c7->hors_totaux);
        $this->assertFalse($c7->note_manquante);
        $this->assertNull($c7->note_finale);
        $this->assertSame(0, $bulletin->notes_manquantes);
        $this->assertSame(22, $bulletin->total_credits);
        $this->assertSame(86.5, $bulletin->moyenne_generale);
    }

    /**
     * Une note manquante ne vaut pas zéro, mais la moyenne des seuls modules
     * notés serait plus flatteuse que la vraie : le bulletin n'est ni classé,
     * ni décidé, ni validable.
     */
    public function test_un_bulletin_incomplet_nest_ni_classe_ni_decide_ni_validable(): void
    {
        $this->actingAsBackoffice();
        $this->maquetteDeReference();
        $this->ngono();
        $tchamba = $this->eleve('TPV25-0157', 'TCHAMBA');
        $this->note($tchamba, 'C10', 3, 30, 20);

        $etat = $this->conseil('generer')->assertOk()->json();

        $bulletin = $this->bulletinDe($tchamba);
        $this->assertSame(2, $bulletin->notes_manquantes);
        $this->assertNull($bulletin->rang);
        $this->assertNull($bulletin->decision_proposee);
        $this->assertSame(BaremeApc::VALIDATION_NON_PARTIEL, $bulletin->validation_competences);
        $this->assertSame(1, $bulletin->effectif_classe);
        $this->assertSame(2, $bulletin->effectif);

        $this->assertStringContainsString('incomplet', implode(' ', $etat['obstacles']));
        $this->conseil('valider')->assertStatus(422);
        $this->assertSame(0, NoteModule::whereNotNull('verrouillee_at')->count());
    }

    public function test_les_rangs_partagent_les_ex_aequo_et_la_classe_a_ses_moyennes(): void
    {
        $this->actingAsBackoffice();
        $this->module('C10', 10);

        $notes = ['A' => [55, 38], 'B' => [50, 30], 'C' => [50, 30], 'D' => [40, 20]];
        $inscriptions = [];
        foreach ($notes as $nom => [$uea, $integratrice]) {
            $inscriptions[$nom] = $this->eleve("M-{$nom}", $nom);
            $this->note($inscriptions[$nom], 'C10', 3, $uea, $integratrice);
        }

        $etat = $this->conseil('generer')->assertOk()->json();

        $rangs = array_map(fn (Inscription $i) => $this->bulletinDe($i)->rang, $inscriptions);
        $this->assertSame(['A' => 1, 'B' => 2, 'C' => 2, 'D' => 4], $rangs);
        $this->assertTrue($this->bulletinDe($inscriptions['B'])->ex_aequo);
        $this->assertFalse($this->bulletinDe($inscriptions['A'])->ex_aequo);

        $this->assertSame(BaremeApc::POSITION_TETE, $this->bulletinDe($inscriptions['A'])->position_groupe);
        $this->assertSame(BaremeApc::POSITION_DIFFICULTE, $this->bulletinDe($inscriptions['D'])->position_groupe);

        // (93 + 80 + 80 + 60) / 4 = 78,25 → 78,3
        $this->assertEquals(78.3, $etat['moyenne_classe']);
        $this->assertEquals(93, $etat['moyenne_max']);
        $this->assertEquals(60, $etat['moyenne_min']);
        $this->assertSame(['A', 'B', 'C', 'D'], array_column($etat['eleves'], 'nom_complet'));

        // D (60) est sous le seuil et n'a aucune compétence validée : échec.
        $this->assertSame(BaremeApc::DECISION_ECHEC, $this->bulletinDe($inscriptions['D'])->decision_proposee);
    }

    /**
     * Annuel : moyenne des trimestres programmés ; un module ouvert au T2
     * n'a pas de zéro au T1 ; un module non programmé garde sa ligne mais
     * sort des totaux.
     */
    public function test_le_bulletin_annuel_moyenne_les_trimestres_programmes(): void
    {
        $this->actingAsBackoffice();
        $this->module('C10', 10, ordre: 1);
        $this->module('C7', 6, trimestres: [2, 3], ordre: 2);
        $this->module('STAGE', 12, trimestres: [], ordre: 3);
        $ngono = $this->eleve('TPV25-0142', 'NGONO');

        $this->note($ngono, 'C10', 1, 50, 30); // 80
        $this->note($ngono, 'C10', 2, 52, 33); // 85
        $this->note($ngono, 'C10', 3, 52, 35); // 87
        $this->note($ngono, 'C7', 2, 40, 30);  // 70
        $this->note($ngono, 'C7', 3, 45, 30);  // 75

        $this->conseil('generer', 'annuel')->assertOk();
        $bulletin = $this->bulletinDe($ngono, 'annuel');

        $this->assertSame(Bulletin::TYPE_ANNUEL, $bulletin->type);
        $c10 = $bulletin->lignes->firstWhere('code', 'C10');
        $c7 = $bulletin->lignes->firstWhere('code', 'C7');
        $stage = $bulletin->lignes->firstWhere('code', 'STAGE');

        $this->assertSame(84.0, $c10->note_finale);          // (80+85+87)/3
        $this->assertSame(72.5, $c7->note_finale);           // (70+75)/2, pas /3
        $this->assertNull($c7->notes_trimestres['1']);
        $this->assertSame([2, 3], $c7->trimestres);
        $this->assertTrue($stage->hors_totaux);
        $this->assertFalse($stage->note_manquante);

        $this->assertSame(16, $bulletin->total_credits);
        // (84×10 + 72,5×6) / 16 = 79,6875 → 79,7
        $this->assertSame(79.7, $bulletin->moyenne_generale);
        $this->assertSame(BaremeApc::DECISION_ADMIS, $bulletin->decision_proposee);
    }

    public function test_un_trimestre_programme_sans_note_rend_la_ligne_annuelle_incomplete(): void
    {
        $this->actingAsBackoffice();
        $this->module('C10', 10);
        $ngono = $this->eleve('TPV25-0142', 'NGONO');
        $this->note($ngono, 'C10', 1, 50, 30);
        $this->note($ngono, 'C10', 3, 52, 35);

        $this->conseil('generer', 'annuel')->assertOk();
        $ligne = $this->bulletinDe($ngono, 'annuel')->lignes->sole();

        $this->assertTrue($ligne->note_manquante);
        $this->assertNull($ligne->note_finale);
    }

    public function test_un_credit_manquant_sur_la_maquette_bloque_le_calcul(): void
    {
        $this->actingAsBackoffice();
        $this->module('C10', null);
        $this->eleve('TPV25-0142', 'NGONO');

        $reponse = $this->conseil('generer')->assertStatus(422);

        $this->assertStringContainsString('C10', $reponse->json('errors.classe_id.0'));
        $this->assertSame(0, Bulletin::count());
    }

    /* ------------------------------------------------------------------ */
    /* Décisions du conseil                                                */
    /* ------------------------------------------------------------------ */

    public function test_le_conseil_modifie_une_decision_avec_trace(): void
    {
        $user = $this->actingAsBackoffice();
        $this->maquetteDeReference();
        $ngono = $this->ngono();
        $this->conseil('generer')->assertOk();
        $bulletin = $this->bulletinDe($ngono);

        $this->putJson("/api/bulletins/{$bulletin->id}", [
            'decision' => BaremeApc::DECISION_REMEDIATION,
            'observation' => 'Doit consolider C5.',
        ])->assertOk()
            ->assertJsonPath('decision', BaremeApc::DECISION_REMEDIATION)
            ->assertJsonPath('decision_proposee', BaremeApc::DECISION_SUCCES);

        $bulletin->refresh();
        $this->assertSame($user->id, $bulletin->decision_conseil_par);
        $this->assertNotNull($bulletin->decision_conseil_at);
        $this->assertSame('Doit consolider C5.', $bulletin->observation);

        // Revenir à la proposition efface le choix du conseil.
        $this->putJson("/api/bulletins/{$bulletin->id}", ['decision' => BaremeApc::DECISION_SUCCES])->assertOk();
        $this->assertNull($bulletin->refresh()->decision_conseil);

        // Une décision annuelle n'a pas de sens sur un trimestre.
        $this->putJson("/api/bulletins/{$bulletin->id}", ['decision' => BaremeApc::DECISION_ADMIS])
            ->assertStatus(422)->assertJsonValidationErrors('decision');
    }

    /** Recalculer un brouillon ne fait pas perdre le travail du conseil. */
    public function test_recalculer_garde_la_decision_et_lobservation_du_conseil(): void
    {
        $this->actingAsBackoffice();
        $this->maquetteDeReference();
        $ngono = $this->ngono();
        $this->conseil('generer')->assertOk();
        $bulletin = $this->bulletinDe($ngono);
        $this->putJson("/api/bulletins/{$bulletin->id}", [
            'decision' => BaremeApc::DECISION_REMEDIATION,
            'observation' => 'Bon trimestre.',
        ])->assertOk();

        $this->note($ngono, 'C5', 3, 50, 31);
        $this->conseil('generer')->assertOk();

        $apres = $this->bulletinDe($ngono);
        $this->assertSame($bulletin->id, $apres->id);
        $this->assertSame(1, $apres->version);
        $this->assertSame(BaremeApc::DECISION_REMEDIATION, $apres->decision_conseil);
        $this->assertSame('Bon trimestre.', $apres->observation);
        $this->assertSame(81.0, $apres->lignes->firstWhere('code', 'C5')->note_finale);
        $this->assertSame(3, $apres->lignes->count());
    }

    /* ------------------------------------------------------------------ */
    /* Validation, verrous, réouverture                                    */
    /* ------------------------------------------------------------------ */

    public function test_valider_fige_les_bulletins_et_verrouille_les_notes(): void
    {
        $user = $this->actingAsBackoffice();
        $this->maquetteDeReference();
        $ngono = $this->ngono();
        $this->note($ngono, 'C10', 1, 50, 30);
        $this->conseil('generer')->assertOk();

        $this->conseil('valider')->assertOk()->assertJsonPath('statut', 'valide');

        $bulletin = $this->bulletinDe($ngono);
        $this->assertSame(Bulletin::STATUT_VALIDE, $bulletin->statut);
        $this->assertSame($user->id, $bulletin->valide_par);

        // Les notes du trimestre validé sont verrouillées, pas celles des autres.
        $this->assertSame(3, NoteModule::where('trimestre', 3)->whereNotNull('verrouillee_at')->count());
        $this->assertNull(NoteModule::where('trimestre', 1)->sole()->verrouillee_at);

        $this->putJson('/api/notes/bulk', [
            'classe_id' => $this->classe->id,
            'trimestre' => 3,
            'classe_module_id' => $this->maquette['C10']->id,
            'notes' => [['inscription_id' => $ngono->id, 'moyenne_uea' => 60, 'note_integratrice' => 40]],
        ])->assertStatus(422);

        // Le bulletin validé ne se recalcule ni ne se modifie.
        $this->conseil('generer')->assertStatus(422);
        $this->putJson("/api/bulletins/{$bulletin->id}", ['observation' => 'Après coup'])->assertStatus(422);
    }

    /** Un conseil ne fige pas des bulletins calculés avant la dernière correction. */
    public function test_la_validation_refuse_des_bulletins_perimes(): void
    {
        $this->actingAsBackoffice();
        $this->maquetteDeReference();
        $ngono = $this->ngono();
        $this->conseil('generer')->assertOk();

        $this->note($ngono, 'C5', 3, 50, 31);

        $etat = $this->getJson("/api/conseil?classe_id={$this->classe->id}&periode=3")->assertOk()->json();
        $this->assertFalse($etat['a_jour']);

        $this->conseil('valider')->assertStatus(422);
        $this->assertSame(Bulletin::STATUT_BROUILLON, $this->bulletinDe($ngono)->statut);
    }

    /**
     * Le snapshot n'est pas optionnel : corriger un crédit après coup ne
     * réécrit pas un bulletin signé.
     */
    public function test_un_bulletin_valide_ne_suit_pas_les_corrections_de_la_maquette(): void
    {
        $this->actingAsBackoffice();
        $this->maquetteDeReference();
        $ngono = $this->ngono();
        $this->conseil('generer')->assertOk();
        $this->conseil('valider')->assertOk();

        $this->maquette['C10']->update(['credit' => 2]);
        $this->maquette['C10']->module->update(['intitule' => 'Renommé']);

        $bulletin = $this->bulletinDe($ngono);
        $c10 = $bulletin->lignes->firstWhere('code', 'C10');
        $this->assertSame(10, $c10->credit);
        $this->assertSame('Module C10', $c10->intitule);
        $this->assertSame(86.5, $bulletin->moyenne_generale);
    }

    /** Rouvrir conserve la version signée et fait de la suivante une version 2. */
    public function test_rouvrir_conserve_la_version_signee_et_deverrouille(): void
    {
        $user = $this->actingAsBackoffice();
        $this->maquetteDeReference();
        $ngono = $this->ngono();
        $this->conseil('generer')->assertOk();
        $v1 = $this->bulletinDe($ngono);
        $this->putJson("/api/bulletins/{$v1->id}", ['observation' => 'Bon trimestre.'])->assertOk();
        $this->conseil('valider')->assertOk();

        $this->conseil('rouvrir')->assertStatus(422)->assertJsonValidationErrors('motif');
        $this->conseil('rouvrir', corps: ['motif' => 'Erreur de saisie en C5'])->assertOk()
            ->assertJsonPath('statut', 'aucun')
            ->assertJsonPath('versions_remplacees', 1);

        $v1->refresh();
        $this->assertNotNull($v1->remplace_at);
        $this->assertSame($user->id, $v1->remplace_par);
        $this->assertSame('Erreur de saisie en C5', $v1->motif_reouverture);
        $this->assertSame(Bulletin::STATUT_VALIDE, $v1->statut);
        $this->assertSame(0, NoteModule::whereNotNull('verrouillee_at')->count());

        $this->note($ngono, 'C5', 3, 50, 31);
        $this->conseil('generer')->assertOk();

        $v2 = $this->bulletinDe($ngono);
        $this->assertNotSame($v1->id, $v2->id);
        $this->assertSame(2, $v2->version);
        $this->assertSame('Bon trimestre.', $v2->observation);
        // La version signée n'a pas bougé.
        $this->assertSame(78.0, $v1->lignes()->where('code', 'C5')->sole()->note_finale);
    }

    /** L'annuel repose sur les notes des trimestres : il se rouvre en premier. */
    public function test_un_trimestre_ne_se_rouvre_pas_sous_un_annuel_valide(): void
    {
        $this->actingAsBackoffice();
        $this->module('C10', 10);
        $ngono = $this->eleve('TPV25-0142', 'NGONO');
        foreach ([1, 2, 3] as $t) {
            $this->note($ngono, 'C10', $t, 50, 30);
        }

        $this->conseil('generer', 1)->assertOk();
        $this->conseil('valider', 1)->assertOk();
        $this->conseil('generer', 'annuel')->assertOk();
        $this->conseil('valider', 'annuel')->assertOk();
        $this->assertSame(3, NoteModule::whereNotNull('verrouillee_at')->count());

        $this->conseil('rouvrir', 1, ['motif' => 'Correction'])->assertStatus(422);

        // Rouvrir l'annuel libère T2 et T3, mais T1 reste tenu par son bulletin.
        $this->conseil('rouvrir', 'annuel', ['motif' => 'Correction'])->assertOk();
        $this->assertNotNull(NoteModule::where('trimestre', 1)->sole()->verrouillee_at);
        $this->assertNull(NoteModule::where('trimestre', 2)->sole()->verrouillee_at);
    }

    public function test_le_detail_dun_bulletin_porte_ses_lignes(): void
    {
        $this->actingAsBackoffice();
        $this->maquetteDeReference();
        $ngono = $this->ngono();
        $this->conseil('generer')->assertOk();

        $this->getJson('/api/bulletins/'.$this->bulletinDe($ngono)->id)
            ->assertOk()
            ->assertJsonPath('eleve.matricule', 'TPV25-0142')
            ->assertJsonPath('classe.code', '1TPV')
            ->assertJsonCount(3, 'lignes')
            ->assertJsonPath('lignes.0.code', 'C10');
    }

    /* ------------------------------------------------------------------ */
    /* Périmètre                                                           */
    /* ------------------------------------------------------------------ */

    public function test_un_enseignant_lit_le_conseil_mais_ne_le_conduit_pas(): void
    {
        $this->maquetteDeReference();
        $this->ngono();

        $enseignant = Enseignant::factory()->create();
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $this->getJson("/api/conseil?classe_id={$this->classe->id}&periode=3")
            ->assertOk()->assertJsonPath('statut', 'aucun');
        $this->conseil('generer')->assertForbidden();
        $this->conseil('valider')->assertForbidden();

        $this->assertSame(0, Bulletin::count());
    }
}
