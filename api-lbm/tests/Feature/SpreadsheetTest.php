<?php

namespace Tests\Feature;

use App\Exports\ArrayExport;
use App\Models\Classe;
use App\Models\Discipline;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Maatwebsite\Excel\Excel as ExcelWriterType;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class SpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsBackoffice(): User
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    public function test_telechargement_du_modele_et_export_pour_chaque_entite(): void
    {
        $this->actingAsBackoffice();
        Classe::factory()->create(['nom' => 'Terminale D', 'code' => 'TD1']);
        Discipline::factory()->create(['nom' => 'Maths', 'code' => 'MATH']);
        Enseignant::factory()->create(['nom' => 'Jean Test', 'matricule' => 'MAT-001']);

        foreach (['personnel', 'classes', 'disciplines', 'emplois'] as $entity) {
            $this->get("/api/spreadsheet/{$entity}/template")
                ->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

            $this->get("/api/spreadsheet/{$entity}/export")
                ->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
    }

    public function test_import_xlsx_cree_et_met_a_jour_les_classes(): void
    {
        $this->actingAsBackoffice();

        $binary = Excel::raw(
            new ArrayExport(['nom', 'code', 'niveau', 'specialite', 'effectif'], [
                ['Terminale C', 'TC1', 'Terminale', 'Sciences', 40],
                ['Première D', 'PD1', 'Première', 'Sciences', 35],
            ]),
            ExcelWriterType::XLSX
        );

        $file = UploadedFile::fake()->createWithContent('classes.xlsx', $binary);

        $response = $this->post('/api/spreadsheet/classes/import', ['file' => $file])->assertOk();

        $response->assertJson(['importes' => 2, 'erreurs' => []]);
        $this->assertDatabaseHas('classes', ['code' => 'TC1', 'effectif' => 40]);
        $this->assertDatabaseHas('classes', ['code' => 'PD1']);

        // Rejouer le même import ne duplique pas (upsert par code) et met à jour l'effectif.
        $binary2 = Excel::raw(
            new ArrayExport(['nom', 'code', 'niveau', 'specialite', 'effectif'], [
                ['Terminale C', 'TC1', 'Terminale', 'Sciences', 42],
            ]),
            ExcelWriterType::XLSX
        );
        $this->post('/api/spreadsheet/classes/import', [
            'file' => UploadedFile::fake()->createWithContent('classes2.xlsx', $binary2),
        ])->assertOk()->assertJson(['importes' => 1]);

        $this->assertSame(1, Classe::where('code', 'TC1')->count());
        $this->assertDatabaseHas('classes', ['code' => 'TC1', 'effectif' => 42]);
    }

    public function test_import_xlsx_rapporte_les_lignes_invalides(): void
    {
        $this->actingAsBackoffice();

        $binary = Excel::raw(
            new ArrayExport(['nom', 'code', 'niveau', 'specialite', 'effectif'], [
                ['Classe sans code', '', null, null, null],
                ['Seconde A', 'SA1', null, null, 30],
            ]),
            ExcelWriterType::XLSX
        );

        $response = $this->post('/api/spreadsheet/classes/import', [
            'file' => UploadedFile::fake()->createWithContent('classes.xlsx', $binary),
        ])->assertOk();

        $response->assertJsonPath('importes', 1);
        $response->assertJsonPath('erreurs.0.erreur', 'nom et code requis');
    }

    public function test_emplois_exporte_et_importe_un_onglet_par_classe_avec_listes_predefinies(): void
    {
        $this->actingAsBackoffice();

        $classeA = Classe::factory()->create(['nom' => 'Terminale A', 'code' => 'TA1']);
        $classeB = Classe::factory()->create(['nom' => 'Terminale B', 'code' => 'TB1']);
        $enseignant = Enseignant::factory()->create(['matricule' => 'MAT-001']);
        $discipline = Discipline::factory()->create(['code' => 'MATH']);

        foreach ([$classeA, $classeB] as $index => $classe) {
            EmploiDuTemps::create([
                'enseignant_id' => $enseignant->id,
                'classe_id' => $classe->id,
                'discipline_id' => $discipline->id,
                'jour' => $index + 1,
                'heure_debut' => '07:30',
                'heure_fin' => '08:10',
            ]);
        }

        $template = $this->get('/api/spreadsheet/emplois/template')->assertOk();
        $templateWorkbook = $this->readWorkbook($this->downloadedFileContents($template));
        $this->assertSame(['TA1', 'TB1', '_listes'], $templateWorkbook->getSheetNames());
        $this->assertSame('=MatriculesEnseignants', $templateWorkbook->getSheet(0)->getCell('A2')->getDataValidation()->getFormula1());
        $this->assertSame('=CodesDisciplines', $templateWorkbook->getSheet(0)->getCell('C2')->getDataValidation()->getFormula1());
        $this->assertSame('=HeuresDebut', $templateWorkbook->getSheet(0)->getCell('E2')->getDataValidation()->getFormula1());
        $this->assertSame('=HeuresFin', $templateWorkbook->getSheet(0)->getCell('F2')->getDataValidation()->getFormula1());
        $this->assertSame('veryHidden', $templateWorkbook->getSheetByName('_listes')->getSheetState());

        $export = $this->get('/api/spreadsheet/emplois/export')->assertOk();
        $exportBinary = $this->downloadedFileContents($export);
        $exportWorkbook = $this->readWorkbook($exportBinary);
        $this->assertSame(['TA1', 'TB1', '_listes'], $exportWorkbook->getSheetNames());
        $this->assertSame('list', $exportWorkbook->getSheet(0)->getCell('A2')->getDataValidation()->getType());

        $response = $this->post('/api/spreadsheet/emplois/import', [
            'file' => UploadedFile::fake()->createWithContent('emplois.xlsx', $exportBinary),
        ])->assertOk();

        $response->assertJson(['importes' => 2, 'erreurs' => []]);
        $this->assertSame(2, EmploiDuTemps::count());
    }

    public function test_import_emplois_refuse_les_matieres_et_horaires_hors_liste(): void
    {
        $this->actingAsBackoffice();
        Classe::factory()->create(['code' => 'TA1']);
        Enseignant::factory()->create(['matricule' => 'MAT-001']);
        Discipline::factory()->create(['code' => 'MATH']);
        $binary = Excel::raw(
            new ArrayExport(
                ['matricule_enseignant', 'code_classe', 'code_discipline', 'jour', 'heure_debut', 'heure_fin', 'salle', 'type_cours'],
                [
                    ['MAT-001', 'TA1', 'MATH', 1, '08:00', '08:50', null, null],
                    ['MAT-001', 'TA1', 'UNKNOWN', 2, '08:10', '08:50', null, null],
                ],
            ),
            ExcelWriterType::XLSX,
        );

        $response = $this->post('/api/spreadsheet/emplois/import', [
            'file' => UploadedFile::fake()->createWithContent('emplois.xlsx', $binary),
        ])->assertOk();

        $response->assertJsonPath('importes', 0);
        $response->assertJsonPath('erreurs.0.erreur', 'heure_debut doit être choisie dans la liste prédéfinie');
        $response->assertJsonPath('erreurs.1.erreur', 'enseignant/classe/discipline introuvable');
        $this->assertSame(0, EmploiDuTemps::count());
    }

    private function readWorkbook(string $binary): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'emplois-');
        file_put_contents($path, $binary);

        try {
            return IOFactory::load($path);
        } finally {
            unlink($path);
        }
    }

    private function downloadedFileContents(TestResponse $response): string
    {
        /** @var BinaryFileResponse $fileResponse */
        $fileResponse = $response->baseResponse;

        return file_get_contents($fileResponse->getFile()->getPathname());
    }
}
