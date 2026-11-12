<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Discipline;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * Exports Word des journaux et du bilan mensuel (§4.2), séparation des deux
 * catégories de personnel et assiduité garantie du chef d'établissement.
 */
class RapportsWordTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $lundi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lundi = Carbon::parse('2026-10-05')->startOfWeek(Carbon::MONDAY);
        Carbon::setTestNow($this->lundi->copy()->addDays(5)->setTime(18, 0));
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

    private function coursLe(Enseignant $enseignant, Carbon $jour): void
    {
        EmploiDuTemps::create([
            'enseignant_id' => $enseignant->id,
            'classe_id' => Classe::factory()->create()->id,
            'discipline_id' => Discipline::factory()->create()->id,
            'jour' => $jour->isoWeekday(),
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
        ]);
    }

    /** Le .docx est un paquet OOXML : on relit son `word/document.xml`. */
    private function documentXml(string $contenu): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'test').'.docx';
        file_put_contents($chemin, $contenu);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($chemin) === true, 'Le fichier produit n’est pas une archive .docx lisible.');
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($chemin);

        $this->assertIsString($xml, 'Le document Word ne contient pas word/document.xml.');

        return $xml;
    }

    public function test_le_journal_du_jour_sexporte_en_word(): void
    {
        $enseignant = Enseignant::factory()->create(['nom' => 'MBALLA JEAN', 'section' => 'Industrielle']);
        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => $this->lundi->toDateString(),
            'heure_arrivee' => $this->lundi->copy()->setTime(7, 42),
            'source' => 'app_mobile',
        ]);
        $this->actingAsBackoffice();

        $reponse = $this->get('/api/assiduite/journal/word?categorie=enseignant&date='.$this->lundi->toDateString())
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $xml = $this->documentXml($reponse->streamedContent());

        $this->assertStringContainsString('MBALLA JEAN', $xml);
        $this->assertStringContainsString('07:42', $xml);
        $this->assertStringContainsString('Personnel enseignant', $xml);
    }

    public function test_un_export_sans_categorie_est_refuse(): void
    {
        $this->actingAsBackoffice();

        // Sans catégorie, le document mélangerait enseignants et
        // administratifs : c'est précisément ce que la séparation interdit.
        $this->getJson('/api/assiduite/journal/word')->assertStatus(422);
        $this->getJson('/api/assiduite/journal-hebdomadaire/word')->assertStatus(422);
        $this->getJson('/api/assiduite/bilan-mensuel/word')->assertStatus(422);
    }

    public function test_le_journal_word_ne_melange_pas_les_deux_categories(): void
    {
        Enseignant::factory()->create(['nom' => 'ENSEIGNANT TEST', 'section' => 'Industrielle']);
        $administratif = Enseignant::factory()->create(['nom' => 'SECRETAIRE TEST', 'section' => 'Administration']);
        foreach ([Enseignant::query()->where('nom', 'ENSEIGNANT TEST')->first(), $administratif] as $personne) {
            Presence::create([
                'enseignant_id' => $personne->id,
                'date' => $this->lundi->toDateString(),
                'heure_arrivee' => $this->lundi->copy()->setTime(7, 30),
                'source' => 'app_mobile',
            ]);
        }
        $this->actingAsBackoffice();

        $enseignants = $this->documentXml(
            $this->get('/api/assiduite/journal/word?categorie=enseignant&date='.$this->lundi->toDateString())
                ->assertOk()->streamedContent(),
        );
        $administration = $this->documentXml(
            $this->get('/api/assiduite/journal/word?categorie=administration&date='.$this->lundi->toDateString())
                ->assertOk()->streamedContent(),
        );

        $this->assertStringContainsString('ENSEIGNANT TEST', $enseignants);
        $this->assertStringNotContainsString('SECRETAIRE TEST', $enseignants);
        $this->assertStringContainsString('SECRETAIRE TEST', $administration);
        $this->assertStringNotContainsString('ENSEIGNANT TEST', $administration);
    }

    public function test_le_journal_hebdomadaire_sexporte_en_word_en_paysage(): void
    {
        $enseignant = Enseignant::factory()->create(['nom' => 'ABSENT DU LUNDI']);
        $this->coursLe($enseignant, $this->lundi);
        $this->actingAsBackoffice();

        $reponse = $this->get('/api/assiduite/journal-hebdomadaire/word?categorie=enseignant&semaine='.$this->lundi->toDateString())
            ->assertOk();
        $xml = $this->documentXml($reponse->streamedContent());

        $this->assertStringContainsString('w:orient="landscape"', $xml);
        $this->assertStringContainsString('ABSENT DU LUNDI', $xml);
        $this->assertStringContainsString('ABS', $xml);
    }

    public function test_le_bilan_mensuel_est_groupe_par_section(): void
    {
        $industriel = Enseignant::factory()->create(['nom' => 'PROF INDUS', 'section' => 'Industrielle']);
        $commercial = Enseignant::factory()->create(['nom' => 'PROF COM', 'section' => 'Commerciale']);
        $this->coursLe($industriel, $this->lundi);
        $this->coursLe($commercial, $this->lundi);
        Presence::create([
            'enseignant_id' => $industriel->id,
            'date' => $this->lundi->toDateString(),
            'heure_arrivee' => $this->lundi->copy()->setTime(7, 50),
            'source' => 'app_mobile',
        ]);
        $this->actingAsBackoffice();

        $bilan = $this->getJson('/api/assiduite/bilan-mensuel?mois=2026-10&categorie=enseignant')
            ->assertOk()
            ->json();

        $sections = collect($bilan['sections']);
        $this->assertSame(['Commerciale', 'Industrielle'], $sections->pluck('section')->all());
        $this->assertEqualsWithDelta(100.0, $sections->firstWhere('section', 'Industrielle')['taux_assiduite'], 0.01);
        $this->assertEqualsWithDelta(0.0, $sections->firstWhere('section', 'Commerciale')['taux_assiduite'], 0.01);
        $this->assertSame(2, $bilan['total']['effectif']);

        $xml = $this->documentXml(
            $this->get('/api/assiduite/bilan-mensuel/word?mois=2026-10&categorie=enseignant')->assertOk()->streamedContent(),
        );
        $this->assertStringContainsString('Industrielle', $xml);
        $this->assertStringContainsString('Synth', $xml);
    }

    public function test_le_chef_detablissement_reste_a_cent_pour_cent(): void
    {
        $chef = Enseignant::factory()->create([
            'nom' => 'LE PROVISEUR',
            'section' => 'Industrielle',
            'fonction' => 'Chef d’établissement',
        ]);
        // Attendu toute la semaine, et aucun pointage : sans la garantie, le
        // taux tomberait à 0 %.
        foreach ([0, 1, 2, 3, 4] as $decalage) {
            $this->coursLe($chef, $this->lundi->copy()->addDays($decalage));
        }
        $this->actingAsBackoffice();

        $stats = collect($this->getJson('/api/assiduite/stats?debut=2026-10-01&fin=2026-10-31')->assertOk()->json())
            ->firstWhere('nom', 'LE PROVISEUR');
        $this->assertEqualsWithDelta(100.0, $stats['taux_assiduite'], 0.01);
        $this->assertTrue($stats['assiduite_garantie']);

        $ligne = collect($this->getJson('/api/assiduite/journal-hebdomadaire?semaine='.$this->lundi->toDateString())
            ->assertOk()->json('lignes'))->firstWhere('nom', 'LE PROVISEUR');
        $this->assertEqualsWithDelta(100.0, $ligne['taux_assiduite'], 0.01);
        $this->assertSame($ligne['jours_attendus'], $ligne['jours_presents']);

        $xml = $this->documentXml(
            $this->get('/api/assiduite/journal-hebdomadaire/word?categorie=enseignant&semaine='.$this->lundi->toDateString())
                ->assertOk()->streamedContent(),
        );
        // La légende du document mentionne « ABS » : on vérifie l'absence de
        // cellule portant ce texte, pas celle du mot dans la page.
        $this->assertStringNotContainsString('preserve">ABS<', $xml);
        $this->assertStringContainsString('preserve">OK<', $xml);
    }

    /**
     * Les trois bilans mensuels : le global porte les deux volets, les deux
     * autres n'en gardent qu'un. Mélanger assiduité et ponctualité dans tous
     * les documents, c'était faire circuler un classement nominatif dans des
     * réunions où seule l'assiduité est à l'ordre du jour.
     */
    public function test_les_trois_bilans_mensuels_sexportent_en_word(): void
    {
        $enseignant = Enseignant::factory()->create(['nom' => 'MBALLA JEAN', 'section' => 'Industrielle']);
        $this->coursLe($enseignant, $this->lundi);
        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => $this->lundi->toDateString(),
            'heure_arrivee' => $this->lundi->copy()->setTime(8, 25),
            'source' => 'app_mobile',
        ]);
        $this->actingAsBackoffice();

        $mois = $this->lundi->format('Y-m');
        $url = fn (string $volet) => "/api/assiduite/bilan-mensuel/word?categorie=enseignant&mois={$mois}&volet={$volet}";

        $global = $this->documentXml($this->get($url('global'))->assertOk()->streamedContent());
        $assiduite = $this->documentXml($this->get($url('assiduite'))->assertOk()->streamedContent());
        $ponctualite = $this->documentXml($this->get($url('ponctualite'))->assertOk()->streamedContent());

        $this->assertStringContainsString('Synthèse par section', $global);
        $this->assertStringContainsString('Classement de ponctualité', $global);

        $this->assertStringContainsString('Synthèse par section', $assiduite);
        $this->assertStringNotContainsString('Classement de ponctualité', $assiduite);

        $this->assertStringContainsString('Classement de ponctualité', $ponctualite);
        $this->assertStringNotContainsString('Synthèse par section', $ponctualite);
        $this->assertStringContainsString('MBALLA JEAN', $ponctualite);
    }

    public function test_les_trois_bilans_mensuels_sexportent_en_pdf(): void
    {
        $enseignant = Enseignant::factory()->create(['nom' => 'MBALLA JEAN', 'section' => 'Industrielle']);
        $this->coursLe($enseignant, $this->lundi);
        $this->actingAsBackoffice();

        $mois = $this->lundi->format('Y-m');

        foreach (['global', 'assiduite', 'ponctualite'] as $volet) {
            $reponse = $this->get("/api/assiduite/bilan-mensuel/pdf?categorie=enseignant&mois={$mois}&volet={$volet}");

            $reponse->assertOk();
            $this->assertSame('application/pdf', $reponse->headers->get('content-type'));
        }
    }

    public function test_un_volet_inconnu_est_refuse(): void
    {
        $this->actingAsBackoffice();

        $this->getJson('/api/assiduite/bilan-mensuel/word?categorie=enseignant&volet=salaires')
            ->assertStatus(422)
            ->assertJsonValidationErrors('volet');
    }
}
