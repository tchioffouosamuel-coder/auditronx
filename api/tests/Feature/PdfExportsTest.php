<?php

namespace Tests\Feature;

use App\Models\Enseignant;
use App\Models\User;
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
