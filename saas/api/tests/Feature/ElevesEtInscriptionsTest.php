<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Inscription;
use App\Models\User;
use App\Services\AnneeScolaire;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Élèves et inscriptions (§bulletins APC).
 *
 * Le point du lot : **l'inscription, et non l'élève, est l'unité du
 * chantier**. Les notes et les bulletins s'y rattacheront, ce qui permet à un
 * redoublant d'avoir deux années côte à côte sans que la seconde écrase la
 * première. Les tests ci-dessous épinglent cette séparation et ses
 * conséquences — l'effectif compté sur les inscriptions actives, l'unicité
 * par année, le statut imprimé en en-tête du bulletin.
 */
class ElevesEtInscriptionsTest extends TestCase
{
    use RefreshDatabase;

    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();

        // L'année scolaire bascule au 1er septembre : sans date figée, ces
        // tests changeraient de résultat chaque rentrée.
        Carbon::setTestNow(Carbon::parse('2025-11-28 09:00:00'));

        $this->classe = Classe::create([
            'nom' => '1re année TPV',
            'code' => '1TPV',
            'niveau' => 'Seconde',
            'effectif' => 28,
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

    private function eleve(string $matricule, string $nom, string $prenoms = ''): Eleve
    {
        return Eleve::create([
            'matricule' => $matricule,
            'nom' => $nom,
            'prenoms' => $prenoms,
            'sexe' => Eleve::SEXE_FEMININ,
            'date_naissance' => '2008-03-14',
            'lieu_naissance' => 'Ebolowa',
        ]);
    }

    private function inscrit(Eleve $eleve, ?string $annee = null, array $extra = []): Inscription
    {
        return Inscription::create([
            'eleve_id' => $eleve->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire' => $annee ?? AnneeScolaire::courante(),
            ...$extra,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* L'année scolaire                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * La règle vivait dans une méthode privée de CoursEnseignantController ;
     * trois consommateurs plus tard, elle est partagée. Le format à la barre
     * oblique est celui de `programmes.annee_scolaire`, déjà en circulation.
     */
    public function test_lannee_scolaire_bascule_a_la_rentree(): void
    {
        $this->assertSame('2025/2026', AnneeScolaire::pour(Carbon::parse('2025-09-01')));
        $this->assertSame('2024/2025', AnneeScolaire::pour(Carbon::parse('2025-08-31')));
        $this->assertSame('2025/2026', AnneeScolaire::pour(Carbon::parse('2026-06-30')));
        $this->assertSame('2025/2026', AnneeScolaire::courante());
    }

    /** Un classeur écrit l'année de trois façons ; elles désignent la même. */
    public function test_lannee_scolaire_se_normalise(): void
    {
        $this->assertSame('2025/2026', AnneeScolaire::normalise('2025-2026'));
        $this->assertSame('2025/2026', AnneeScolaire::normalise('2025 2026'));
        $this->assertSame('2025/2026', AnneeScolaire::normalise('2025/26'));
        $this->assertSame('2025/2026', AnneeScolaire::normalise('  2025 / 2026 '));
        $this->assertNull(AnneeScolaire::normalise(''));

        // Illisible : rendue telle quelle plutôt qu'inventée — mieux vaut une
        // année bizarre mais visible.
        $this->assertSame('année X', AnneeScolaire::normalise('année X'));
    }

    /* ------------------------------------------------------------------ */
    /* Inscriptions                                                        */
    /* ------------------------------------------------------------------ */

    public function test_un_eleve_na_quune_inscription_par_annee(): void
    {
        $this->actingAsBackoffice();
        $eleve = $this->eleve('TPV25-0142', 'NGONO', 'Aline Carine');

        $this->postJson('/api/inscriptions', [
            'eleve_id' => $eleve->id,
            'classe_id' => $this->classe->id,
        ])->assertCreated()->assertJsonPath('annee_scolaire', '2025/2026');

        $this->postJson('/api/inscriptions', [
            'eleve_id' => $eleve->id,
            'classe_id' => $this->classe->id,
        ])->assertStatus(422)->assertJsonValidationErrors('eleve_id');

        $this->assertSame(1, Inscription::count());
    }

    /**
     * Le cœur du modèle : un redoublant garde son année précédente intacte,
     * avec ses propres notes et ses propres bulletins à venir.
     */
    public function test_un_redoublant_a_deux_inscriptions_distinctes(): void
    {
        $this->actingAsBackoffice();
        $eleve = $this->eleve('TPV24-0101', 'TCHAMBA', 'Kevin Arnaud');

        $this->inscrit($eleve, '2024/2025');
        $this->postJson('/api/inscriptions', [
            'eleve_id' => $eleve->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire' => '2025-2026',
            'statut' => Inscription::STATUT_REDOUBLANT,
        ])->assertCreated();

        $inscriptions = $eleve->inscriptions()->orderBy('annee_scolaire')->get();

        $this->assertCount(2, $inscriptions);
        $this->assertSame(Inscription::STATUT_REGULIER, $inscriptions[0]->statut);
        $this->assertSame(Inscription::STATUT_REDOUBLANT, $inscriptions[1]->statut);
        $this->assertSame('2025/2026', $inscriptions[1]->annee_scolaire, 'l\'année saisie au tiret est normalisée');
        $this->assertSame('Redoublant', $inscriptions[1]->libelleStatut());
    }

    /** Changer de classe en cours d'année modifie l'inscription, n'en crée pas une seconde. */
    public function test_changer_de_classe_modifie_linscription(): void
    {
        $this->actingAsBackoffice();
        $eleve = $this->eleve('TPV25-0200', 'MBALLA');
        $inscription = $this->inscrit($eleve);
        $autre = Classe::create(['nom' => '1re année F2', 'code' => '1F2', 'niveau' => 'Seconde']);

        $this->putJson("/api/inscriptions/{$inscription->id}", [
            'eleve_id' => $eleve->id,
            'classe_id' => $autre->id,
        ])->assertOk()->assertJsonPath('classe.code', '1F2');

        $this->assertSame(1, Inscription::count());
    }

    /* ------------------------------------------------------------------ */
    /* Liste de classe et effectif                                         */
    /* ------------------------------------------------------------------ */

    /**
     * L'effectif imprimé en en-tête du bulletin se compte sur les
     * inscriptions actives, **pas** sur `classes.effectif` — une valeur
     * saisie à la main, qui se périme au premier départ et fausserait aussi
     * le rang.
     */
    public function test_leffectif_se_compte_sur_les_inscriptions_actives(): void
    {
        $this->actingAsBackoffice();

        $this->inscrit($this->eleve('M1', 'ABEGA'));
        $this->inscrit($this->eleve('M2', 'NGONO'));
        $this->inscrit($this->eleve('M3', 'ZANGA'), extra: ['actif' => false]);
        $this->inscrit($this->eleve('M4', 'AUTRE'), annee: '2024/2025');

        $reponse = $this->getJson("/api/classes/{$this->classe->id}/eleves")->assertOk();

        $this->assertSame(2, $reponse->json('effectif'), 'ni le parti, ni celui de l\'an dernier');
        $this->assertSame(28, $this->classe->effectif, 'la valeur saisie à la main n\'est pas touchée');
        $this->assertSame(['ABEGA', 'NGONO'], array_column($reponse->json('eleves'), 'nom_complet'));
    }

    public function test_la_liste_de_classe_est_triee_par_nom(): void
    {
        $this->actingAsBackoffice();

        $this->inscrit($this->eleve('M1', 'ZANGA', 'Paul'));
        $this->inscrit($this->eleve('M2', 'ABEGA', 'Marie'));
        $this->inscrit($this->eleve('M3', 'ABEGA', 'Claire'));

        $noms = array_column(
            $this->getJson("/api/classes/{$this->classe->id}/eleves")->assertOk()->json('eleves'),
            'nom_complet'
        );

        $this->assertSame(['ABEGA Claire', 'ABEGA Marie', 'ZANGA Paul'], $noms);
    }

    /** Ce que l'en-tête du bulletin imprime, et rien d'autre. */
    public function test_la_liste_de_classe_porte_lidentite_du_bulletin(): void
    {
        $this->actingAsBackoffice();
        $eleve = $this->eleve('TPV25-0142', 'NGONO', 'Aline Carine');
        $inscription = $this->inscrit($eleve);

        $ligne = $this->getJson("/api/classes/{$this->classe->id}/eleves")->assertOk()->json('eleves.0');

        $this->assertSame($inscription->id, $ligne['inscription_id']);
        $this->assertSame('TPV25-0142', $ligne['matricule']);
        $this->assertSame('NGONO Aline Carine', $ligne['nom_complet']);
        $this->assertSame('F', $ligne['sexe']);
        $this->assertSame('2008-03-14', $ligne['date_naissance']);
        $this->assertSame('Ebolowa', $ligne['lieu_naissance']);
        $this->assertSame(Inscription::STATUT_REGULIER, $ligne['statut']);
    }

    public function test_une_annee_passee_se_relit(): void
    {
        $this->actingAsBackoffice();
        $this->inscrit($this->eleve('M1', 'ANCIEN'), annee: '2024/2025');

        $this->getJson("/api/classes/{$this->classe->id}/eleves?annee=2024-2025")
            ->assertOk()
            ->assertJsonPath('effectif', 1)
            ->assertJsonPath('annee_scolaire', '2024/2025');

        $this->getJson('/api/eleves/annees')->assertOk()->assertExactJson(['2024/2025']);
    }

    /* ------------------------------------------------------------------ */
    /* Fiches élèves                                                       */
    /* ------------------------------------------------------------------ */

    public function test_le_matricule_est_unique(): void
    {
        $this->actingAsBackoffice();
        $this->eleve('TPV25-0142', 'NGONO');

        $this->postJson('/api/eleves', ['matricule' => 'TPV25-0142', 'nom' => 'AUTRE'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('matricule');
    }

    public function test_le_sexe_nadmet_que_m_ou_f(): void
    {
        $this->actingAsBackoffice();

        $this->postJson('/api/eleves', ['matricule' => 'X1', 'nom' => 'ESSAI', 'sexe' => 'Autre'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sexe');
    }

    public function test_les_eleves_se_filtrent_par_classe_et_se_cherchent(): void
    {
        $this->actingAsBackoffice();
        $autre = Classe::create(['nom' => '1re année F2', 'code' => '1F2', 'niveau' => 'Seconde']);

        $this->inscrit($this->eleve('TPV25-0142', 'NGONO', 'Aline'));
        Inscription::create([
            'eleve_id' => $this->eleve('F225-0001', 'MBALLA', 'Jean')->id,
            'classe_id' => $autre->id,
            'annee_scolaire' => AnneeScolaire::courante(),
        ]);

        $this->getJson("/api/eleves?classe_id={$this->classe->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.matricule', 'TPV25-0142');

        $this->getJson('/api/eleves?q=MBALLA')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nom', 'MBALLA');
    }

    /** Supprimer un élève emporte ses inscriptions, et rien de la classe. */
    public function test_supprimer_un_eleve_emporte_ses_inscriptions(): void
    {
        $this->actingAsBackoffice();
        $eleve = $this->eleve('M1', 'ABEGA');
        $this->inscrit($eleve);
        $this->inscrit($eleve, '2024/2025');

        $this->deleteJson("/api/eleves/{$eleve->id}")->assertNoContent();

        $this->assertSame(0, Inscription::count());
        $this->assertNotNull($this->classe->fresh());
    }

    /* ------------------------------------------------------------------ */
    /* Import XLSX                                                         */
    /* ------------------------------------------------------------------ */

    /** @param array<int, array<int, mixed>> $lignes */
    private function importe(array $lignes): array
    {
        $entetes = [
            'matricule', 'nom', 'prenoms', 'sexe', 'date_naissance', 'lieu_naissance',
            'code_classe', 'annee_scolaire', 'statut',
        ];

        $binaire = Excel::raw(
            new \App\Exports\ArrayExport($entetes, $lignes),
            \Maatwebsite\Excel\Excel::XLSX
        );

        return $this->post('/api/spreadsheet/eleves/import', [
            'file' => UploadedFile::fake()->createWithContent('eleves.xlsx', $binaire),
        ])->assertOk()->json();
    }

    public function test_une_liste_de_classe_simporte_identite_et_inscription(): void
    {
        $this->actingAsBackoffice();

        $resultat = $this->importe([
            ['TPV25-0142', 'NGONO', 'Aline Carine', 'F', '14/03/2008', 'Ebolowa', '1TPV', '2025-2026', 'regulier'],
            ['TPV25-0157', 'TCHAMBA', 'Kevin Arnaud', 'M', '02/09/2007', 'Dschang', '1TPV', '2025-2026', 'redoublant'],
        ]);

        $this->assertSame(2, $resultat['importes']);
        $this->assertSame([], $resultat['erreurs']);

        $eleve = Eleve::where('matricule', 'TPV25-0142')->firstOrFail();
        $this->assertSame('NGONO Aline Carine', $eleve->nom_complet);
        $this->assertSame('2008-03-14', $eleve->date_naissance->toDateString());

        $this->assertSame(2, Inscription::activesDe($this->classe->id, '2025/2026')->count());
        $this->assertSame(
            Inscription::STATUT_REDOUBLANT,
            Inscription::whereRelation('eleve', 'matricule', 'TPV25-0157')->value('statut')
        );
    }

    /**
     * Une colonne formatée en date dans le classeur arrive ici en nombre.
     * La lire comme une chaîne donnerait une naissance en l'an 39521.
     */
    public function test_une_date_au_format_excel_est_lue_comme_une_date(): void
    {
        $this->actingAsBackoffice();

        // 39521 = 14/03/2008 dans le calendrier d'Excel.
        $this->importe([['TPV25-0142', 'NGONO', 'Aline', 'F', 39521, 'Ebolowa', '1TPV', '2025-2026', '']]);

        $this->assertSame(
            '2008-03-14',
            Eleve::where('matricule', 'TPV25-0142')->value('date_naissance')->toDateString()
        );
    }

    public function test_limport_signale_ligne_par_ligne_ce_quil_refuse(): void
    {
        $this->actingAsBackoffice();

        $resultat = $this->importe([
            ['', 'SANS MATRICULE', '', '', '', '', '1TPV', '', ''],
            ['M2', 'CLASSE INCONNUE', '', '', '', '', 'ZZZZ', '', ''],
            ['M3', 'SEXE FAUX', '', 'Autre', '', '', '1TPV', '', ''],
            ['M4', 'DATE FAUSSE', '', 'F', '32/13/2008', '', '1TPV', '', ''],
            ['M5', 'STATUT FAUX', '', 'F', '', '', '1TPV', '', 'auditeur'],
        ]);

        $this->assertSame(0, $resultat['importes']);
        $this->assertCount(5, $resultat['erreurs']);

        $messages = array_column($resultat['erreurs'], 'erreur');
        $this->assertStringContainsString('matricule et nom requis', $messages[0]);
        $this->assertStringContainsString('ZZZZ', $messages[1]);
        $this->assertStringContainsString('M ou F', $messages[2]);
        $this->assertStringContainsString('date_naissance illisible', $messages[3]);
        $this->assertStringContainsString('statut', $messages[4]);
    }

    /**
     * Les listes arrivent parfois avant les affectations : une fiche sans
     * classe enregistre l'identité et attend son inscription.
     */
    public function test_une_fiche_sans_classe_enregistre_lidentite_seule(): void
    {
        $this->actingAsBackoffice();

        $resultat = $this->importe([['M9', 'NOUVEAU', 'Pierre', 'M', '', '', '', '', '']]);

        $this->assertSame(1, $resultat['importes']);
        $this->assertSame(1, Eleve::count());
        $this->assertSame(0, Inscription::count());
    }

    public function test_un_reimport_met_a_jour_sans_dupliquer(): void
    {
        $this->actingAsBackoffice();

        $ligne = [['TPV25-0142', 'NGONO', 'Aline Carine', 'F', '14/03/2008', 'Ebolowa', '1TPV', '2025-2026', '']];
        $this->importe($ligne);
        $this->importe([['TPV25-0142', 'NGONO', 'Aline Carine Épouse X', 'F', '14/03/2008', 'Yaoundé', '1TPV', '2025-2026', '']]);

        $this->assertSame(1, Eleve::count());
        $this->assertSame(1, Inscription::count());
        $this->assertSame('Yaoundé', Eleve::first()->lieu_naissance);
    }

    public function test_le_modele_vierge_porte_les_bonnes_colonnes(): void
    {
        $this->actingAsBackoffice();

        $binaire = $this->get('/api/spreadsheet/eleves/template')->assertOk()->streamedContent();

        $lignes = Excel::toArray(new \App\Imports\FeuillesBrutesImport, UploadedFile::fake()->createWithContent('m.xlsx', $binaire));

        $this->assertSame(
            ['matricule', 'nom', 'prenoms', 'sexe', 'date_naissance', 'lieu_naissance', 'code_classe', 'annee_scolaire', 'statut'],
            $lignes[0][0]
        );
    }

    /* ------------------------------------------------------------------ */
    /* Périmètre                                                           */
    /* ------------------------------------------------------------------ */

    public function test_un_enseignant_lit_les_eleves_mais_ne_les_modifie_pas(): void
    {
        $eleve = $this->eleve('TPV25-0142', 'NGONO', 'Aline');
        $inscription = $this->inscrit($eleve);

        $enseignant = Enseignant::factory()->create();
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $this->getJson('/api/eleves')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/classes/{$this->classe->id}/eleves")->assertOk()->assertJsonPath('effectif', 1);
        $this->getJson('/api/inscriptions')->assertOk();

        $this->postJson('/api/eleves', ['matricule' => 'X', 'nom' => 'X'])->assertForbidden();
        $this->deleteJson("/api/eleves/{$eleve->id}")->assertForbidden();
        $this->deleteJson("/api/inscriptions/{$inscription->id}")->assertForbidden();

        $this->assertSame(1, Eleve::count());
        $this->assertSame(1, Inscription::count());
    }
}
