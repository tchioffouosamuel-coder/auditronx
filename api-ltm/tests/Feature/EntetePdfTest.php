<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tout document généré porte l'entête officiel de l'établissement (§4.4).
 * Quatre des six vues PDF sortaient sans entête, et les deux autres le
 * recopiaient en dur : ces tests verrouillent l'inclusion du partiel partagé
 * et le fait que ses libellés viennent bien de la configuration.
 */
class EntetePdfTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Les fournisseurs de données PHPUnit tournent avant l'amorçage de
     * l'application : on ne peut pas s'appuyer sur `resource_path()` ici.
     *
     * @return list<array{string}>
     */
    public static function vuesPdf(): array
    {
        return array_map(
            fn (string $chemin) => [basename($chemin, '.blade.php')],
            glob(__DIR__.'/../../resources/views/pdf/*.blade.php'),
        );
    }

    #[DataProvider('vuesPdf')]
    public function test_chaque_vue_pdf_inclut_l_entete(string $vue): void
    {
        $this->assertStringContainsString(
            "@include('pdf.partials.entete')",
            file_get_contents(resource_path("views/pdf/{$vue}.blade.php")),
            "La vue pdf.{$vue} doit imprimer l'entête de l'établissement.",
        );
    }

    public function test_l_entete_reprend_l_identite_configuree(): void
    {
        config([
            'etablissement.pays' => 'REPUBLIQUE DU TCHAD',
            'etablissement.devise' => 'Unité - Travail - Progrès',
            'etablissement.nom' => 'LYCEE DE MOUNDOU',
            'etablissement.nom_en' => 'MOUNDOU HIGH SCHOOL',
        ]);

        $html = view('pdf.partials.entete')->render();

        $this->assertStringContainsString('REPUBLIQUE DU TCHAD', $html);
        $this->assertStringContainsString('Unité - Travail - Progrès', $html);
        $this->assertStringContainsString('LYCEE DE MOUNDOU', $html);
        $this->assertStringContainsString('MOUNDOU HIGH SCHOOL', $html);
        $this->assertStringNotContainsString('MEIGANGA', $html);
    }

    public function test_l_identite_de_l_etablissement_est_exposee_a_l_api(): void
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        config(['etablissement.nom' => 'LYCEE DE MOUNDOU']);

        $this->get('/api/parametres/etablissement')
            ->assertOk()
            ->assertJsonPath('nom', 'LYCEE DE MOUNDOU')
            ->assertJsonStructure(['pays', 'devise', 'nom', 'pays_en', 'devise_en', 'nom_en', 'logo_url']);
    }

    public function test_l_entete_s_imprime_sans_logo_quand_le_fichier_manque(): void
    {
        config(['etablissement.logo' => 'logo-absent-de-ce-depot.png']);

        $html = view('pdf.partials.entete')->render();

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString(config('etablissement.nom'), $html);
    }
}
