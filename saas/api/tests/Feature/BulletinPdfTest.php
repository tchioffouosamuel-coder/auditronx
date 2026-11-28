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
use App\Services\CalculBulletin;
use App\Services\ConseilClasse;
use App\Services\ImpressionBulletin;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Impression des bulletins et du PV (§bulletins APC, lot 6).
 *
 * La classe de test a la forme la plus serrée du modèle : treize modules sur
 * les quatre blocs, un module ouvert au deuxième trimestre et un stage non
 * programmé. Avec `BULLETIN_PDF_DUMP=<dossier>`, les PDF produits sont
 * déposés dans ce dossier pour être regardés — un rendu qui déborde ne se
 * voit pas dans une assertion.
 */
class BulletinPdfTest extends TestCase
{
    use RefreshDatabase;

    private Classe $classe;

    /** @var array<string, ClasseModule> */
    private array $maquette = [];

    /** @var array<string, Inscription> */
    private array $inscriptions = [];

    private const MODULES = [
        // code, intitulé, bloc, crédit, VH, trimestres
        ['C10', 'Conduite d\'une opération de transformation des fruits', Module::BLOC_COMPETENCES_PARTICULIERES, 10, 150, [1, 2, 3]],
        ['C11', 'Conditionnement et emballage des produits végétaux', Module::BLOC_COMPETENCES_PARTICULIERES, 8, 120, [1, 2, 3]],
        ['C12', 'Contrôle qualité des produits transformés', Module::BLOC_COMPETENCES_PARTICULIERES, 8, 120, [1, 2, 3]],
        ['C13', 'Fabrication des jus, nectars et sirops', Module::BLOC_COMPETENCES_PARTICULIERES, 6, 90, [2, 3]],
        ['STG', 'Stage en milieu professionnel', Module::BLOC_COMPETENCES_PARTICULIERES, 12, 160, []],
        ['C1', 'Métier et formation', Module::BLOC_COMPETENCES_GENERALES, 2, 30, [1, 2, 3]],
        ['C5', 'Hygiène, santé et sécurité au travail', Module::BLOC_COMPETENCES_GENERALES, 4, 60, [1, 2, 3]],
        ['C6', 'Communication en milieu professionnel', Module::BLOC_COMPETENCES_GENERALES, 3, 45, [1, 2, 3]],
        ['EG1', 'Français', Module::BLOC_ENSEIGNEMENTS_GENERAUX, 3, 60, [1, 2, 3]],
        ['EG2', 'Anglais', Module::BLOC_ENSEIGNEMENTS_GENERAUX, 3, 60, [1, 2, 3]],
        ['EG3', 'Mathématiques appliquées', Module::BLOC_ENSEIGNEMENTS_GENERAUX, 4, 75, [1, 2, 3]],
        ['EC1', 'Éducation physique et sportive', Module::BLOC_ENSEIGNEMENTS_COMPLEMENTAIRES, 2, 45, [1, 2, 3]],
        ['EC2', 'Entrepreneuriat', Module::BLOC_ENSEIGNEMENTS_COMPLEMENTAIRES, 2, 30, [1, 2, 3]],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-20 09:00:00'));

        $specialite = Specialite::create(['code' => 'TPV', 'nom' => 'Transformation des produits végétaux']);
        $this->classe = Classe::create([
            'nom' => '1re année TPV',
            'code' => '1TPV',
            'niveau' => 'Seconde',
            'specialite_id' => $specialite->id,
        ]);

        $enseignant = Enseignant::factory()->create(['nom' => 'MBARGA Jean Paul']);

        foreach (self::MODULES as $ordre => [$code, $intitule, $bloc, $credit, $vh, $trimestres]) {
            $module = Module::create([
                'specialite_id' => $specialite->id, 'niveau' => 'Seconde', 'code' => $code,
                'intitule' => $intitule, 'bloc' => $bloc, 'credit' => $credit, 'volume_horaire' => $vh, 'ordre' => $ordre,
            ]);
            $this->maquette[$code] = ClasseModule::create([
                'classe_id' => $this->classe->id, 'module_id' => $module->id, 'credit' => $credit,
                'volume_horaire' => $vh, 'trimestres' => $trimestres, 'ordre' => $ordre, 'enseignant_id' => $enseignant->id,
            ]);
        }

        // NGONO réussit partout ; TCHAMBA échoue en C11 et EG3.
        $this->eleve('TPV25-0142', 'NGONO', 'Aline Carine', 'F', 52, 35);
        $this->eleve('TPV25-0157', 'TCHAMBA', 'Hervé', 'M', 50, 24, ['C11' => [30, 20], 'EG3' => [25, 18]]);
        $this->eleve('TPV25-0163', 'ABENA', 'Marie', 'F', 48, 30);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        // Le barème est mémorisé par base : un test qui le change le
        // laisserait aux suivants, la transaction annulée n'y touche pas.
        BaremeApc::oublier();
        parent::tearDown();
    }

    private function eleve(string $matricule, string $nom, string $prenoms, string $sexe, float $uea, float $ei, array $exceptions = []): void
    {
        $eleve = Eleve::create([
            'matricule' => $matricule, 'nom' => $nom, 'prenoms' => $prenoms, 'sexe' => $sexe,
            'date_naissance' => '2008-03-14', 'lieu_naissance' => 'Yaoundé',
        ]);
        $inscription = $this->inscriptions[$matricule] = Inscription::create([
            'eleve_id' => $eleve->id, 'classe_id' => $this->classe->id, 'annee_scolaire' => AnneeScolaire::courante(),
        ]);

        foreach ($this->maquette as $code => $ligne) {
            foreach ($ligne->trimestresProgrammes() as $t) {
                [$u, $e] = $exceptions[$code] ?? [$uea, $ei];
                NoteModule::create([
                    'inscription_id' => $inscription->id, 'classe_module_id' => $ligne->id, 'trimestre' => $t,
                    'moyenne_uea' => min(60, $u + $t), 'note_integratrice' => $e,
                ]);
            }
        }
    }

    private function actingAsBackoffice(): User
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    private function conseilValide(string $periode): void
    {
        CalculBulletin::generer($this->classe, AnneeScolaire::courante(), $periode);
        ConseilClasse::valider($this->classe, AnneeScolaire::courante(), $periode, null);
    }

    private function bulletinDe(string $matricule, string $periode): Bulletin
    {
        return Bulletin::where('inscription_id', $this->inscriptions[$matricule]->id)
            ->where('periode', $periode)->courants()->sole();
    }

    /** Dépose le PDF pour relecture visuelle quand BULLETIN_PDF_DUMP est posé. */
    private function deposer(string $nom, string $contenu): void
    {
        $dossier = getenv('BULLETIN_PDF_DUMP');
        if ($dossier) {
            file_put_contents(rtrim($dossier, '/\\').DIRECTORY_SEPARATOR.$nom, $contenu);
        }
    }

    private function assertPdf($reponse): string
    {
        $reponse->assertOk();
        $this->assertSame('application/pdf', $reponse->headers->get('content-type'));
        $contenu = (string) $reponse->getContent();
        $this->assertStringStartsWith('%PDF', $contenu);

        return $contenu;
    }

    /* ------------------------------------------------------------------ */

    public function test_le_bulletin_annuel_de_treize_modules_tient_sur_une_page(): void
    {
        $this->actingAsBackoffice();
        $this->conseilValide('annuel');
        $bulletin = $this->bulletinDe('TPV25-0142', 'annuel');

        $pdf = $this->assertPdf($this->get("/api/bulletins/{$bulletin->id}/pdf"));
        $this->deposer('annuel-ngono.pdf', $pdf);

        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf));
    }

    public function test_le_bulletin_trimestriel_se_telecharge(): void
    {
        $this->actingAsBackoffice();
        $this->conseilValide('1');
        $bulletin = $this->bulletinDe('TPV25-0157', '1');

        $reponse = $this->get("/api/bulletins/{$bulletin->id}/pdf");
        $pdf = $this->assertPdf($reponse);
        $this->deposer('t1-tchamba.pdf', $pdf);

        $this->assertStringContainsString('bulletin-tpv25-0157-t1-2025-2026.pdf', $reponse->headers->get('content-disposition'));
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf));
    }

    public function test_la_classe_simprime_une_page_par_eleve(): void
    {
        $this->actingAsBackoffice();
        $this->conseilValide('3');

        $pdf = $this->assertPdf($this->get("/api/bulletins/classe/{$this->classe->id}/pdf?periode=3"));
        $this->deposer('t3-classe.pdf', $pdf);

        $this->assertSame(3, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf));
    }

    public function test_le_pv_du_conseil_se_telecharge(): void
    {
        $this->actingAsBackoffice();
        $this->conseilValide('3');
        $this->putJson('/api/bulletins/'.$this->bulletinDe('TPV25-0157', '3')->id, [])->assertStatus(422);

        $pdf = $this->assertPdf($this->get("/api/bulletins/classe/{$this->classe->id}/pv?periode=3"));
        $this->deposer('t3-pv.pdf', $pdf);
    }

    /* ------------------------------------------------------------------ */
    /* Ce que la page imprime                                              */
    /* ------------------------------------------------------------------ */

    /** Tiret, parenthèses, virgule, initiales : la mise en forme, sans recalcul. */
    public function test_la_page_annuelle_imprime_tiret_parentheses_et_initiales(): void
    {
        $this->conseilValide('annuel');
        $page = ImpressionBulletin::page($this->bulletinDe('TPV25-0142', 'annuel'));

        $lignes = collect($page['blocs'])->flatMap(fn (array $b) => $b['lignes'])->keyBy('code');

        $this->assertSame([1, 2, 3], $page['trimestres']);
        $this->assertSame(BaremeApc::TIRET, $lignes['C13']['notes'][1]);   // ouvert au T2
        $this->assertSame('89,0', $lignes['C13']['notes'][2]);             // 52+2 + 35
        $this->assertSame('(12)', $lignes['STG']['credit']);               // non programmé
        $this->assertSame(BaremeApc::TIRET, $lignes['STG']['note_finale']);
        $this->assertSame('M.J.P.', $lignes['C10']['signataire']);
        $this->assertSame('Année Scolaire : 2025-2026', 'Année Scolaire : '.$page['annee']);
        $this->assertSame('1er', $page['synthese']['rang']);
        $this->assertSame(
            ['Modules des compétences particulières', 'Modules des compétences générales',
                'Modules des enseignements généraux', 'Modules des enseignements complémentaires'],
            array_column($page['blocs'], 'libelle'),
        );
    }

    /**
     * Le snapshot fait foi : changer le barème après la validation ne change
     * ni les mentions ni la légende d'une réimpression.
     */
    public function test_une_reimpression_garde_le_bareme_du_calcul(): void
    {
        $this->conseilValide('3');
        $bulletin = $this->bulletinDe('TPV25-0142', '3');
        $avant = ImpressionBulletin::page($bulletin);

        BaremeApc::definir(['mentions' => ['A' => 95, 'B' => 90, 'C' => 85, 'D' => 80, 'E' => 75], 'seuil_reussite' => 75]);
        $this->maquette['C10']->update(['credit' => 1]);

        $apres = ImpressionBulletin::page($bulletin->fresh());

        $this->assertSame($avant['legende'], $apres['legende']);
        $this->assertSame('≥ 85,0', $apres['legende'][1]['borne']);
        $this->assertSame($avant['blocs'], $apres['blocs']);
        $this->assertSame($avant['synthese']['moyenne'], $apres['synthese']['moyenne']);
    }

    /** Un brouillon s'imprime pour relecture, mais il le dit. */
    public function test_un_brouillon_simprime_barre(): void
    {
        $this->actingAsBackoffice();
        CalculBulletin::generer($this->classe, AnneeScolaire::courante(), '3');

        $page = ImpressionBulletin::page($this->bulletinDe('TPV25-0142', '3'));
        $this->assertSame('BROUILLON', $page['filigrane']);

        $html = view('pdf.bulletins', ['pages' => [$page]])->render();
        $this->assertStringContainsString('class="filigrane">BROUILLON', $html);

        $this->deposer('t3-brouillon.pdf', $this->assertPdf($this->get("/api/bulletins/classe/{$this->classe->id}/pdf?periode=3")));
    }

    public function test_la_decision_retenue_est_cochee_et_le_pv_marque_lecart(): void
    {
        CalculBulletin::generer($this->classe, AnneeScolaire::courante(), '3');
        $tchamba = $this->bulletinDe('TPV25-0157', '3');
        ConseilClasse::modifier($tchamba, ['decision' => BaremeApc::DECISION_ECHEC, 'observation' => 'Reprendre C11'], null);
        ConseilClasse::valider($this->classe, AnneeScolaire::courante(), '3', null);

        $html = view('pdf.bulletins', ['pages' => [ImpressionBulletin::page($tchamba->fresh())]])->render();
        $this->assertStringContainsString('☒</span> Échec', $html);
        $this->assertStringContainsString('☐</span> Remédiation', $html);
        $this->assertStringContainsString('Reprendre C11', $html);

        $pv = ImpressionBulletin::pv($this->classe, AnneeScolaire::courante(), '3', Bulletin::duConseil($this->classe->id, AnneeScolaire::courante(), '3')->courants()->get());
        $ligne = collect($pv['eleves'])->firstWhere('matricule', 'TPV25-0157');
        $this->assertTrue($ligne['modifiee']);
        $this->assertSame('Échec', $ligne['decision']);
        $this->assertSame('Remédiation', $ligne['decision_proposee']);

        $c11 = collect($pv['modules'])->firstWhere('code', 'C11');
        $this->assertSame(3, $c11['notes']);
        $this->assertSame(2, $c11['validees']);
        $this->assertSame('67 %', $c11['taux']);
    }

    /* ------------------------------------------------------------------ */
    /* Archive et refus                                                    */
    /* ------------------------------------------------------------------ */

    public function test_larchive_range_un_dossier_par_classe_et_ignore_les_brouillons(): void
    {
        $this->actingAsBackoffice();
        $this->conseilValide('3');

        $autre = Classe::create(['nom' => '2e année TPV', 'code' => '2TPV', 'niveau' => 'Seconde', 'specialite_id' => $this->classe->specialite_id]);
        foreach ($this->classe->maquette as $ligne) {
            ClasseModule::create([...$ligne->only(['module_id', 'credit', 'volume_horaire', 'trimestres', 'ordre']), 'classe_id' => $autre->id]);
        }
        $eleve = Eleve::create(['matricule' => 'TPV24-0001', 'nom' => 'ESSOMBA']);
        Inscription::create(['eleve_id' => $eleve->id, 'classe_id' => $autre->id, 'annee_scolaire' => AnneeScolaire::courante()]);
        CalculBulletin::generer($autre, AnneeScolaire::courante(), '3'); // brouillon : hors archive

        $reponse = $this->get('/api/bulletins/export-zip?periode=3');
        $reponse->assertOk();
        $this->assertSame('application/zip', $reponse->headers->get('content-type'));

        $fichier = $reponse->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $zip->open($fichier);
        $noms = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $noms[] = $zip->getNameIndex($i);
        }
        $zip->close();
        sort($noms);

        $this->assertSame([
            '1tpv/bulletins-1tpv-t3-2025-2026.pdf',
            '1tpv/pv-conseil-1tpv-t3-2025-2026.pdf',
        ], $noms);
    }

    public function test_une_classe_sans_bulletin_le_dit(): void
    {
        $this->actingAsBackoffice();

        $this->getJson("/api/bulletins/classe/{$this->classe->id}/pdf?periode=3")
            ->assertNotFound()
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'conseil de classe'));
        $this->getJson("/api/bulletins/classe/{$this->classe->id}/pdf")->assertStatus(422);
        $this->getJson('/api/bulletins/export-zip?periode=3')->assertNotFound();
    }

    public function test_un_enseignant_imprime_un_bulletin_mais_pas_larchive(): void
    {
        $this->conseilValide('3');
        $bulletin = $this->bulletinDe('TPV25-0142', '3');

        $enseignant = Enseignant::factory()->create();
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $this->assertPdf($this->get("/api/bulletins/{$bulletin->id}/pdf"));
        $this->getJson('/api/bulletins/export-zip?periode=3')->assertForbidden();
    }
}
