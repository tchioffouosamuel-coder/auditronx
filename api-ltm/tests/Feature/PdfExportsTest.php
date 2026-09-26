<?php

namespace Tests\Feature;

use App\Models\Enseignant;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfExportsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsBackoffice(): User
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    public function test_le_bilan_cumule_pdf_se_telecharge(): void
    {
        $this->actingAsBackoffice();
        Enseignant::factory()->count(2)->create();

        $response = $this->get('/api/retards/bilan-cumule');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_le_bilan_cumule_trie_les_enseignants_et_exclut_l_administration(): void
    {
        $this->actingAsBackoffice();
        Enseignant::factory()->create(['nom' => 'Zulu Enseignant', 'section' => 'Sciences']);
        Enseignant::factory()->create(['nom' => 'Administration', 'section' => 'aDmInIsTrAtIoN']);
        Enseignant::factory()->create(['nom' => 'Alpha Enseignant', 'section' => 'Lettres']);

        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('download')->once()->andReturn(response()->noContent());
        Pdf::shouldReceive('loadView')
            ->once()
            ->with('pdf.retards-cumule', \Mockery::on(function (array $viewData): bool {
                $this->assertSame(['Alpha Enseignant', 'Zulu Enseignant'], array_column($viewData['data'], 'nom'));
                $this->assertArrayHasKey('taux_assiduite', $viewData['data'][0]);

                $html = view('pdf.retards-cumule', $viewData)->render();
                $totalPosition = strpos($html, 'TOTAL<br>Périodes');
                $ratePosition = strpos($html, "Taux<br>d'assiduité");
                $this->assertNotFalse($totalPosition);
                $this->assertNotFalse($ratePosition);
                $this->assertLessThan($ratePosition, $totalPosition);
                $this->assertSame(count($viewData['data']) + 2, substr_count($html, 'class="total-col"'));
                $this->assertSame(1, preg_match('/<th rowspan="2" class="assiduite-col">Taux<br>d.assiduité<\/th>\s*<\/tr>/', $html));
                $this->assertStringContainsString("Bilan mensuel d'assiduité et de ponctualité", $html);

                return true;
            }))
            ->andReturn($pdf);

        $response = $this->get('/api/retards/bilan-cumule');

        $response->assertNoContent();
    }

    public function test_le_journal_des_presences_pdf_se_telecharge(): void
    {
        $this->actingAsBackoffice();

        $response = $this->get('/api/assiduite/journal/pdf?date=2026-09-26');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_le_bilan_individuel_pdf_se_telecharge(): void
    {
        $this->actingAsBackoffice();
        $enseignant = Enseignant::factory()->create();

        $response = $this->get("/api/retards/bilan/{$enseignant->id}");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_lexport_zip_se_telecharge(): void
    {
        $this->actingAsBackoffice();
        Enseignant::factory()->count(2)->create();

        $response = $this->get('/api/statistiques/export-zip');

        $response->assertOk();
        $this->assertSame('application/zip', $response->headers->get('content-type'));
    }
}
