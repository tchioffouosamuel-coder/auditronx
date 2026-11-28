<?php

namespace Tests\Feature;

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
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Saisie des notes (§bulletins APC).
 *
 * Les chiffres des tests sont ceux du bulletin de référence — le troisième
 * trimestre de NGONO Aline Carine, 1re année TPV : C10 52+35=87, C12 54+36=90,
 * C5 47+31=78. Un calcul qui change doit se voir ici avant de se voir sur un
 * bulletin imprimé.
 */
class SaisieNotesTest extends TestCase
{
    use RefreshDatabase;

    private Classe $classe;

    private Specialite $specialite;

    /** @var array<string, ClasseModule> */
    private array $maquette = [];

    /** @var array<string, Inscription> */
    private array $inscriptions = [];

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

    private function module(string $code, int $credit, array $trimestres = [1, 2, 3], int $ordre = 0): ClasseModule
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
            'volume_horaire' => 150,
            'trimestres' => $trimestres,
            'ordre' => $ordre,
        ]);
    }

    private function eleve(string $matricule, string $nom): Inscription
    {
        $eleve = Eleve::create(['matricule' => $matricule, 'nom' => $nom]);

        return $this->inscriptions[$matricule] = Inscription::create([
            'eleve_id' => $eleve->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire' => AnneeScolaire::courante(),
        ]);
    }

    /** @param array<int, array<string, mixed>> $notes */
    private function enregistre(ClasseModule $ligne, array $notes, int $trimestre = 3)
    {
        return $this->putJson('/api/notes/bulk', [
            'classe_id' => $this->classe->id,
            'trimestre' => $trimestre,
            'classe_module_id' => $ligne->id,
            'notes' => $notes,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* La grille                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Les élèves sans note apparaissent avec des cases vides : c'est une
     * grille à remplir, pas un relevé de ce qui existe.
     */
    public function test_la_grille_liste_tous_les_eleves_notes_ou_non(): void
    {
        $this->actingAsBackoffice();
        $ligne = $this->module('C10', 10);
        $this->eleve('TPV25-0142', 'NGONO');
        $this->eleve('TPV25-0157', 'TCHAMBA');

        $grille = $this->getJson("/api/notes?classe_id={$this->classe->id}&trimestre=3&classe_module_id={$ligne->id}")
            ->assertOk()->json();

        $this->assertCount(2, $grille['eleves']);
        $this->assertSame('NGONO', $grille['eleves'][0]['nom_complet']);
        $this->assertNull($grille['eleves'][0]['moyenne_uea']);
        // Le barème voyage avec la grille, bornes de mention comprises :
        // l'écran recalcule la mention pendant la frappe. On vérifie les
        // valeurs utiles, pas la forme exacte du payload, qui s'enrichira.
        // assertEquals et non assertSame : json_encode rend 70.0 en « 70 ».
        $this->assertEquals(60, $grille['bareme']['uea']);
        $this->assertEquals(40, $grille['bareme']['integratrice']);
        $this->assertEquals(70.0, $grille['bareme']['seuil_reussite']);
        $this->assertEquals(BaremeApc::MENTIONS, $grille['bareme']['mentions']);
        $this->assertSame(BaremeApc::APPRECIATION_NON_ACQUISE, $grille['bareme']['appreciation_non_acquise']);
    }

    /** Le calcul du bulletin de référence : 52 + 35 = 87, mention B, sanction S. */
    public function test_la_note_finale_est_calculee_et_stockee_a_lenregistrement(): void
    {
        $this->actingAsBackoffice();
        $ligne = $this->module('C10', 10);
        $inscription = $this->eleve('TPV25-0142', 'NGONO');

        $reponse = $this->enregistre($ligne, [
            ['inscription_id' => $inscription->id, 'moyenne_uea' => 52, 'note_integratrice' => 35],
        ])->assertOk();

        $this->assertEquals(87.0, $reponse->json('eleves.0.note_finale'));
        $this->assertSame('B', $reponse->json('eleves.0.mention'));
        $this->assertSame(BaremeApc::SANCTION_VALIDEE, $reponse->json('eleves.0.sanction'));
        $this->assertSame('Très bien', $reponse->json('eleves.0.appreciation'));

        // Stockée, pas recalculée : un bulletin imprimé reste reproductible.
        $this->assertSame(87.0, NoteModule::firstOrFail()->note_finale);
    }

    /**
     * Une épreuve intégratrice manquante n'est pas un zéro : c'est une
     * évaluation qui n'a pas encore eu lieu.
     */
    public function test_une_saisie_partielle_ne_produit_pas_de_note_finale(): void
    {
        $this->actingAsBackoffice();
        $ligne = $this->module('C10', 10);
        $inscription = $this->eleve('TPV25-0142', 'NGONO');

        $reponse = $this->enregistre($ligne, [
            ['inscription_id' => $inscription->id, 'moyenne_uea' => 52],
        ])->assertOk();

        $this->assertEquals(52.0, $reponse->json('eleves.0.moyenne_uea'));
        $this->assertNull($reponse->json('eleves.0.note_finale'));
        $this->assertNull($reponse->json('eleves.0.mention'));
        $this->assertFalse(NoteModule::firstOrFail()->estComplete());
    }

    /**
     * La note finale est recalculée à chaque enregistrement, et nulle part
     * ailleurs : une note finale en désaccord avec ses deux composantes, ce
     * serait un bulletin faux que rien ne signale.
     */
    public function test_la_note_finale_suit_une_correction(): void
    {
        $this->actingAsBackoffice();
        $ligne = $this->module('C10', 10);
        $inscription = $this->eleve('TPV25-0142', 'NGONO');

        $this->enregistre($ligne, [['inscription_id' => $inscription->id, 'moyenne_uea' => 52, 'note_integratrice' => 35]]);
        $this->enregistre($ligne, [['inscription_id' => $inscription->id, 'moyenne_uea' => 40, 'note_integratrice' => 24]]);

        $note = NoteModule::firstOrFail();
        $this->assertSame(64.0, $note->note_finale);
        $this->assertSame(BaremeApc::SANCTION_NON_ACQUISE, BaremeApc::sanction($note->note_finale));
        $this->assertSame(1, NoteModule::count(), 'une correction ne crée pas une seconde ligne');
    }

    /**
     * Une saisie effacée est supprimée, pas laissée à null : sinon elle
     * resterait indistinguable d'une case jamais remplie dans l'avancement.
     */
    public function test_une_saisie_effacee_est_supprimee(): void
    {
        $this->actingAsBackoffice();
        $ligne = $this->module('C10', 10);
        $inscription = $this->eleve('TPV25-0142', 'NGONO');

        $this->enregistre($ligne, [['inscription_id' => $inscription->id, 'moyenne_uea' => 52, 'note_integratrice' => 35]]);
        $this->assertSame(1, NoteModule::count());

        $reponse = $this->enregistre($ligne, [
            ['inscription_id' => $inscription->id, 'moyenne_uea' => null, 'note_integratrice' => null],
        ])->assertOk();

        $this->assertSame(1, $reponse->json('bilan.effacees'));
        $this->assertSame(0, NoteModule::count());
    }

    public function test_une_note_hors_bareme_est_refusee(): void
    {
        $this->actingAsBackoffice();
        $ligne = $this->module('C10', 10);
        $inscription = $this->eleve('TPV25-0142', 'NGONO');

        $this->enregistre($ligne, [['inscription_id' => $inscription->id, 'moyenne_uea' => 61]])
            ->assertStatus(422)->assertJsonValidationErrors('notes.0.moyenne_uea');

        $this->enregistre($ligne, [['inscription_id' => $inscription->id, 'note_integratrice' => 41]])
            ->assertStatus(422)->assertJsonValidationErrors('notes.0.note_integratrice');
    }

    /* ------------------------------------------------------------------ */
    /* Ce que la grille refuse                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Une note sur un module non programmé n'entrerait dans aucune moyenne
     * annuelle, et personne ne verrait qu'elle a été perdue.
     */
    public function test_un_module_non_programme_au_trimestre_nest_pas_saisissable(): void
    {
        $this->actingAsBackoffice();
        $ligne = $this->module('C8', 10, trimestres: [1, 2]);
        $inscription = $this->eleve('TPV25-0142', 'NGONO');

        $reponse = $this->enregistre($ligne, [
            ['inscription_id' => $inscription->id, 'moyenne_uea' => 52, 'note_integratrice' => 35],
        ])->assertStatus(422);

        $this->assertStringContainsString('C8', $reponse->json('errors.classe_module_id.0'));
        $this->assertStringContainsString('trimestre 3', $reponse->json('errors.classe_module_id.0'));
        $this->assertSame(0, NoteModule::count());

        // Mais il l'est aux trimestres où il est programmé.
        $this->enregistre($ligne, [
            ['inscription_id' => $inscription->id, 'moyenne_uea' => 52, 'note_integratrice' => 35],
        ], trimestre: 2)->assertOk();
    }

    public function test_un_module_dune_autre_classe_est_refuse(): void
    {
        $this->actingAsBackoffice();
        $this->eleve('TPV25-0142', 'NGONO');

        $autreClasse = Classe::create(['nom' => 'Autre', 'code' => 'AUT', 'niveau' => 'Seconde']);
        $module = Module::create([
            'specialite_id' => $this->specialite->id,
            'niveau' => 'Seconde',
            'code' => 'C99',
            'intitule' => 'Ailleurs',
            'bloc' => Module::BLOC_COMPETENCES_GENERALES,
        ]);
        $intrus = ClasseModule::create(['classe_id' => $autreClasse->id, 'module_id' => $module->id]);

        $this->getJson("/api/notes?classe_id={$this->classe->id}&trimestre=3&classe_module_id={$intrus->id}")
            ->assertStatus(422)->assertJsonValidationErrors('classe_module_id');
    }

    public function test_un_eleve_hors_de_la_classe_est_refuse(): void
    {
        $this->actingAsBackoffice();
        $ligne = $this->module('C10', 10);
        $this->eleve('TPV25-0142', 'NGONO');

        $autre = Classe::create(['nom' => 'Autre', 'code' => 'AUT', 'niveau' => 'Seconde']);
        $eleve = Eleve::create(['matricule' => 'X1', 'nom' => 'AILLEURS']);
        $inscription = Inscription::create([
            'eleve_id' => $eleve->id,
            'classe_id' => $autre->id,
            'annee_scolaire' => AnneeScolaire::courante(),
        ]);

        $this->enregistre($ligne, [
            ['inscription_id' => $inscription->id, 'moyenne_uea' => 52, 'note_integratrice' => 35],
        ])->assertStatus(422)->assertJsonValidationErrors('notes');

        $this->assertSame(0, NoteModule::count());
    }

    /** Une note qui a servi à un bulletin validé ne se modifie plus en silence. */
    public function test_une_note_verrouillee_ne_se_modifie_pas(): void
    {
        $this->actingAsBackoffice();
        $ligne = $this->module('C10', 10);
        $inscription = $this->eleve('TPV25-0142', 'NGONO');

        $this->enregistre($ligne, [['inscription_id' => $inscription->id, 'moyenne_uea' => 52, 'note_integratrice' => 35]]);
        NoteModule::firstOrFail()->update(['verrouillee_at' => now()]);

        $reponse = $this->enregistre($ligne, [
            ['inscription_id' => $inscription->id, 'moyenne_uea' => 60, 'note_integratrice' => 40],
        ])->assertStatus(422);

        $this->assertStringContainsString('verrouillées', $reponse->json('errors.notes.0'));
        $this->assertSame(87.0, NoteModule::firstOrFail()->note_finale);
    }

    /* ------------------------------------------------------------------ */
    /* L'avancement                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Sans ce décompte, personne ne découvre qu'il manque une colonne avant
     * le conseil de classe, c'est-à-dire trop tard.
     */
    public function test_lavancement_dit_ce_quil_reste_a_saisir(): void
    {
        $this->actingAsBackoffice();
        $c10 = $this->module('C10', 10, ordre: 1);
        $c12 = $this->module('C12', 10, ordre: 2);
        $this->module('C8', 10, trimestres: [1, 2], ordre: 3);

        $a = $this->eleve('M1', 'ABEGA');
        $b = $this->eleve('M2', 'NGONO');

        $this->enregistre($c10, [
            ['inscription_id' => $a->id, 'moyenne_uea' => 52, 'note_integratrice' => 35],
            ['inscription_id' => $b->id, 'moyenne_uea' => 50, 'note_integratrice' => 34],
        ])->assertOk();
        // Saisie partielle : elle ne compte pas comme faite.
        $this->enregistre($c12, [['inscription_id' => $a->id, 'moyenne_uea' => 54]])->assertOk();

        $avancement = $this->getJson("/api/notes/avancement?classe_id={$this->classe->id}&trimestre=3")
            ->assertOk()->json();

        $this->assertSame(2, $avancement['effectif']);
        $this->assertCount(2, $avancement['modules'], 'C8 n\'est pas programmé au trimestre 3');

        $parCode = collect($avancement['modules'])->keyBy('code');
        $this->assertSame(2, $parCode['C10']['saisies']);
        $this->assertTrue($parCode['C10']['complet']);
        $this->assertSame(0, $parCode['C12']['saisies']);
        $this->assertFalse($parCode['C12']['complet']);

        $this->assertSame(1, $avancement['modules_complets']);
        $this->assertFalse($avancement['prete']);
    }

    public function test_lavancement_est_le_defaut_de_la_route_des_notes(): void
    {
        $this->actingAsBackoffice();
        $this->module('C10', 10);
        $this->eleve('M1', 'ABEGA');

        $this->getJson("/api/notes?classe_id={$this->classe->id}&trimestre=3")
            ->assertOk()
            ->assertJsonPath('effectif', 1)
            ->assertJsonCount(1, 'modules');
    }

    public function test_une_classe_entierement_saisie_est_declaree_prete(): void
    {
        $this->actingAsBackoffice();
        $ligne = $this->module('C10', 10);
        $inscription = $this->eleve('M1', 'ABEGA');

        $this->enregistre($ligne, [['inscription_id' => $inscription->id, 'moyenne_uea' => 52, 'note_integratrice' => 35]]);

        $this->getJson("/api/notes/avancement?classe_id={$this->classe->id}&trimestre=3")
            ->assertOk()
            ->assertJsonPath('prete', true);
    }

    /** Un élève parti en cours d'année sort des grilles à remplir. */
    public function test_un_eleve_inactif_sort_de_la_grille_et_de_leffectif_attendu(): void
    {
        $this->actingAsBackoffice();
        $ligne = $this->module('C10', 10);
        $actif = $this->eleve('M1', 'ABEGA');
        $parti = $this->eleve('M2', 'PARTI');
        $parti->update(['actif' => false]);

        $this->enregistre($ligne, [['inscription_id' => $actif->id, 'moyenne_uea' => 52, 'note_integratrice' => 35]]);

        $grille = $this->getJson("/api/notes?classe_id={$this->classe->id}&trimestre=3&classe_module_id={$ligne->id}")
            ->assertOk()->json();

        $this->assertCount(1, $grille['eleves']);
        $this->getJson("/api/notes/avancement?classe_id={$this->classe->id}&trimestre=3")
            ->assertJsonPath('effectif', 1)
            ->assertJsonPath('prete', true);
    }

    /* ------------------------------------------------------------------ */
    /* Le classeur hors ligne                                              */
    /* ------------------------------------------------------------------ */

    public function test_le_classeur_exporte_un_onglet_par_module_programme(): void
    {
        $this->actingAsBackoffice();
        $this->module('C10', 10, ordre: 1);
        $this->module('C12', 10, ordre: 2);
        $this->module('C8', 10, trimestres: [1, 2], ordre: 3);
        $this->eleve('TPV25-0142', 'NGONO');

        $binaire = $this->get("/api/notes/classeur/export?classe_id={$this->classe->id}&trimestre=3")
            ->assertOk()->streamedContent();

        $fichier = UploadedFile::fake()->createWithContent('notes.xlsx', $binaire);
        $onglets = \App\Support\Classeur::nomsOnglets($fichier->getRealPath());

        $this->assertSame(['C10', 'C12'], $onglets);

        $feuilles = Excel::toArray(new \App\Imports\FeuillesBrutesImport, $fichier);
        $this->assertSame(['matricule', 'nom', 'uea_60', 'integratrice_40'], $feuilles[0][0]);
        $this->assertSame('TPV25-0142', $feuilles[0][1][0]);
        $this->assertSame('NGONO', $feuilles[0][1][1]);
    }

    /** @param array<string, array<int, array<int, mixed>>> $parOnglet */
    private function importeClasseur(array $parOnglet, array $extra = []): array
    {
        $feuilles = [];
        foreach ($parOnglet as $code => $lignes) {
            $feuilles[] = new \App\Exports\NotesModuleSheetExport(
                $code,
                $lignes,
                ['matricule', 'nom', 'uea_60', 'integratrice_40'],
            );
        }

        $classeur = new class($feuilles) implements \Maatwebsite\Excel\Concerns\Export, \Maatwebsite\Excel\Concerns\WithMultipleSheets
        {
            public function __construct(private readonly array $feuilles) {}

            public function sheets(): array
            {
                return $this->feuilles;
            }
        };

        return $this->post('/api/notes/classeur/import', [
            'classe_id' => $this->classe->id,
            'trimestre' => 3,
            'file' => UploadedFile::fake()->createWithContent(
                'notes.xlsx',
                Excel::raw($classeur, \Maatwebsite\Excel\Excel::XLSX)
            ),
            ...$extra,
        ])->assertOk()->json();
    }

    /**
     * Les onglets sont rapprochés par code de module, les lignes par
     * matricule : ni l'ordre des onglets ni celui des lignes ne comptent.
     */
    public function test_le_classeur_se_reimporte_quel_que_soit_lordre(): void
    {
        $this->actingAsBackoffice();
        $c10 = $this->module('C10', 10, ordre: 1);
        $c12 = $this->module('C12', 10, ordre: 2);
        $a = $this->eleve('TPV25-0142', 'NGONO');
        $b = $this->eleve('TPV25-0157', 'TCHAMBA');

        $resultat = $this->importeClasseur([
            // Onglets et lignes dans le désordre.
            'C12' => [
                ['TPV25-0157', 'TCHAMBA', 43, 28],
                ['TPV25-0142', 'NGONO', 54, 36],
            ],
            'C10' => [
                ['TPV25-0142', 'NGONO', 52, 35],
                ['TPV25-0157', 'TCHAMBA', 40, 24],
            ],
        ]);

        $this->assertSame(4, $resultat['lues']);
        $this->assertSame([], $resultat['erreurs']);

        $note = fn (ClasseModule $l, Inscription $i) => NoteModule::where('classe_module_id', $l->id)
            ->where('inscription_id', $i->id)->where('trimestre', 3)->value('note_finale');

        $this->assertEquals(87.0, $note($c10, $a));
        $this->assertEquals(90.0, $note($c12, $a));
        $this->assertEquals(64.0, $note($c10, $b));
        $this->assertEquals(71.0, $note($c12, $b));
    }

    /** Un classeur francophone écrit « 52,5 ». */
    public function test_la_virgule_decimale_est_acceptee(): void
    {
        $this->actingAsBackoffice();
        $c10 = $this->module('C10', 10);
        $a = $this->eleve('TPV25-0142', 'NGONO');

        $this->importeClasseur(['C10' => [['TPV25-0142', 'NGONO', '52,5', '35']]]);

        $this->assertSame(87.5, NoteModule::firstOrFail()->note_finale);
    }

    public function test_limport_signale_onglet_inconnu_eleve_inconnu_et_note_fausse(): void
    {
        $this->actingAsBackoffice();
        $this->module('C10', 10);
        $this->eleve('TPV25-0142', 'NGONO');

        $resultat = $this->importeClasseur([
            'C99' => [['TPV25-0142', 'NGONO', 50, 30]],
            'C10' => [
                ['INCONNU', 'FANTOME', 50, 30],
                ['TPV25-0142', 'NGONO', 99, 30],
            ],
        ]);

        $this->assertSame(0, $resultat['lues']);
        $this->assertCount(3, $resultat['erreurs']);

        $messages = array_column($resultat['erreurs'], 'erreur');
        $this->assertStringContainsString('C99', $messages[0]);
        $this->assertStringContainsString('INCONNU', $messages[1]);
        $this->assertStringContainsString('hors barème', $messages[2]);
        $this->assertSame(0, NoteModule::count());
    }

    /** Télécharger puis réimporter sans rien changer ne modifie rien. */
    public function test_un_aller_retour_sans_modification_ne_change_rien(): void
    {
        $this->actingAsBackoffice();
        $ligne = $this->module('C10', 10);
        $inscription = $this->eleve('TPV25-0142', 'NGONO');

        $this->enregistre($ligne, [['inscription_id' => $inscription->id, 'moyenne_uea' => 52, 'note_integratrice' => 35]]);
        $avant = NoteModule::firstOrFail()->only(['moyenne_uea', 'note_integratrice', 'note_finale']);

        $binaire = $this->get("/api/notes/classeur/export?classe_id={$this->classe->id}&trimestre=3")
            ->assertOk()->streamedContent();

        $this->post('/api/notes/classeur/import', [
            'classe_id' => $this->classe->id,
            'trimestre' => 3,
            'file' => UploadedFile::fake()->createWithContent('notes.xlsx', $binaire),
        ])->assertOk();

        $this->assertSame(1, NoteModule::count());
        $this->assertEquals($avant, NoteModule::firstOrFail()->only(['moyenne_uea', 'note_integratrice', 'note_finale']));
    }

    public function test_lapercu_du_classeur_necrit_rien(): void
    {
        $this->actingAsBackoffice();
        $this->module('C10', 10);
        $this->eleve('TPV25-0142', 'NGONO');

        $resultat = $this->importeClasseur(
            ['C10' => [['TPV25-0142', 'NGONO', 52, 35]]],
            ['apercu' => 1],
        );

        $this->assertFalse($resultat['applique']);
        $this->assertSame(1, $resultat['lues']);
        $this->assertSame(0, NoteModule::count());
    }

    /* ------------------------------------------------------------------ */
    /* Périmètre                                                           */
    /* ------------------------------------------------------------------ */

    public function test_un_enseignant_lit_les_notes_mais_ne_les_saisit_pas(): void
    {
        $ligne = $this->module('C10', 10);
        $inscription = $this->eleve('TPV25-0142', 'NGONO');
        NoteModule::create([
            'inscription_id' => $inscription->id,
            'classe_module_id' => $ligne->id,
            'trimestre' => 3,
            'moyenne_uea' => 52,
            'note_integratrice' => 35,
        ]);

        $enseignant = Enseignant::factory()->create();
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $this->getJson("/api/notes?classe_id={$this->classe->id}&trimestre=3&classe_module_id={$ligne->id}")
            ->assertOk()
            ->assertJsonPath('eleves.0.note_finale', 87);
        $this->getJson("/api/notes/avancement?classe_id={$this->classe->id}&trimestre=3")->assertOk();

        $this->putJson('/api/notes/bulk', [
            'classe_id' => $this->classe->id,
            'trimestre' => 3,
            'classe_module_id' => $ligne->id,
            'notes' => [['inscription_id' => $inscription->id, 'moyenne_uea' => 10, 'note_integratrice' => 10]],
        ])->assertForbidden();

        $this->assertSame(87.0, NoteModule::firstOrFail()->note_finale);
    }

    /** La saisie est tracée : qui a posé la note. */
    public function test_la_saisie_porte_son_auteur(): void
    {
        $user = $this->actingAsBackoffice();
        $ligne = $this->module('C10', 10);
        $inscription = $this->eleve('TPV25-0142', 'NGONO');

        $this->enregistre($ligne, [['inscription_id' => $inscription->id, 'moyenne_uea' => 52, 'note_integratrice' => 35]]);

        $this->assertSame($user->id, NoteModule::firstOrFail()->saisie_par);
    }
}
