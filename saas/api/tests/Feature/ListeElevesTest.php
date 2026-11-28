<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Inscription;
use App\Models\User;
use App\Support\ListeEleves;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Modèle, export et import des élèves au format de la liste de classe de
 * l'administration : un onglet par classe, « LISTE DES ÉLÈVES — classe —
 * année » en titre, les en-têtes N°, NOMS ET PRÉNOMS, MATRICULE NATIONAL,
 * MATRICULE INTERNE, DATE DE NAISSANCE, LIEU DE NAISSANCE, TÉLÉPHONE, SEXE,
 * STATUT en ligne 3.
 */
class ListeElevesTest extends TestCase
{
    use RefreshDatabase;

    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-28 09:00:00'));

        $this->classe = Classe::create([
            'nom' => '2nde F4-BA',
            'code' => '2F4BA',
            'niveau' => 'Seconde',
            'effectif' => 45,
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

    /**
     * Une ligne : [nom, matricule national, matricule interne, date, lieu,
     * téléphone, sexe, statut].
     *
     * @param  array<int, mixed>  $valeurs
     * @return array<string, mixed>
     */
    private function ligne(array $valeurs): array
    {
        return array_combine(
            ['nom', 'matricule_national', 'matricule', 'date_naissance', 'lieu_naissance', 'telephone', 'sexe', 'statut'],
            $valeurs,
        );
    }

    /** @param array<int, array<int, mixed>> $lignes */
    private function classeur(string $classe, string $annee, array $lignes): string
    {
        $chemin = ListeEleves::ecrire([[
            'onglet' => 'Eleves',
            'classe' => $classe,
            'annee' => $annee,
            'lignes' => array_map(fn (array $l) => $this->ligne($l), $lignes),
        ]]);

        $binaire = file_get_contents($chemin);
        unlink($chemin);

        return $binaire;
    }

    private function envoie(string $binaire): \Illuminate\Testing\TestResponse
    {
        return $this->post('/api/spreadsheet/eleves/import', [
            'file' => UploadedFile::fake()->createWithContent('liste.xlsx', $binaire),
        ], ['Accept' => 'application/json']);
    }

    /** @param array<int, array<int, mixed>> $lignes */
    private function importe(array $lignes, string $classe = '2nde F4-BA', string $annee = '2026-2027'): array
    {
        return $this->envoie($this->classeur($classe, $annee, $lignes))->assertOk()->json();
    }

    /** Le fichier d'une réponse de téléchargement, qu'aucun envoi n'a encore effacé. */
    private function contenu(\Illuminate\Testing\TestResponse $reponse): string
    {
        return file_get_contents($reponse->baseResponse->getFile()->getPathname());
    }

    /** @return array<int, array<int, mixed>> chaque onglet en lignes brutes, `A1` en [0][0] */
    private function lit(string $binaire): array
    {
        $chemin = tempnam(sys_get_temp_dir(), 'lu').'.xlsx';
        file_put_contents($chemin, $binaire);
        $classeur = IOFactory::load($chemin);
        $onglets = [];
        foreach ($classeur->getAllSheets() as $ws) {
            $onglets[$ws->getTitle()] = $ws->toArray(null, false, false, false);
        }
        unlink($chemin);

        return $onglets;
    }

    public function test_la_liste_de_classe_simporte_avec_la_classe_et_lannee_du_titre(): void
    {
        $this->actingAsBackoffice();

        $resultat = $this->importe([
            ['ALPHA BETA', '', '26T0001', '01/01/2008', 'VILLE-A', 600000001, 'M', 'N'],
            ['GAMMA  DELTA JOSEPH  ', '100000001', '21T0002', '20/09/2005', 'VILLE-B', '600000002', 'M', 'R'],
        ]);

        $this->assertSame(2, $resultat['importes']);
        $this->assertSame([], $resultat['erreurs']);

        $eleve = Eleve::where('matricule', '21T0002')->firstOrFail();
        $this->assertSame('GAMMA DELTA JOSEPH', $eleve->nom_complet);
        $this->assertSame('100000001', $eleve->matricule_national);
        $this->assertSame('600000002', $eleve->telephone);
        $this->assertSame('2005-09-20', $eleve->date_naissance->toDateString());

        $this->assertSame('600000001', Eleve::where('matricule', '26T0001')->value('telephone'));
        $this->assertNull(Eleve::where('matricule', '26T0001')->value('matricule_national'));

        $this->assertSame(2, Inscription::activesDe($this->classe->id, '2026/2027')->count());
        $this->assertSame(
            Inscription::STATUT_REDOUBLANT,
            Inscription::whereRelation('eleve', 'matricule', '21T0002')->value('statut')
        );
        $this->assertSame(
            Inscription::STATUT_REGULIER,
            Inscription::whereRelation('eleve', 'matricule', '26T0001')->value('statut')
        );
    }

    /** Le titre peut nommer la classe par son code comme par son nom. */
    public function test_le_titre_nomme_la_classe_par_son_code(): void
    {
        $this->actingAsBackoffice();

        $this->importe([['NGONO ALINE', '', 'M1', '', '', '', 'F', '']], '2f4ba', '2025/26');

        $this->assertSame(1, Inscription::activesDe($this->classe->id, '2025/2026')->count());
    }

    /**
     * La liste passe sous Excel de main en main : titre en minuscules au
     * tiret simple, en-têtes sans accents, décalés et dans un autre ordre.
     */
    public function test_la_lecture_tolere_une_liste_retouchee(): void
    {
        $this->actingAsBackoffice();

        $classeur = new Spreadsheet;
        $ws = $classeur->getActiveSheet();
        $ws->fromArray([
            ['Liste des eleves - 2nde F4-BA - 2026-2027'],
            ['Sexe', 'Matricule interne', 'Noms et prenoms', 'Statut', 'Date de naissance'],
            ['F', '26X0001', 'MOUSSA AÏCHA', 'r', 39521],
            [],
            ['M', '26X0002', 'BELLO PAUL', '', '14/03/2008'],
        ]);
        $chemin = tempnam(sys_get_temp_dir(), 'retouche').'.xlsx';
        (new Xlsx($classeur))->save($chemin);
        $binaire = file_get_contents($chemin);
        unlink($chemin);

        $resultat = $this->envoie($binaire)->assertOk()->json();

        $this->assertSame(2, $resultat['importes']);
        $this->assertSame('2008-03-14', Eleve::where('matricule', '26X0001')->value('date_naissance')->toDateString());
        $this->assertSame(
            Inscription::STATUT_REDOUBLANT,
            Inscription::whereRelation('eleve', 'matricule', '26X0001')->value('statut')
        );
    }

    public function test_limport_signale_ligne_par_ligne_ce_quil_refuse(): void
    {
        $this->actingAsBackoffice();

        $resultat = $this->importe([
            ['SANS MATRICULE', '', '', '', '', '', 'M', 'N'],
            ['SEXE FAUX', '', 'M3', '', '', '', 'Autre', 'N'],
            ['DATE FAUSSE', '', 'M4', '32/13/2008', '', '', 'F', 'N'],
            ['STATUT FAUX', '', 'M5', '', '', '', 'F', 'auditeur'],
            ['BONNE LIGNE', '', 'M6', '', '', '', 'F', 'N'],
        ]);

        $this->assertSame(1, $resultat['importes']);
        $this->assertCount(4, $resultat['erreurs']);

        // Numéros de ligne de la feuille : les élèves commencent en ligne 4.
        $this->assertSame([4, 5, 6, 7], array_column($resultat['erreurs'], 'ligne'));
        $this->assertSame('Eleves', $resultat['erreurs'][0]['feuille_nom']);

        $messages = array_column($resultat['erreurs'], 'erreur');
        $this->assertStringContainsString('matricule interne et noms requis', $messages[0]);
        $this->assertStringContainsString('M ou F', $messages[1]);
        $this->assertStringContainsString('date de naissance illisible', $messages[2]);
        $this->assertStringContainsString('N (nouveau) ou R (redoublant)', $messages[3]);
    }

    /**
     * Une faute de frappe dans le titre ne doit pas inscrire quarante élèves
     * nulle part sans que personne ne le voie : l'onglet entier est refusé.
     */
    public function test_une_classe_inconnue_refuse_longlet_entier(): void
    {
        $this->actingAsBackoffice();

        $resultat = $this->importe([['NGONO ALINE', '', 'M1', '', '', '', 'F', 'N']], '2nde F9-ZZ');

        $this->assertSame(0, $resultat['importes']);
        $this->assertCount(1, $resultat['erreurs']);
        $this->assertSame(1, $resultat['erreurs'][0]['ligne']);
        $this->assertStringContainsString('2nde F9-ZZ', $resultat['erreurs'][0]['erreur']);
        $this->assertSame(0, Eleve::count());
    }

    /** Les listes arrivent parfois avant les affectations. */
    public function test_un_onglet_sans_classe_enregistre_lidentite_seule(): void
    {
        $this->actingAsBackoffice();

        $resultat = $this->importe([['NOUVEAU PIERRE', '', 'M9', '', '', '', 'M', '']], ListeEleves::SANS_CLASSE);

        $this->assertSame(1, $resultat['importes']);
        $this->assertSame(1, Eleve::count());
        $this->assertSame(0, Inscription::count());
    }

    public function test_un_reimport_met_a_jour_sans_dupliquer(): void
    {
        $this->actingAsBackoffice();

        Eleve::create(['matricule' => '26T0001', 'nom' => 'ALPHA', 'prenoms' => 'Beta']);

        $this->importe([['ALPHA BETA', '', '26T0001', '01/01/2008', 'VILLE-A', '', 'M', 'N']]);
        $this->importe([['ALPHA BETA', '100000002', '26T0001', '01/01/2008', 'VILLE-C', '', 'M', 'R']]);

        $this->assertSame(1, Eleve::count());
        $this->assertSame(1, Inscription::count());

        $eleve = Eleve::first();
        $this->assertSame('VILLE-C', $eleve->lieu_naissance);
        $this->assertSame('100000002', $eleve->matricule_national);
        // Le nom complet est dans une seule colonne : les anciens prénoms ne s'y ajoutent pas.
        $this->assertSame('ALPHA BETA', $eleve->nom_complet);
        $this->assertSame(Inscription::STATUT_REDOUBLANT, Inscription::first()->statut);
    }

    public function test_un_fichier_sans_onglet_reconnu_est_refuse(): void
    {
        $this->actingAsBackoffice();

        $classeur = new Spreadsheet;
        $classeur->getActiveSheet()->fromArray([['matricule', 'nom', 'code_classe'], ['M1', 'X', '2F4BA']]);
        $chemin = tempnam(sys_get_temp_dir(), 'ancien').'.xlsx';
        (new Xlsx($classeur))->save($chemin);
        $binaire = file_get_contents($chemin);
        unlink($chemin);

        $this->envoie($binaire)->assertStatus(422)->assertJsonPath(
            'message',
            fn (string $message) => str_contains($message, 'NOMS ET PRÉNOMS')
        );
        $this->assertSame(0, Eleve::count());
    }

    public function test_le_modele_suit_la_liste_officielle_un_onglet_par_classe(): void
    {
        $this->actingAsBackoffice();
        Classe::create(['nom' => 'Terminale F3', 'code' => 'TF3', 'niveau' => 'Terminale', 'effectif' => 30]);

        $reponse = $this->get('/api/spreadsheet/eleves/template')->assertOk();
        $onglets = $this->lit($this->contenu($reponse));

        $this->assertSame(['2F4BA', 'TF3'], array_keys($onglets));
        $this->assertSame('LISTE DES ÉLÈVES — 2F4BA — 2026-2027', $onglets['2F4BA'][0][0]);
        $this->assertSame(array_values(ListeEleves::COLONNES), $onglets['2F4BA'][2]);
        $this->assertSame(
            ['N°', 'NOMS ET PRÉNOMS', 'MATRICULE NATIONAL', 'MATRICULE INTERNE', 'DATE DE NAISSANCE', 'LIEU DE NAISSANCE', 'TÉLÉPHONE', 'SEXE', 'STATUT'],
            $onglets['TF3'][2]
        );
    }

    /** L'export se réimporte tel quel : c'est la voie pour corriger une liste en masse. */
    public function test_lexport_se_reimporte_tel_quel(): void
    {
        $this->actingAsBackoffice();

        $this->importe([
            ['ZETA JEAN PAUL', '100000003', '24T0003', '01/01/2009', 'VILLE-D', '600000003', 'M', 'N'],
            ['ALPHA OMEGA', '', '26T0004', '03/03/2010', 'VILLE-E', '', 'M', 'R'],
        ]);
        Eleve::create(['matricule' => 'X1', 'nom' => 'NON AFFECTE']);

        $reponse = $this->get('/api/spreadsheet/eleves/export')->assertOk();
        $binaire = $this->contenu($reponse);
        $onglets = $this->lit($binaire);

        $this->assertSame(['2F4BA', ListeEleves::SANS_CLASSE], array_keys($onglets));
        $this->assertSame(
            [1, 'ALPHA OMEGA', null, '26T0004', '03/03/2010', 'VILLE-E', null, 'M', 'R'],
            $onglets['2F4BA'][3]
        );
        $this->assertSame(
            [2, 'ZETA JEAN PAUL', '100000003', '24T0003', '01/01/2009', 'VILLE-D', '600000003', 'M', 'N'],
            $onglets['2F4BA'][4]
        );

        $resultat = $this->envoie($binaire)->assertOk()->json();
        $this->assertSame(3, $resultat['importes']);
        $this->assertSame([], $resultat['erreurs']);
        $this->assertSame(3, Eleve::count());
        $this->assertSame(2, Inscription::count());
    }

    public function test_un_enseignant_ne_peut_pas_importer(): void
    {
        $enseignant = Enseignant::factory()->create();
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $this->envoie($this->classeur('2nde F4-BA', '2026-2027', [['X', '', 'M1', '', '', '', 'M', 'N']]))
            ->assertForbidden();
        $this->assertSame(0, Eleve::count());
    }
}
