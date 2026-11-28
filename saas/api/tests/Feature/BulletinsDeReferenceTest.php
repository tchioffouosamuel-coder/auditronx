<?php

namespace Tests\Feature;

use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\ClasseModule;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\Module;
use App\Models\NoteModule;
use App\Models\Specialite;
use App\Services\AnneeScolaire;
use App\Services\BaremeApc;
use App\Services\CalculBulletin;
use App\Services\ConseilClasse;
use App\Services\ImpressionBulletin;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Critère de fin du chantier (§bulletins APC) : les quatre bulletins des cas
 * pratiques de la formation des censeurs industriels, ressaisis dans
 * l'application, doivent ressortir à l'identique.
 *
 * Source : « Formation des censeurs industriels sur l'implémentation des
 * bulletins de compétences modèle APC », cas pratiques — NGONO Aline Carine
 * (admise) et TCHAMBA Kevin Arnaud (en échec), 1re année TPV, trimestre 3 et
 * annuel.
 *
 * Seules les valeurs propres à l'élève sont comparées. Les moyennes de
 * classe du modèle portent sur un effectif de 28 qu'on ne reconstitue pas.
 *
 * `BULLETIN_PDF_DUMP=<dossier>` dépose les quatre PDF pour les comparer au
 * modèle à l'œil.
 */
class BulletinsDeReferenceTest extends TestCase
{
    use RefreshDatabase;

    private Classe $classe;

    /** @var array<string, ClasseModule> */
    private array $maquette = [];

    /** @var array<string, Inscription> */
    private array $inscriptions = [];

    /** code => [intitulé, bloc, crédit, VH, trimestres programmés] */
    private const MAQUETTE = [
        'C8' => ['Chocolats et dérivés du cacao', 'P', 10, 135, [1, 2]],
        'C9' => ['Farines (racines, tubercules, céréales)', 'P', 9, 135, [1, 2]],
        'C10' => ['Boissons alcoolisées (céréales, fruits, cacao)', 'P', 10, 150, [1, 2, 3]],
        'C11' => ['Extraction des huiles végétales', 'P', 10, 135, [2, 3]],
        'C12' => ['Produits à base des fruits', 'P', 10, 135, [2, 3]],
        'C13' => ['Stage en entreprise', 'P', 20, 300, []],
        'C1' => ['Métier et formation', 'G', 2, 30, [1]],
        'C2' => ['Qualité-Hygiène-Sécurité-Environnement', 'G', 9, 120, [1, 2]],
        'C3' => ['Techniques de conditionnement', 'G', 9, 135, [2, 3]],
        'C4' => ['Techniques de conservation', 'G', 10, 150, [1, 2, 3]],
        'C5' => ['Traitement des déchets', 'G', 9, 150, [1, 2, 3]],
        'C6' => ['Contrôle de la qualité des produits', 'G', 7, 105, [1, 2]],
        'C7' => ['Entrepreneuriat', 'G', 2, 30, [3]],
    ];

    /** Notes finales des trimestres 1 et 2, colonnes « Note Trim » des bulletins annuels. */
    private const NOTES_T1_T2 = [
        'TPV25-0142' => [
            'C8' => [85, 88], 'C9' => [80, 83], 'C10' => [84, 86], 'C11' => [null, 81], 'C12' => [null, 87],
            'C1' => [92, null], 'C2' => [88, 90], 'C3' => [null, 82], 'C4' => [79, 81], 'C5' => [76, 77], 'C6' => [83, 85],
        ],
        'TPV25-0157' => [
            'C8' => [62, 65], 'C9' => [70, 72], 'C10' => [63, 66], 'C11' => [null, 61], 'C12' => [null, 70],
            'C1' => [78, null], 'C2' => [68, 71], 'C3' => [null, 66], 'C4' => [64, 67], 'C5' => [70, 71], 'C6' => [60, 63],
        ],
    ];

    /** Trimestre 3 : moyenne UEA (/60) et épreuve intégratrice (/40), telles qu'imprimées. */
    private const NOTES_T3 = [
        'TPV25-0142' => ['C10' => [52, 35], 'C11' => [50, 34], 'C12' => [54, 36], 'C3' => [51, 33], 'C4' => [49, 33], 'C5' => [47, 31], 'C7' => [53, 35]],
        'TPV25-0157' => ['C10' => [40, 24], 'C11' => [38, 22], 'C12' => [43, 28], 'C3' => [42, 27], 'C4' => [41, 25], 'C5' => [44, 28], 'C7' => [46, 29]],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-20 09:00:00'));

        $specialite = Specialite::create(['code' => 'TPV', 'nom' => 'Transformation des Produits d’origine Végétale']);
        $this->classe = Classe::create(['nom' => '1re année TPV', 'code' => '1TPV', 'niveau' => '1re année', 'specialite_id' => $specialite->id]);

        $ordre = 0;
        foreach (self::MAQUETTE as $code => [$intitule, $bloc, $credit, $vh, $trimestres]) {
            $module = Module::create([
                'specialite_id' => $specialite->id, 'niveau' => '1re année', 'code' => $code, 'intitule' => $intitule,
                'bloc' => $bloc === 'P' ? Module::BLOC_COMPETENCES_PARTICULIERES : Module::BLOC_COMPETENCES_GENERALES,
                'credit' => $credit, 'volume_horaire' => $vh, 'ordre' => $ordre,
            ]);
            $this->maquette[$code] = ClasseModule::create([
                'classe_id' => $this->classe->id, 'module_id' => $module->id, 'credit' => $credit,
                'volume_horaire' => $vh, 'trimestres' => $trimestres, 'ordre' => $ordre++,
            ]);
        }

        $this->eleve('TPV25-0142', 'NGONO', 'Aline Carine', 'F', '2008-03-14', 'Ebolowa');
        $this->eleve('TPV25-0157', 'TCHAMBA', 'Kevin Arnaud', 'M', '2007-09-02', 'Dschang');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function eleve(string $matricule, string $nom, string $prenoms, string $sexe, string $naissance, string $lieu): void
    {
        $eleve = Eleve::create([
            'matricule' => $matricule, 'nom' => $nom, 'prenoms' => $prenoms, 'sexe' => $sexe,
            'date_naissance' => $naissance, 'lieu_naissance' => $lieu,
        ]);
        $inscription = $this->inscriptions[$matricule] = Inscription::create([
            'eleve_id' => $eleve->id, 'classe_id' => $this->classe->id, 'annee_scolaire' => AnneeScolaire::courante(),
        ]);

        // Les bulletins annuels ne donnent que la note finale des T1 et T2 :
        // on la répartit 60/40, la somme seule compte.
        foreach (self::NOTES_T1_T2[$matricule] as $code => $notes) {
            foreach ($notes as $i => $note) {
                if ($note !== null) {
                    $uea = round($note * 0.6);
                    $this->note($inscription, $code, $i + 1, $uea, $note - $uea);
                }
            }
        }

        foreach (self::NOTES_T3[$matricule] as $code => [$uea, $integratrice]) {
            $this->note($inscription, $code, 3, $uea, $integratrice);
        }
    }

    private function note(Inscription $inscription, string $code, int $trimestre, float $uea, float $integratrice): void
    {
        NoteModule::create([
            'inscription_id' => $inscription->id, 'classe_module_id' => $this->maquette[$code]->id,
            'trimestre' => $trimestre, 'moyenne_uea' => $uea, 'note_integratrice' => $integratrice,
        ]);
    }

    /** @return array<string, mixed> la page imprimée */
    private function page(string $matricule, string $periode): array
    {
        $annee = AnneeScolaire::courante();
        if (! Bulletin::duConseil($this->classe->id, $annee, $periode)->exists()) {
            CalculBulletin::generer($this->classe, $annee, $periode);
            ConseilClasse::valider($this->classe, $annee, $periode, null);
        }

        $bulletin = Bulletin::where('inscription_id', $this->inscriptions[$matricule]->id)
            ->where('periode', $periode)->courants()->sole();

        $dossier = getenv('BULLETIN_PDF_DUMP');
        if ($dossier) {
            file_put_contents(
                rtrim($dossier, '/\\').DIRECTORY_SEPARATOR."reference-{$matricule}-{$periode}.pdf",
                ImpressionBulletin::pdfBulletins(collect([$bulletin]))->output(),
            );
        }

        return ImpressionBulletin::page($bulletin);
    }

    /**
     * Les lignes imprimées, par code : [notes…, note finale, crédit, mention, sanction, appréciation].
     *
     * @return array<string, array<int, string>>
     */
    private function lignes(array $page): array
    {
        $lignes = [];
        foreach ($page['blocs'] as $bloc) {
            foreach ($bloc['lignes'] as $l) {
                $lignes[$l['code']] = $page['annuel']
                    ? [...array_values($l['notes']), $l['note_finale'], $l['credit'], $l['volume_horaire'], $l['mention'], $l['sanction'], $l['appreciation']]
                    : [$l['moyenne_uea'], $l['note_integratrice'], $l['note_finale'], $l['credit'], $l['mention'], $l['sanction'], $l['appreciation']];
            }
        }

        return $lignes;
    }

    private const NA = 'Non acquise – à reprendre';

    public function test_ngono_trimestre_3(): void
    {
        $page = $this->page('TPV25-0142', '3');

        $this->assertSame('BULLETIN DE COMPÉTENCES : Trimestre 3', $page['titre']);
        $this->assertSame('Transformation des Produits d’origine Végétale (TPV)', $page['classe']['specialite']);
        $this->assertSame(['14/03/2008', 'Ebolowa', 'F'], [$page['eleve']['date_naissance'], $page['eleve']['lieu_naissance'], $page['eleve']['sexe']]);

        // Seuls les modules programmés au T3 sont imprimés.
        $this->assertSame([
            'C10' => ['52', '35', '87', '10', 'B', 'S', 'Très bien'],
            'C11' => ['50', '34', '84', '10', 'C', 'S', 'Bien'],
            'C12' => ['54', '36', '90', '10', 'A', 'S', 'Excellent'],
            'C3' => ['51', '33', '84', '9', 'C', 'S', 'Bien'],
            'C4' => ['49', '33', '82', '10', 'C', 'S', 'Bien'],
            'C5' => ['47', '31', '78', '9', 'D', 'S', 'Assez bien'],
            'C7' => ['53', '35', '88', '2', 'B', 'S', 'Très bien'],
        ], $this->lignes($page));

        $this->assertSame('84,4', $page['pied']['moyenne']);
        $this->assertSame('60', $page['pied']['credits']);
        $this->assertSame('C', $page['pied']['mention']);
        $this->assertSame('SUCCÈS', $page['pied']['decision']);
        $this->assertSame('Toutes validées', $page['pied']['competences']);
        $this->assertSame(BaremeApc::POSITION_TETE, $page['synthese']['position']);
        $this->assertSame(BaremeApc::VALIDATION_OUI, $page['synthese']['validation']);
        $this->assertStringEndsWith('programmées au trimestre 3.', $page['seuil']);
    }

    public function test_tchamba_trimestre_3(): void
    {
        $page = $this->page('TPV25-0157', '3');

        $this->assertSame([
            'C10' => ['40', '24', '64', '10', '—', 'E', self::NA],
            'C11' => ['38', '22', '60', '10', '—', 'E', self::NA],
            'C12' => ['43', '28', '71', '10', 'E', 'S', 'Passable'],
            'C3' => ['42', '27', '69', '9', '—', 'E', self::NA],
            'C4' => ['41', '25', '66', '10', '—', 'E', self::NA],
            'C5' => ['44', '28', '72', '9', 'E', 'S', 'Passable'],
            'C7' => ['46', '29', '75', '2', 'D', 'S', 'Assez bien'],
        ], $this->lignes($page));

        $this->assertSame('67,2', $page['pied']['moyenne']);
        $this->assertSame('60', $page['pied']['credits']);
        $this->assertSame('—', $page['pied']['mention']);
        $this->assertSame('ÉCHEC', $page['pied']['decision']);
        $this->assertTrue($page['pied']['defavorable']);
        $this->assertSame('4 compétence(s) non validée(s)', $page['pied']['competences']);
        $this->assertSame(BaremeApc::POSITION_DIFFICULTE, $page['synthese']['position']);
        $this->assertSame(BaremeApc::VALIDATION_NON_PARTIEL, $page['synthese']['validation']);
    }

    public function test_ngono_annuel(): void
    {
        $page = $this->page('TPV25-0142', 'annuel');

        $this->assertSame('BULLETIN DE COMPÉTENCES : ANNUEL', $page['titre']);
        $this->assertSame([
            'C8' => ['85', '88', '—', '86,5', '10', '135', 'B', 'S', 'Très bien'],
            'C9' => ['80', '83', '—', '81,5', '9', '135', 'C', 'S', 'Bien'],
            'C10' => ['84', '86', '87', '85,7', '10', '150', 'B', 'S', 'Très bien'],
            'C11' => ['—', '81', '84', '82,5', '10', '135', 'C', 'S', 'Bien'],
            'C12' => ['—', '87', '90', '88,5', '10', '135', 'B', 'S', 'Très bien'],
            'C13' => ['—', '—', '—', '—', '(20)', '300', '—', '—', 'Non programmé'],
            'C1' => ['92', '—', '—', '92', '2', '30', 'A', 'S', 'Excellent'],
            'C2' => ['88', '90', '—', '89', '9', '120', 'B', 'S', 'Très bien'],
            'C3' => ['—', '82', '84', '83', '9', '135', 'C', 'S', 'Bien'],
            'C4' => ['79', '81', '82', '80,7', '10', '150', 'C', 'S', 'Bien'],
            'C5' => ['76', '77', '78', '77', '9', '150', 'D', 'S', 'Assez bien'],
            'C6' => ['83', '85', '—', '84', '7', '105', 'C', 'S', 'Bien'],
            'C7' => ['—', '—', '88', '88', '2', '30', 'B', 'S', 'Très bien'],
        ], $this->lignes($page));

        $this->assertSame('84,1', $page['pied']['moyenne']);
        $this->assertSame('97', $page['pied']['credits']);
        $this->assertSame('1410', $page['pied']['volume_horaire']);
        $this->assertSame('C', $page['pied']['mention']);
        $this->assertSame('Toutes validées', $page['pied']['competences']);
        // Le modèle imprime « SUCCÈS » au pied de l'annuel ; le barème nomme
        // la décision annuelle « Admis ». Voir BULLETINS-APC.md, points ouverts.
        $this->assertSame(BaremeApc::DECISION_ADMIS, $page['synthese']['decision']);
        $this->assertSame(BaremeApc::POSITION_TETE, $page['synthese']['position']);
    }

    public function test_tchamba_annuel(): void
    {
        $page = $this->page('TPV25-0157', 'annuel');

        $this->assertSame([
            'C8' => ['62', '65', '—', '63,5', '10', '135', '—', 'E', self::NA],
            'C9' => ['70', '72', '—', '71', '9', '135', 'E', 'S', 'Passable'],
            'C10' => ['63', '66', '64', '64,3', '10', '150', '—', 'E', self::NA],
            'C11' => ['—', '61', '60', '60,5', '10', '135', '—', 'E', self::NA],
            'C12' => ['—', '70', '71', '70,5', '10', '135', 'E', 'S', 'Passable'],
            'C13' => ['—', '—', '—', '—', '(20)', '300', '—', '—', 'Non programmé'],
            'C1' => ['78', '—', '—', '78', '2', '30', 'D', 'S', 'Assez bien'],
            'C2' => ['68', '71', '—', '69,5', '9', '120', '—', 'E', self::NA],
            'C3' => ['—', '66', '69', '67,5', '9', '135', '—', 'E', self::NA],
            'C4' => ['64', '67', '66', '65,7', '10', '150', '—', 'E', self::NA],
            'C5' => ['70', '71', '72', '71', '9', '150', 'E', 'S', 'Passable'],
            'C6' => ['60', '63', '—', '61,5', '7', '105', '—', 'E', self::NA],
            'C7' => ['—', '—', '75', '75', '2', '30', 'D', 'S', 'Assez bien'],
        ], $this->lignes($page));

        $this->assertSame('66,9', $page['pied']['moyenne']);
        $this->assertSame('97', $page['pied']['credits']);
        $this->assertSame('1410', $page['pied']['volume_horaire']);
        $this->assertSame('—', $page['pied']['mention']);
        $this->assertSame('7 compétence(s) non validée(s)', $page['pied']['competences']);
        $this->assertSame(BaremeApc::DECISION_REDOUBLEMENT, $page['synthese']['decision']);
        $this->assertSame(BaremeApc::POSITION_DIFFICULTE, $page['synthese']['position']);
        $this->assertSame(BaremeApc::VALIDATION_NON_PARTIEL, $page['synthese']['validation']);
    }

    /** Les blocs sans module restent imprimés, « sans objet », comme dans le modèle. */
    public function test_les_blocs_vides_sont_imprimes_sans_objet(): void
    {
        $page = $this->page('TPV25-0142', '3');

        $this->assertSame([
            'MODULES DES COMPÉTENCES PARTICULIÈRES', 'MODULES DES COMPÉTENCES GÉNÉRALES',
            'MODULES DES ENSEIGNEMENTS GÉNÉRAUX', 'MODULES DES ENSEIGNEMENTS COMPLÉMENTAIRES',
        ], array_column($page['blocs'], 'libelle'));
        $this->assertSame([], $page['blocs'][2]['lignes']);

        $html = view('pdf.bulletins', ['pages' => [$page]])->render();
        $this->assertStringContainsString('Non couverts par le référentiel de formation TPV – sans objet', $html);
        $this->assertStringContainsString('Mention A (Excellent) : [90 - 100]', $html);
        $this->assertStringContainsString('Mention E (Passable) : [70 - 74]', $html);
        $this->assertStringContainsString('[X] <span class="rouge b">Succès</span>', $html);
    }
}
