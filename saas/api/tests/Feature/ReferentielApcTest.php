<?php

namespace Tests\Feature;

use App\Models\Departement;
use App\Models\Enseignant;
use App\Models\Module;
use App\Models\Specialite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Référentiel APC : départements, spécialités, modules, et l'import du
 * classeur d'organisation pédagogique.
 *
 * Le classeur de test n'est pas fabriqué pour l'occasion : c'est le fichier
 * réellement diffusé (`tests/Fixtures/referentiel-apc-op.xlsx`, quatre
 * spécialités SH / F2 / MVT / F6-MIPE), avec ses irrégularités — deux modules
 * sur une même ligne, intitulés répétés, onglets sans aucun code, deux lignes
 * TOTAL qui se contredisent, un écart d'heures dans l'onglet de contrôle.
 *
 * C'est le point du lot : ces anomalies doivent **ressortir nommées**, et non
 * être arbitrées en silence. Les tests ci-dessous les épinglent une par une.
 */
class ReferentielApcTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsBackoffice(): User
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    private function classeurOfficiel(): UploadedFile
    {
        return new UploadedFile(
            base_path('tests/Fixtures/referentiel-apc-op.xlsx'),
            'referentiel-apc-op.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            test: true,
        );
    }

    /** @return array<string, mixed> */
    private function importe(array $extra = []): array
    {
        return $this->post('/api/spreadsheet/referentiel/import', [
            'file' => $this->classeurOfficiel(),
            ...$extra,
        ])->assertOk()->json();
    }

    /* ------------------------------------------------------------------ */
    /* Import du classeur réel                                             */
    /* ------------------------------------------------------------------ */

    public function test_le_classeur_de_lorganisation_pedagogique_cree_les_quatre_specialites(): void
    {
        $this->actingAsBackoffice();

        $reponse = $this->importe();

        $this->assertTrue($reponse['applique']);
        $this->assertSame(['SH', 'F2', 'MVT', 'F6-MIPE'], array_column($reponse['specialites'], 'code'));

        // Le nom d'onglet ne dit pas de quelle formation il s'agit : la
        // spécialité naît avec son code pour libellé, à compléter à l'écran.
        $this->assertSame('F2', Specialite::where('code', 'F2')->value('nom'));
        $this->assertNull(Specialite::where('code', 'F2')->value('departement_id'));
    }

    public function test_les_modules_sont_ranges_dans_le_bloc_de_leur_colonne(): void
    {
        $this->actingAsBackoffice();
        $this->importe();

        $sh = Specialite::where('code', 'SH')->firstOrFail();

        $parBloc = Module::where('specialite_id', $sh->id)
            ->get()
            ->groupBy('bloc')
            ->map->count();

        $this->assertSame(3, $parBloc[Module::BLOC_COMPETENCES_PARTICULIERES]);
        $this->assertSame(6, $parBloc[Module::BLOC_COMPETENCES_GENERALES]);
        $this->assertSame(5, $parBloc[Module::BLOC_ENSEIGNEMENTS_GENERAUX]);
        $this->assertSame(2, $parBloc[Module::BLOC_ENSEIGNEMENTS_COMPLEMENTAIRES]);
    }

    public function test_le_code_est_extrait_de_la_fin_de_lintitule(): void
    {
        $this->actingAsBackoffice();
        $this->importe();

        $f2 = Specialite::where('code', 'F2')->firstOrFail();

        // Deux parenthèses : seule la dernière est un code, le sigle reste
        // dans l'intitulé.
        $qhse = Module::where('specialite_id', $f2->id)->where('code', 'C2')->firstOrFail();
        $this->assertSame('Qualité, Hygiène, Sécurité et Environnement (QHSE)', $qhse->intitule);

        $mesures = Module::where('specialite_id', $f2->id)->where('code', 'C7')->firstOrFail();
        $this->assertSame('Instrumentation et mesures électroniques', $mesures->intitule);
        $this->assertSame(Module::BLOC_COMPETENCES_PARTICULIERES, $mesures->bloc);
        $this->assertSame(75, $mesures->volume_horaire);
    }

    /**
     * Le niveau n'est écrit nulle part ailleurs que dans la ligne de total —
     * et cette ligne ne doit évidemment pas devenir un module.
     */
    public function test_la_ligne_total_donne_le_niveau_sans_produire_de_module(): void
    {
        $this->actingAsBackoffice();
        $this->importe();

        $this->assertSame(['Seconde'], Module::query()->distinct()->pluck('niveau')->all());
        $this->assertSame(0, Module::where('intitule', 'like', 'TOTAL%')->count());
    }

    /* ------------------------------------------------------------------ */
    /* Les anomalies du classeur, une par une                              */
    /* ------------------------------------------------------------------ */

    /**
     * Onglet F2, ligne 5 : la colonne des compétences particulières porte
     * « … (C10) » et celle des compétences générales « Métier et formation
     * (C1) », pour 75 h uniques. Rien ne dit à qui elles vont.
     */
    public function test_deux_modules_sur_une_ligne_sont_importes_sans_volume_horaire(): void
    {
        $this->actingAsBackoffice();

        $reponse = $this->importe();

        $ambigus = $this->avertissements($reponse, 'F2', 5);
        $this->assertCount(1, $ambigus);
        $this->assertStringContainsString('deux blocs renseignés sur la même ligne', $ambigus[0]['avertissement']);
        $this->assertStringContainsString('75 h', $ambigus[0]['avertissement']);

        $f2 = Specialite::where('code', 'F2')->firstOrFail();
        $this->assertNull(Module::where('specialite_id', $f2->id)->where('code', 'C10')->value('volume_horaire'));
        $this->assertNull(Module::where('specialite_id', $f2->id)->where('code', 'C1')->value('volume_horaire'));
    }

    /**
     * Même onglet, ligne 6 : « Métier et formation (C1) » est répété, avec un
     * autre volume horaire. La seconde occurrence est rejetée en nommant la
     * première.
     */
    public function test_un_code_repete_dans_un_onglet_est_rejete_en_citant_la_premiere_ligne(): void
    {
        $this->actingAsBackoffice();

        $reponse = $this->importe();

        $erreurs = $this->erreurs($reponse, 'F2', 6);
        $this->assertCount(1, $erreurs);
        $this->assertStringContainsString('C1', $erreurs[0]['erreur']);
        $this->assertStringContainsString('ligne 5', $erreurs[0]['erreur']);

        $f2 = Specialite::where('code', 'F2')->firstOrFail();
        $this->assertSame(1, Module::where('specialite_id', $f2->id)->where('code', 'C1')->count());
    }

    /**
     * Onglet MVT : « Français » apparaît lignes 11 et 12, et « Éducation
     * Physique et Sportive » lignes 16 et 17. Aucun des deux n'a de code —
     * c'est donc le code fabriqué qui révèle le doublon.
     */
    public function test_un_intitule_repete_sans_code_est_detecte_par_son_code_fabrique(): void
    {
        $this->actingAsBackoffice();

        $reponse = $this->importe();

        $this->assertCount(1, $this->erreurs($reponse, 'MVT', 12));
        $this->assertCount(1, $this->erreurs($reponse, 'MVT', 17));

        $mvt = Specialite::where('code', 'MVT')->firstOrFail();
        $this->assertSame(1, Module::where('specialite_id', $mvt->id)->where('intitule', 'Français')->count());
    }

    /** Onglet MVT : aucun module n'est codé dans le document. */
    public function test_un_module_sans_code_recoit_un_code_fabrique_signale(): void
    {
        $this->actingAsBackoffice();

        $reponse = $this->importe();

        $mvt = collect($reponse['specialites'])->firstWhere('code', 'MVT');
        $this->assertSame($mvt['modules'], $mvt['codes_fabriques'], 'aucun code dans cet onglet');

        $specialite = Specialite::where('code', 'MVT')->firstOrFail();
        $francais = Module::where('specialite_id', $specialite->id)->where('intitule', 'Français')->firstOrFail();
        $this->assertSame('EGEN-FRANCAIS', $francais->code);

        $fabriques = $this->avertissements($reponse, 'MVT', 12);
        $this->assertSame([], $fabriques, 'la ligne 12 est rejetée, pas signalée comme code fabriqué');
    }

    /**
     * Onglet F6-MIPE : l'organisation pédagogique annonce 990 h, mais les
     * lignes de l'onglet en totalisent 1005. L'import n'arbitre pas — il le
     * dit.
     */
    public function test_lecart_dheures_de_longlet_controle_est_remonte(): void
    {
        $this->actingAsBackoffice();

        $reponse = $this->importe();

        $controles = collect($reponse['controles'])->keyBy('specialite');

        $this->assertSame(0, $controles['SH']['ecart']);
        $this->assertSame(0, $controles['F2']['ecart']);
        $this->assertSame(0, $controles['MVT']['ecart']);

        $this->assertSame(1005, $controles['F6-MIPE']['total_calcule']);
        $this->assertSame(990, $controles['F6-MIPE']['total_annonce']);
        $this->assertSame(15, $controles['F6-MIPE']['ecart']);
    }

    /** L'onglet « Contrôle » n'est pas une spécialité. */
    public function test_longlet_de_controle_ne_devient_pas_une_specialite(): void
    {
        $this->actingAsBackoffice();
        $this->importe();

        $this->assertSame(4, Specialite::count());
        $this->assertNull(Specialite::where('code', 'CONTRÔLE')->first());
    }

    /** Les totaux d'heures lus doivent être ceux des lignes du document. */
    public function test_les_totaux_dheures_lus_sont_ceux_du_document(): void
    {
        $this->actingAsBackoffice();

        $totaux = collect($this->importe()['specialites'])->pluck('total_volume_horaire', 'code');

        $this->assertSame(915, $totaux['SH']);
        $this->assertSame(900, $totaux['F2']);
        $this->assertSame(945, $totaux['MVT']);
        $this->assertSame(1005, $totaux['F6-MIPE']);
    }

    /* ------------------------------------------------------------------ */
    /* Aperçu, idempotence, aller-retour                                   */
    /* ------------------------------------------------------------------ */

    public function test_lapercu_rend_le_meme_diagnostic_sans_rien_ecrire(): void
    {
        $this->actingAsBackoffice();

        $apercu = $this->importe(['apercu' => 1]);

        $this->assertFalse($apercu['applique']);
        $this->assertCount(4, $apercu['specialites']);
        $this->assertNotEmpty($apercu['erreurs']);
        $this->assertSame(0, Specialite::count());
        $this->assertSame(0, Module::count());
    }

    /**
     * Un classeur réimporté ne duplique rien : la clé naturelle est
     * (spécialité, niveau, code).
     */
    public function test_un_reimport_met_a_jour_sans_dupliquer(): void
    {
        $this->actingAsBackoffice();

        $this->importe();
        $modules = Module::count();
        $this->assertGreaterThan(0, $modules);

        $second = $this->importe();

        $this->assertSame($modules, Module::count());
        $this->assertSame(4, Specialite::count());
        $this->assertSame(0, $second['bilan']['modules_crees']);
        $this->assertSame($modules, $second['bilan']['modules_mis_a_jour']);
    }

    /**
     * Un crédit saisi à la main — le classeur n'en porte pas — survit au
     * réimport du même document.
     */
    public function test_un_reimport_ne_perd_pas_les_credits_saisis(): void
    {
        $this->actingAsBackoffice();
        $this->importe();

        $module = Module::where('code', 'C2')->firstOrFail();
        $module->update(['credit' => 9]);

        $this->importe();

        $this->assertSame(9, $module->fresh()->credit);
    }

    /**
     * L'export reprend la disposition du document : réimporté, il rend le
     * même référentiel. C'est ce qui permet de corriger un classeur hors
     * ligne sans passer par une conversion manuelle.
     */
    public function test_le_classeur_exporte_se_reimporte_a_lidentique(): void
    {
        $this->actingAsBackoffice();
        $this->importe();

        $avant = Module::orderBy('specialite_id')->orderBy('code')
            ->get(['specialite_id', 'niveau', 'code', 'intitule', 'bloc', 'volume_horaire'])
            ->toArray();

        $binaire = $this->get('/api/spreadsheet/referentiel/export')->assertOk()->streamedContent();

        $reponse = $this->post('/api/spreadsheet/referentiel/import', [
            'file' => UploadedFile::fake()->createWithContent('referentiel.xlsx', $binaire),
        ])->assertOk()->json();

        $this->assertSame([], $reponse['erreurs'], 'un export propre ne doit plus porter d\'anomalie');

        $apres = Module::orderBy('specialite_id')->orderBy('code')
            ->get(['specialite_id', 'niveau', 'code', 'intitule', 'bloc', 'volume_horaire'])
            ->toArray();

        $this->assertEquals($avant, $apres);
    }

    public function test_le_modele_vierge_porte_les_entetes_de_bloc(): void
    {
        $this->actingAsBackoffice();

        $binaire = $this->get('/api/spreadsheet/referentiel/template')->assertOk()->streamedContent();

        // Le modèle vierge n'a aucun module : il doit être refusé proprement,
        // pas créer une spécialité « SPECIALITE » vide.
        $reponse = $this->post('/api/spreadsheet/referentiel/import', [
            'file' => UploadedFile::fake()->createWithContent('modele.xlsx', $binaire),
        ])->assertOk()->json();

        $this->assertSame([], $reponse['specialites']);
        $this->assertStringContainsString('aucun module lisible', $reponse['erreurs'][0]['erreur']);
        $this->assertSame(0, Specialite::count());
    }

    /* ------------------------------------------------------------------ */
    /* Structure : départements et spécialités                             */
    /* ------------------------------------------------------------------ */

    public function test_une_specialite_se_rattache_a_un_departement(): void
    {
        $this->actingAsBackoffice();

        $departement = $this->postJson('/api/departements', [
            'code' => 'GE',
            'nom' => 'Génie Électrique',
        ])->assertCreated()->json();

        $this->importe();

        $specialite = Specialite::where('code', 'F2')->firstOrFail();

        $this->putJson("/api/specialites/{$specialite->id}", [
            'code' => 'F2',
            'nom' => 'Électronique',
            'departement_id' => $departement['id'],
        ])->assertOk()->assertJsonPath('departement.code', 'GE');

        $this->getJson('/api/specialites?departement_id='.$departement['id'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nom', 'Électronique');
    }

    /**
     * Supprimer un département ne doit pas emporter les spécialités : elles
     * se retrouvent simplement sans rattachement.
     */
    public function test_supprimer_un_departement_detache_ses_specialites(): void
    {
        $this->actingAsBackoffice();

        $departement = Departement::create(['code' => 'GM', 'nom' => 'Génie Mécanique']);
        $specialite = Specialite::create(['code' => 'MVT', 'nom' => 'MVT', 'departement_id' => $departement->id]);

        $this->deleteJson("/api/departements/{$departement->id}")->assertNoContent();

        $this->assertNull($specialite->fresh()->departement_id);
        $this->assertNotNull($specialite->fresh());
    }

    /** Supprimer une spécialité emporte son référentiel : un module n'existe pas sans elle. */
    public function test_supprimer_une_specialite_emporte_ses_modules(): void
    {
        $this->actingAsBackoffice();
        $this->importe();

        $specialite = Specialite::where('code', 'SH')->firstOrFail();
        $this->assertGreaterThan(0, Module::where('specialite_id', $specialite->id)->count());

        $this->deleteJson("/api/specialites/{$specialite->id}")->assertNoContent();

        $this->assertSame(0, Module::where('specialite_id', $specialite->id)->count());
    }

    /**
     * Le même code vit dans plusieurs spécialités — « C1 · Métier et
     * formation » est commun à toutes — mais pas deux fois dans la même.
     */
    public function test_un_code_de_module_nest_unique_quau_sein_dune_specialite(): void
    {
        $this->actingAsBackoffice();
        $this->importe();

        $this->assertGreaterThan(1, Module::where('code', 'C1')->count());

        $sh = Specialite::where('code', 'SH')->firstOrFail();

        $this->postJson('/api/modules', [
            'specialite_id' => $sh->id,
            'niveau' => 'Seconde',
            'code' => 'C1',
            'intitule' => 'Doublon',
            'bloc' => Module::BLOC_COMPETENCES_GENERALES,
        ])->assertStatus(422)->assertJsonValidationErrors('code');

        // Même code, autre niveau : accepté.
        $this->postJson('/api/modules', [
            'specialite_id' => $sh->id,
            'niveau' => 'Première',
            'code' => 'C1',
            'intitule' => 'Métier et formation',
            'bloc' => Module::BLOC_COMPETENCES_GENERALES,
        ])->assertCreated();
    }

    public function test_les_modules_se_listent_dans_lordre_du_bulletin(): void
    {
        $this->actingAsBackoffice();
        $this->importe();

        $sh = Specialite::where('code', 'SH')->firstOrFail();

        $blocs = collect(
            $this->getJson("/api/modules?specialite_id={$sh->id}&niveau=Seconde")->assertOk()->json('data')
        )->pluck('bloc')->unique()->values()->all();

        $this->assertSame([
            Module::BLOC_COMPETENCES_PARTICULIERES,
            Module::BLOC_COMPETENCES_GENERALES,
            Module::BLOC_ENSEIGNEMENTS_GENERAUX,
            Module::BLOC_ENSEIGNEMENTS_COMPLEMENTAIRES,
        ], $blocs);
    }

    /* ------------------------------------------------------------------ */
    /* Périmètre                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Un enseignant lit le référentiel — les écrans de notes en dépendront —
     * mais ne le modifie pas, et n'importe surtout pas un classeur qui
     * réécrirait la maquette d'une filière entière.
     *
     * Le référentiel est posé directement par Eloquent, et non par un import
     * authentifié : le guard Sanctum mémorise l'utilisateur de la première
     * requête d'un test, si bien qu'un second `withToken()` ne change pas
     * d'identité. Un test de périmètre doit donc n'en connaître qu'une.
     */
    public function test_un_enseignant_lit_le_referentiel_mais_ne_lecrit_pas(): void
    {
        $specialite = Specialite::create(['code' => 'F2', 'nom' => 'F2']);
        Module::create([
            'specialite_id' => $specialite->id,
            'niveau' => 'Seconde',
            'code' => 'C1',
            'intitule' => 'Métier et formation',
            'bloc' => Module::BLOC_COMPETENCES_GENERALES,
        ]);

        $enseignant = Enseignant::factory()->create();
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $this->getJson('/api/modules')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/specialites')->assertOk();
        $this->getJson('/api/departements')->assertOk();

        $this->postJson('/api/departements', ['code' => 'X', 'nom' => 'X'])->assertForbidden();
        $this->postJson('/api/modules', [])->assertForbidden();
        $this->putJson("/api/specialites/{$specialite->id}", ['code' => 'F2', 'nom' => 'Piraté'])->assertForbidden();
        $this->post('/api/spreadsheet/referentiel/import', ['file' => $this->classeurOfficiel()])
            ->assertForbidden();

        $this->assertSame(0, Departement::count());
        $this->assertSame('F2', $specialite->fresh()->nom);
    }

    /* ------------------------------------------------------------------ */

    /** @return array<int, array<string, mixed>> */
    private function erreurs(array $reponse, string $feuille, int $ligne): array
    {
        return array_values(array_filter(
            $reponse['erreurs'],
            fn (array $e) => $e['feuille'] === $feuille && $e['ligne'] === $ligne,
        ));
    }

    /** @return array<int, array<string, mixed>> */
    private function avertissements(array $reponse, string $feuille, int $ligne): array
    {
        return array_values(array_filter(
            $reponse['avertissements'],
            fn (array $a) => $a['feuille'] === $feuille && $a['ligne'] === $ligne,
        ));
    }
}
