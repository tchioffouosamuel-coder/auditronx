<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\ClasseModule;
use App\Models\Enseignant;
use App\Models\Module;
use App\Models\Specialite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Maquette d'une classe : le passage du référentiel d'une spécialité à ce que
 * cette classe suit réellement cette année.
 *
 * Le référentiel décrit une formation, la maquette décrit une classe, et les
 * deux divergent : crédits propres, enseignant signataire, et surtout
 * trimestres de programmation — c'est d'eux que sortent les tirets du
 * bulletin annuel et l'exclusion des modules non programmés des totaux.
 */
class MaquetteClasseTest extends TestCase
{
    use RefreshDatabase;

    private Specialite $specialite;

    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->specialite = Specialite::create(['code' => 'TPV', 'nom' => 'Transformation des produits végétaux']);
        $this->classe = Classe::create([
            'nom' => '1re année TPV',
            'code' => '1TPV',
            'niveau' => 'Seconde',
            'specialite_id' => $this->specialite->id,
            'effectif' => 28,
        ]);
    }

    private function actingAsBackoffice(): User
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    private function module(string $code, string $bloc, ?int $credit, ?int $vh, int $ordre = 0): Module
    {
        return Module::create([
            'specialite_id' => $this->specialite->id,
            'niveau' => 'Seconde',
            'code' => $code,
            'intitule' => "Module {$code}",
            'bloc' => $bloc,
            'credit' => $credit,
            'volume_horaire' => $vh,
            'ordre' => $ordre,
        ]);
    }

    /** Le référentiel réduit du cas pratique TPV. */
    private function referentiel(): void
    {
        $this->module('C10', Module::BLOC_COMPETENCES_PARTICULIERES, 10, 150, 1);
        $this->module('C12', Module::BLOC_COMPETENCES_PARTICULIERES, 10, 135, 2);
        $this->module('C13', Module::BLOC_COMPETENCES_PARTICULIERES, 20, 300, 3);
        $this->module('C1', Module::BLOC_COMPETENCES_GENERALES, 2, 30, 1);
        $this->module('C5', Module::BLOC_COMPETENCES_GENERALES, 9, 150, 2);
    }

    /* ------------------------------------------------------------------ */
    /* Initialisation depuis le référentiel                                */
    /* ------------------------------------------------------------------ */

    public function test_la_maquette_reprend_le_referentiel_de_la_specialite(): void
    {
        $this->actingAsBackoffice();
        $this->referentiel();

        $reponse = $this->postJson("/api/classes/{$this->classe->id}/maquette/initialiser")->assertOk();

        $reponse->assertJsonPath('bilan.ajoutes', 5);
        $reponse->assertJsonPath('totaux.modules', 5);
        $reponse->assertJsonPath('totaux.credits', 51);
        $reponse->assertJsonPath('totaux.volume_horaire', 765);

        // Crédits et heures sont recopiés, pas référencés.
        $ligne = ClasseModule::whereRelation('module', 'code', 'C10')->firstOrFail();
        $this->assertSame(10, $ligne->credit);
        $this->assertSame(150, $ligne->volume_horaire);
        $this->assertSame([1, 2, 3], $ligne->trimestres);
    }

    public function test_les_lignes_sortent_dans_lordre_des_blocs_du_bulletin(): void
    {
        $this->actingAsBackoffice();
        $this->referentiel();
        $this->postJson("/api/classes/{$this->classe->id}/maquette/initialiser")->assertOk();

        $codes = collect($this->getJson("/api/classes/{$this->classe->id}/maquette")->assertOk()->json('lignes'))
            ->pluck('code')->all();

        $this->assertSame(['C10', 'C12', 'C13', 'C1', 'C5'], $codes);
    }

    /** Rejouable : une seconde initialisation ne duplique rien. */
    public function test_reinitialiser_najoute_que_ce_qui_manque(): void
    {
        $this->actingAsBackoffice();
        $this->referentiel();
        $this->postJson("/api/classes/{$this->classe->id}/maquette/initialiser")->assertOk();

        $this->module('C7', Module::BLOC_COMPETENCES_GENERALES, 2, 30, 3);

        $this->postJson("/api/classes/{$this->classe->id}/maquette/initialiser")
            ->assertOk()
            ->assertJsonPath('bilan.ajoutes', 1)
            ->assertJsonPath('totaux.modules', 6);
    }

    /**
     * Le classeur de l'organisation pédagogique ne porte pas les crédits : ils
     * se saisissent au référentiel après coup, souvent après l'initialisation
     * des maquettes. Un crédit resté vide doit donc se rattraper tout seul.
     */
    public function test_un_credit_saisi_apres_coup_au_referentiel_complete_les_maquettes(): void
    {
        $this->actingAsBackoffice();
        $module = $this->module('C3', Module::BLOC_COMPETENCES_GENERALES, null, 135);

        $this->postJson("/api/classes/{$this->classe->id}/maquette/initialiser")->assertOk();
        $this->assertNull(ClasseModule::firstOrFail()->credit);

        $module->update(['credit' => 9]);

        $this->postJson("/api/classes/{$this->classe->id}/maquette/initialiser")
            ->assertOk()
            ->assertJsonPath('bilan.completes', 1);

        $this->assertSame(9, ClasseModule::firstOrFail()->credit);
    }

    /** Une valeur propre à la classe n'est pas écrasée par le référentiel… */
    public function test_une_valeur_propre_a_la_classe_survit_a_une_reinitialisation(): void
    {
        $this->actingAsBackoffice();
        $this->module('C3', Module::BLOC_COMPETENCES_GENERALES, 9, 135);

        $this->postJson("/api/classes/{$this->classe->id}/maquette/initialiser")->assertOk();
        ClasseModule::firstOrFail()->update(['credit' => 7]);

        $this->postJson("/api/classes/{$this->classe->id}/maquette/initialiser")->assertOk();

        $this->assertSame(7, ClasseModule::firstOrFail()->credit);
    }

    /** … sauf demande explicite de réalignement. */
    public function test_rafraichir_realigne_la_maquette_sur_le_referentiel(): void
    {
        $this->actingAsBackoffice();
        $this->module('C3', Module::BLOC_COMPETENCES_GENERALES, 9, 135);

        $this->postJson("/api/classes/{$this->classe->id}/maquette/initialiser")->assertOk();
        ClasseModule::firstOrFail()->update(['credit' => 7]);

        $this->postJson("/api/classes/{$this->classe->id}/maquette/initialiser", ['rafraichir' => true])->assertOk();

        $this->assertSame(9, ClasseModule::firstOrFail()->credit);
    }

    /* ------------------------------------------------------------------ */
    /* Ce qui empêche une maquette                                         */
    /* ------------------------------------------------------------------ */

    public function test_une_classe_sans_specialite_dit_pourquoi_elle_na_pas_de_maquette(): void
    {
        $this->actingAsBackoffice();

        $classe = Classe::create(['nom' => '2nde A', 'code' => '2A', 'niveau' => 'Seconde']);

        $this->getJson("/api/classes/{$classe->id}/maquette")
            ->assertOk()
            ->assertJsonPath('obstacle', 'la classe n\'est rattachée à aucune spécialité');

        $this->postJson("/api/classes/{$classe->id}/maquette/initialiser")->assertStatus(422);
    }

    /**
     * Le niveau de la classe désigne les modules du référentiel : écrit
     * autrement, il ne désigne rien. Le message doit le dire, faute de quoi
     * on cherche l'erreur dans le référentiel.
     */
    public function test_un_niveau_qui_ne_correspond_a_aucun_module_est_explique(): void
    {
        $this->actingAsBackoffice();
        $this->referentiel();

        $this->classe->update(['niveau' => '2nde']);

        $reponse = $this->postJson("/api/classes/{$this->classe->id}/maquette/initialiser")->assertStatus(422);

        $this->assertStringContainsString('TPV', $reponse->json('errors.classe.0'));
        $this->assertStringContainsString('2nde', $reponse->json('errors.classe.0'));
        $this->assertSame(0, ClasseModule::count());
    }

    /* ------------------------------------------------------------------ */
    /* Édition de la maquette                                              */
    /* ------------------------------------------------------------------ */

    public function test_la_maquette_senregistre_en_un_envoi(): void
    {
        $this->actingAsBackoffice();
        $this->referentiel();
        $this->postJson("/api/classes/{$this->classe->id}/maquette/initialiser")->assertOk();

        $enseignant = Enseignant::factory()->create(['nom' => 'A. ESSOMBA']);
        $c10 = Module::where('code', 'C10')->firstOrFail();
        $c12 = Module::where('code', 'C12')->firstOrFail();

        $this->putJson("/api/classes/{$this->classe->id}/maquette", [
            'lignes' => [
                ['module_id' => $c10->id, 'credit' => 10, 'volume_horaire' => 150, 'enseignant_id' => $enseignant->id, 'trimestres' => [1, 2, 3]],
                ['module_id' => $c12->id, 'credit' => 10, 'volume_horaire' => 135, 'trimestres' => [2, 3]],
            ],
        ])->assertOk();

        $lignes = collect($this->getJson("/api/classes/{$this->classe->id}/maquette")->json('lignes'))->keyBy('code');

        $this->assertSame('A. ESSOMBA', $lignes['C10']['enseignant']);
        $this->assertSame([2, 3], $lignes['C12']['trimestres']);
        // Les lignes non citées ne bougent pas : une maquette ne se remplace
        // pas par ce que l'écran avait en mémoire.
        $this->assertSame([1, 2, 3], $lignes['C13']['trimestres']);
    }

    /**
     * Un module d'une autre spécialité dans la maquette produirait un bulletin
     * dont personne ne saurait d'où il sort.
     */
    public function test_un_module_hors_referentiel_de_la_classe_est_refuse(): void
    {
        $this->actingAsBackoffice();
        $this->referentiel();

        $autre = Specialite::create(['code' => 'F2', 'nom' => 'F2']);
        $intrus = Module::create([
            'specialite_id' => $autre->id,
            'niveau' => 'Seconde',
            'code' => 'C9',
            'intitule' => 'Systèmes embarqués',
            'bloc' => Module::BLOC_COMPETENCES_PARTICULIERES,
        ]);

        $this->putJson("/api/classes/{$this->classe->id}/maquette", [
            'lignes' => [['module_id' => $intrus->id, 'credit' => 10]],
        ])->assertStatus(422)->assertJsonValidationErrors('lignes');

        $this->assertSame(0, ClasseModule::count());
    }

    public function test_un_trimestre_hors_bareme_est_refuse(): void
    {
        $this->actingAsBackoffice();
        $this->referentiel();
        $module = Module::where('code', 'C10')->firstOrFail();

        $this->putJson("/api/classes/{$this->classe->id}/maquette", [
            'lignes' => [['module_id' => $module->id, 'trimestres' => [1, 4]]],
        ])->assertStatus(422)->assertJsonValidationErrors('lignes.0.trimestres.1');
    }

    /* ------------------------------------------------------------------ */
    /* Programmation et totaux                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Le stage de deuxième année du bulletin de référence : présent sur la
     * maquette, programmé nulle part, crédit entre parenthèses. Il ne doit
     * peser ni sur les crédits, ni sur les heures.
     */
    public function test_un_module_non_programme_reste_affiche_mais_sort_des_totaux(): void
    {
        $this->actingAsBackoffice();
        $this->referentiel();
        $this->postJson("/api/classes/{$this->classe->id}/maquette/initialiser")->assertOk();

        $stage = Module::where('code', 'C13')->firstOrFail();

        $reponse = $this->putJson("/api/classes/{$this->classe->id}/maquette", [
            'lignes' => [[
                'module_id' => $stage->id,
                'credit' => 20,
                'volume_horaire' => 300,
                'trimestres' => [],
            ]],
        ])->assertOk();

        $reponse->assertJsonPath('totaux.modules', 5);
        $reponse->assertJsonPath('totaux.modules_programmes', 4);
        $reponse->assertJsonPath('totaux.credits', 31);
        $reponse->assertJsonPath('totaux.volume_horaire', 465);

        $stageAffiche = collect($reponse->json('lignes'))->firstWhere('code', 'C13');
        $this->assertSame([], $stageAffiche['trimestres']);
        $this->assertSame(20, $stageAffiche['credit']);
    }

    /**
     * Une maquette dont personne n'a détaillé la programmation doit produire
     * un bulletin complet, pas un bulletin vide : `null` vaut « tous les
     * trimestres », jamais « aucun ».
     */
    public function test_une_programmation_jamais_renseignee_vaut_tous_les_trimestres(): void
    {
        $module = $this->module('C3', Module::BLOC_COMPETENCES_GENERALES, 9, 135);

        $ligne = ClasseModule::create([
            'classe_id' => $this->classe->id,
            'module_id' => $module->id,
            'credit' => 9,
            'trimestres' => null,
        ]);

        $this->assertSame([1, 2, 3], $ligne->trimestresProgrammes());
        $this->assertTrue($ligne->estProgrammeAu(2));
        $this->assertTrue($ligne->compteDansLesTotaux());
    }

    public function test_les_credits_manquants_sont_comptes(): void
    {
        $this->actingAsBackoffice();
        $this->module('C3', Module::BLOC_COMPETENCES_GENERALES, null, 135);
        $this->module('C4', Module::BLOC_COMPETENCES_GENERALES, 9, 150);

        $this->postJson("/api/classes/{$this->classe->id}/maquette/initialiser")
            ->assertOk()
            ->assertJsonPath('totaux.credits_manquants', 1);
    }

    /* ------------------------------------------------------------------ */
    /* Retrait, rattachement, périmètre                                    */
    /* ------------------------------------------------------------------ */

    public function test_un_module_se_retire_de_la_maquette(): void
    {
        $this->actingAsBackoffice();
        $this->referentiel();
        $this->postJson("/api/classes/{$this->classe->id}/maquette/initialiser")->assertOk();

        $module = Module::where('code', 'C13')->firstOrFail();

        $this->deleteJson("/api/classes/{$this->classe->id}/maquette/{$module->id}")->assertNoContent();

        $this->getJson("/api/classes/{$this->classe->id}/maquette")
            ->assertOk()
            ->assertJsonPath('totaux.modules', 4);
    }

    /**
     * La colonne texte `classes.specialite` reste lue par des écrans et des
     * exports antérieurs au référentiel : elle doit suivre le rattachement,
     * sinon une même classe a deux spécialités selon l'endroit où on la
     * regarde.
     */
    public function test_rattacher_une_specialite_met_a_jour_la_colonne_texte(): void
    {
        $this->actingAsBackoffice();

        $classe = Classe::create(['nom' => '2nde B', 'code' => '2B', 'niveau' => 'Seconde', 'specialite' => 'à préciser']);

        $this->putJson("/api/classes/{$classe->id}", [
            'nom' => '2nde B',
            'code' => '2B',
            'specialite_id' => $this->specialite->id,
        ])->assertOk()->assertJsonPath('specialite', 'TPV');

        $this->assertSame('TPV', $classe->fresh()->specialite);
    }

    /** Supprimer une classe emporte sa maquette, et rien d'autre. */
    public function test_supprimer_une_classe_emporte_sa_maquette_sans_toucher_au_referentiel(): void
    {
        $this->actingAsBackoffice();
        $this->referentiel();
        $this->postJson("/api/classes/{$this->classe->id}/maquette/initialiser")->assertOk();

        $this->deleteJson("/api/classes/{$this->classe->id}")->assertNoContent();

        $this->assertSame(0, ClasseModule::count());
        $this->assertSame(5, Module::count());
    }

    public function test_un_enseignant_lit_la_maquette_mais_ne_la_modifie_pas(): void
    {
        $this->referentiel();
        ClasseModule::create([
            'classe_id' => $this->classe->id,
            'module_id' => Module::where('code', 'C10')->value('id'),
            'credit' => 10,
        ]);

        $enseignant = Enseignant::factory()->create();
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $this->getJson("/api/classes/{$this->classe->id}/maquette")
            ->assertOk()
            ->assertJsonPath('totaux.modules', 1);

        $this->postJson("/api/classes/{$this->classe->id}/maquette/initialiser")->assertForbidden();
        $this->putJson("/api/classes/{$this->classe->id}/maquette", ['lignes' => []])->assertForbidden();

        $this->assertSame(1, ClasseModule::count());
    }
}
