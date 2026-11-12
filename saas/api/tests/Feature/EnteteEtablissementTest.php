<?php

namespace Tests\Feature;

use App\Models\Enseignant;
use App\Models\Parametre;
use App\Models\Presence;
use App\Models\User;
use App\Services\EnteteEtablissement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        // Le logo s'écrit dans le stockage public de l'établissement : sans
        // ce ménage, un test en laisserait un pour les suivants.
        Storage::disk(EnteteEtablissement::LOGO_DISQUE)->deleteDirectory('entete');
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

    /** Un PNG minimal valide, à envoyer comme logo. */
    private function logoPng(): UploadedFile
    {
        $image = imagecreatetruecolor(60, 30);
        $chemin = tempnam(sys_get_temp_dir(), 'logo').'.png';
        imagepng($image, $chemin);
        imagedestroy($image);

        return new UploadedFile($chemin, 'logo.png', 'image/png', null, true);
    }

    public function test_le_backoffice_envoie_et_retire_le_logo(): void
    {
        $this->actingAsBackoffice();

        $url = $this->post('/api/parametres/entete-etablissement/logo', ['logo' => $this->logoPng()])
            ->assertOk()
            ->json('logo_url');

        $this->assertIsString($url);
        $this->assertStringContainsString('entete/logo.png', $url);
        $this->assertTrue(Storage::disk(EnteteEtablissement::LOGO_DISQUE)->exists('entete/logo.png'));

        // L'en-tête le renvoie aux écrans de configuration, qui l'affichent.
        $this->getJson('/api/parametres/entete-etablissement')->assertOk()->assertJsonPath(
            'logo_url',
            fn ($valeur) => is_string($valeur) && str_contains($valeur, 'entete/logo.png'),
        );

        $this->deleteJson('/api/parametres/entete-etablissement/logo')
            ->assertOk()
            ->assertJson(['logo_url' => null]);

        $this->assertFalse(Storage::disk(EnteteEtablissement::LOGO_DISQUE)->exists('entete/logo.png'));
    }

    public function test_un_fichier_qui_nest_pas_une_image_est_refuse(): void
    {
        $this->actingAsBackoffice();

        $this->post('/api/parametres/entete-etablissement/logo', [
            'logo' => UploadedFile::fake()->create('liste.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('logo');
    }

    public function test_un_enseignant_ne_peut_pas_changer_le_logo(): void
    {
        $enseignant = Enseignant::factory()->create();
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $this->post('/api/parametres/entete-etablissement/logo', ['logo' => $this->logoPng()])
            ->assertForbidden();
    }

    public function test_le_logo_est_embarque_dans_le_document_word(): void
    {
        $this->actingAsBackoffice();
        $enseignant = Enseignant::factory()->create(['nom' => 'MBALLA JEAN', 'section' => 'Industrielle']);
        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => $this->jour->toDateString(),
            'heure_arrivee' => $this->jour->copy()->setTime(7, 42),
            'source' => 'app_mobile',
        ]);

        $this->post('/api/parametres/entete-etablissement/logo', ['logo' => $this->logoPng()])->assertOk();

        $contenu = $this->get(
            '/api/assiduite/journal/word?categorie=enseignant&date='.$this->jour->toDateString()
        )->assertOk()->streamedContent();

        $chemin = tempnam(sys_get_temp_dir(), 'test').'.docx';
        file_put_contents($chemin, $contenu);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($chemin) === true);
        $media = $zip->getFromName('word/media/logo.png');
        $relations = (string) $zip->getFromName('word/_rels/document.xml.rels');
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($chemin);

        // Image embarquée, reliée au document et effectivement dessinée : un
        // des trois manquant, Word ouvre un cadre vide.
        $this->assertIsString($media);
        $this->assertStringContainsString('media/logo.png', $relations);
        $this->assertStringContainsString('<w:drawing>', $xml);
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
