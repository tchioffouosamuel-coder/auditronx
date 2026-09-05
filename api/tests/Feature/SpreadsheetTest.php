<?php

namespace Tests\Feature;

use App\Exports\ArrayExport;
use App\Models\Classe;
use App\Models\Discipline;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel as ExcelWriterType;
use Maatwebsite\Excel\Facades\Excel;
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
}
