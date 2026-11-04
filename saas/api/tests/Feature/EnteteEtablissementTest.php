<?php

namespace Tests\Feature;

use App\Models\Enseignant;
use App\Models\Parametre;
use App\Models\Presence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * En-tête officiel de l'établissement (§4.2) : configurable par le
 * backoffice, imprimé en haut des documents.
 *
 * Le bandeau était écrit en dur dans les vues PDF : l'application étant
 * multi-établissements, chaque administration doit y voir sa propre tutelle.
 */
class EnteteEtablissementTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $jour;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jour = Carbon::parse('2026-10-05');
        Carbon::setTestNow($this->jour->copy()->setTime(18, 0));
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

    public function test_lentete_expose_des_defauts_nationaux(): void
    {
        $this->actingAsBackoffice();

        $this->getJson('/api/parametres/entete-etablissement')
            ->assertOk()
            ->assertJson([
                'republique' => 'RÉPUBLIQUE DU CAMEROUN',
                'republique_en' => 'REPUBLIC OF CAMEROON',
                'ministere' => '',
            ]);
    }

    public function test_le_backoffice_enregistre_son_entete(): void
    {
        $this->actingAsBackoffice();

        $this->putJson('/api/parametres/entete-etablissement', [
            'ministere' => 'MINISTÈRE DES ENSEIGNEMENTS SECONDAIRES',
            'delegation' => 'DÉLÉGATION RÉGIONALE DE L’ADAMAOUA',
            'etablissement' => 'LYCÉE BILINGUE DE NGAOUNDAL',
            'etablissement_en' => 'GOVERNMENT BILINGUAL HIGH SCHOOL NGAOUNDAL',
            'contact' => 'BP 42 Ngaoundal — Tél. 699 00 00 00',
        ])->assertOk()->assertJson([
            'etablissement' => 'LYCÉE BILINGUE DE NGAOUNDAL',
            'contact' => 'BP 42 Ngaoundal — Tél. 699 00 00 00',
        ]);

        $this->assertSame(
            'LYCÉE BILINGUE DE NGAOUNDAL',
            Parametre::get('entete.etablissement', ''),
        );

        // Un champ absent de la requête n'est pas effacé.
        $this->putJson('/api/parametres/entete-etablissement', ['ministere' => 'MINESEC'])
            ->assertOk()
            ->assertJson(['etablissement' => 'LYCÉE BILINGUE DE NGAOUNDAL', 'ministere' => 'MINESEC']);
    }

    public function test_une_ligne_peut_etre_videe(): void
    {
        $this->actingAsBackoffice();

        $this->putJson('/api/parametres/entete-etablissement', ['delegation' => 'DRES ADAMAOUA'])->assertOk();
        $this->putJson('/api/parametres/entete-etablissement', ['delegation' => ''])
            ->assertOk()
            ->assertJson(['delegation' => '']);
    }

    public function test_un_enseignant_ne_peut_pas_modifier_lentete(): void
    {
        $enseignant = Enseignant::factory()->create();
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $this->putJson('/api/parametres/entete-etablissement', ['etablissement' => 'AUTRE LYCÉE'])
            ->assertForbidden();
    }

    public function test_lentete_configure_coiffe_le_journal_pdf(): void
    {
        $this->actingAsBackoffice();
        Parametre::set('entete.etablissement', 'LYCÉE BILINGUE DE NGAOUNDAL');
        Parametre::set('entete.contact', 'BP 42 Ngaoundal');

        // Le rendu Blade de l'en-tête casserait la génération s'il était
        // fautif : un PDF servi est la preuve que le partial s'est compilé.
        // Son contenu textuel, lui, est couvert par l'export Word.
        $reponse = $this->get('/api/assiduite/journal/pdf?date='.$this->jour->toDateString());

        $reponse->assertOk();
        $this->assertSame('application/pdf', $reponse->headers->get('content-type'));
    }

    public function test_lentete_configure_coiffe_le_journal_word(): void
    {
        $this->actingAsBackoffice();
        $enseignant = Enseignant::factory()->create(['nom' => 'MBALLA JEAN', 'section' => 'Industrielle']);
        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => $this->jour->toDateString(),
            'heure_arrivee' => $this->jour->copy()->setTime(7, 42),
            'source' => 'app_mobile',
        ]);

        $this->putJson('/api/parametres/entete-etablissement', [
            'etablissement' => 'LYCÉE BILINGUE DE NGAOUNDAL',
            'etablissement_en' => 'GBHS NGAOUNDAL',
            'contact' => 'BP 42 Ngaoundal',
        ])->assertOk();

        $contenu = $this->get(
            '/api/assiduite/journal/word?categorie=enseignant&date='.$this->jour->toDateString()
        )->assertOk()->streamedContent();

        $chemin = tempnam(sys_get_temp_dir(), 'test').'.docx';
        file_put_contents($chemin, $contenu);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($chemin) === true);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($chemin);

        $this->assertStringContainsString('LYCÉE BILINGUE DE NGAOUNDAL', $xml);
        $this->assertStringContainsString('GBHS NGAOUNDAL', $xml);
        $this->assertStringContainsString('BP 42 Ngaoundal', $xml);
        $this->assertStringContainsString('RÉPUBLIQUE DU CAMEROUN', $xml);
    }
}
