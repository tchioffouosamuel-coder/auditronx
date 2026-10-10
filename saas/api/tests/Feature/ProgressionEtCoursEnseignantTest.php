<?php

namespace Tests\Feature;

use App\Exports\ArrayExport;
use App\Models\Classe;
use App\Models\Discipline;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Models\ProgressionLecon;
use App\Models\Programme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel as ExcelWriterType;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ProgressionEtCoursEnseignantTest extends TestCase
{
    use RefreshDatabase;

    public function test_importe_les_lignes_d_une_progression_par_classe_et_matiere(): void
    {
        $admin = \App\Models\User::factory()->create();
        $this->withToken($admin->createToken('backoffice')->plainTextToken);
        $classe = Classe::factory()->create(['code' => '1A']);
        $discipline = Discipline::factory()->create(['code' => 'INFO']);
        $binary = Excel::raw(new ArrayExport([
            'annee_scolaire',
            'code_classe',
            'code_discipline',
            'trimestre',
            'semaine_numero',
            'periode',
            'unite_apprentissage',
            'unite_enseignement',
            'theorique',
            'pratique',
            'duree',
            'digitalisee',
            'ordre',
        ], [[
            '2026/2027',
            '1A',
            'INFO',
            1,
            1,
            '09-13 Sept 2026',
            'UA 1',
            'Leçon 1. Introduction',
            1,
            0,
            '2p',
            0,
            1,
        ]]), ExcelWriterType::XLSX);

        $this->post('/api/spreadsheet/progressions/import', [
            'file' => UploadedFile::fake()->createWithContent('progressions.xlsx', $binary),
        ])->assertOk()->assertJson(['importes' => 1, 'erreurs' => []]);

        $this->assertDatabaseHas('progression_lecons', ['unite_enseignement' => 'Leçon 1. Introduction', 'ordre' => 1]);
        $this->assertDatabaseHas('programmes', ['classe_id' => $classe->id, 'discipline_id' => $discipline->id, 'nb_seances_prevues' => 1]);
    }

    public function test_un_enseignant_present_peut_cocher_une_lecon_du_cours_du_jour(): void
    {
        $enseignant = Enseignant::factory()->create();
        $classe = Classe::factory()->create();
        $discipline = Discipline::factory()->create();
        $programme = Programme::create([
            'classe_id' => $classe->id,
            'discipline_id' => $discipline->id,
            'annee_scolaire' => now()->year . '/' . (now()->year + 1),
            'nb_seances_prevues' => 1,
        ]);
        $lecon = ProgressionLecon::create(['programme_id' => $programme->id, 'unite_enseignement' => 'Leçon du jour', 'ordre' => 1]);
        $cours = EmploiDuTemps::create([
            'enseignant_id' => $enseignant->id,
            'classe_id' => $classe->id,
            'discipline_id' => $discipline->id,
            'jour' => now()->isoWeekday(),
            'heure_debut' => '08:00',
            'heure_fin' => '09:00',
        ]);
        Presence::create(['enseignant_id' => $enseignant->id, 'date' => now()->toDateString(), 'heure_arrivee' => now()]);
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $this->getJson('/api/mes-cours-du-jour')->assertOk()->assertJsonPath('present', true)->assertJsonPath('cours.0.lecons.0.faite', false);
        $this->postJson('/api/mes-cours-du-jour/lecon-toggle', [
            'emploi_du_temps_id' => $cours->id,
            'progression_lecon_id' => $lecon->id,
            'date' => now()->toDateString(),
        ])->assertOk()->assertJson(['faite' => true]);
        $this->assertDatabaseHas('lecons_realisees', ['enseignant_id' => $enseignant->id, 'progression_lecon_id' => $lecon->id]);
    }
}
