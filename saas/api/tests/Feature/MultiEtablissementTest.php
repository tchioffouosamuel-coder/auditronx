<?php

namespace Tests\Feature;

use App\Models\Central\AnnuaireEntree;
use App\Models\Central\Etablissement;
use App\Models\Central\PlatformUser;
use App\Models\Enseignant;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Garde-fous de la plateforme multi-établissement : résolution du locataire,
 * isolation des données, abonnement suspendu, frontière entre le portail
 * éditeur et les portails clients.
 *
 * Ce sont les tests qui autorisent à servir plusieurs lycées depuis une seule
 * base de code : sans eux, une fuite d'un établissement vers un autre
 * passerait inaperçue jusqu'à ce qu'un client la constate.
 */
class MultiEtablissementTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_requete_sans_entete_etablissement_est_refusee(): void
    {
        $this->withoutHeader('X-Tenant')
            ->getJson('/api/etablissement')
            ->assertStatus(400)
            ->assertJsonPath('erreur', 'etablissement_absent');
    }

    public function test_un_code_etablissement_inconnu_est_refuse(): void
    {
        $this->withHeader('X-Tenant', 'INCONNU')
            ->getJson('/api/etablissement')
            ->assertStatus(404)
            ->assertJsonPath('erreur', 'etablissement_inconnu');
    }

    public function test_un_etablissement_non_provisionne_est_traite_comme_inconnu(): void
    {
        Etablissement::create([
            'code' => 'ATTENTE',
            'nom' => 'Lycée en cours d’ouverture',
            'statut' => Etablissement::STATUT_EN_ATTENTE,
        ]);

        $this->withHeader('X-Tenant', 'ATTENTE')
            ->getJson('/api/etablissement')
            ->assertStatus(404);
    }

    public function test_un_abonnement_suspendu_renvoie_402_et_laisse_les_donnees_intactes(): void
    {
        $suspendu = $this->etablissementSupplementaire('SUSP');
        $suspendu->update(['statut' => Etablissement::STATUT_SUSPENDU, 'suspendu_le' => now()]);

        app(TenantManager::class)->execute($suspendu, fn () => Enseignant::factory()->create());

        $this->withHeader('X-Tenant', 'SUSP')
            ->getJson('/api/etablissement')
            ->assertStatus(402)
            ->assertJsonPath('erreur', 'abonnement_suspendu');

        // La suspension est commerciale, pas destructive : la fiche est encore
        // là le jour où le client règle.
        $this->assertSame(
            1,
            app(TenantManager::class)->execute($suspendu, fn () => Enseignant::count())
        );
    }

    public function test_le_branding_est_servi_sans_authentification(): void
    {
        $this->getJson('/api/etablissement')
            ->assertOk()
            ->assertJsonPath('data.code', 'TEST')
            ->assertJsonPath('data.nom', 'Établissement de test');
    }

    public function test_les_donnees_de_deux_etablissements_restent_cloisonnees(): void
    {
        $autre = $this->etablissementSupplementaire('LCM');

        $chezTest = Enseignant::factory()->create(['nom' => 'Prof de TEST', 'tel' => '699000001']);

        $chezAutre = app(TenantManager::class)->execute(
            $autre,
            fn () => Enseignant::factory()->create(['nom' => 'Prof de LCM', 'tel' => '699000002'])
        );

        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $noms = array_column($this->getJson('/api/personnel')->assertOk()->json('data'), 'nom');

        $this->assertContains('Prof de TEST', $noms);
        $this->assertNotContains('Prof de LCM', $noms);

        // Les deux fiches portent le même identifiant numérique, chacune dans
        // sa base : c'est l'en-tête, et lui seul, qui désigne laquelle est
        // servie. Un identifiant deviné ne traverse donc pas la frontière.
        $this->assertSame($chezTest->id, $chezAutre->id);

        $this->withHeader('X-Tenant', 'LCM')
            ->getJson('/api/personnel/' . $chezTest->id)
            ->assertOk()
            ->assertJsonPath('nom', 'Prof de LCM');
    }

    public function test_un_token_d_un_etablissement_ne_vaut_rien_chez_un_autre(): void
    {
        $this->etablissementSupplementaire('LCM');

        $direction = User::factory()->create(['password' => 'secret1234']);

        $token = $this->postJson('/api/login', [
            'email' => $direction->email,
            'password' => 'secret1234',
        ])->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/personnel')->assertOk();

        // Même URL, même token, autre en-tête : les tokens vivent dans la base
        // de chaque établissement, donc celui-ci n'existe pas chez LCM.
        // (L'en-tête est posé en dernier : `withHeader` vaut pour toutes les
        // requêtes suivantes du test.)
        // Dans un test, les deux requêtes partagent le conteneur, donc le
        // guard Sanctum garde l'utilisateur déjà résolu — ce qui n'arrive
        // jamais entre deux vraies requêtes HTTP. On l'oublie explicitement
        // pour que le token soit bien revérifié dans la base de LCM.
        app('auth')->forgetGuards();

        $this->withHeader('X-Tenant', 'LCM')
            ->withToken($token)
            ->getJson('/api/personnel')
            ->assertUnauthorized();
    }

    public function test_le_catalogue_public_ne_liste_que_les_etablissements_actifs(): void
    {
        $this->etablissementSupplementaire('LBM', ['nom' => 'Lycée Bilingue']);
        $this->etablissementSupplementaire('OFF', ['nom' => 'Lycée résilié'])
            ->update(['statut' => Etablissement::STATUT_ARCHIVE]);

        $codes = array_column($this->getJson('/api/central/catalogue')->assertOk()->json('data'), 'code');

        $this->assertContains('TEST', $codes);
        $this->assertContains('LBM', $codes);
        $this->assertNotContains('OFF', $codes);
    }

    public function test_l_annuaire_retrouve_l_etablissement_d_un_numero_sans_le_stocker_en_clair(): void
    {
        Enseignant::factory()->create(['tel' => '+237 699 00 11 22']);

        $codes = array_column(
            $this->postJson('/api/central/annuaire/resolve', ['tel' => '699001122'])
                ->assertOk()
                ->json('data'),
            'code'
        );

        $this->assertSame(['TEST'], $codes);

        // Rien d'exploitable en base centrale : ni numéro, ni nom.
        $entrees = AnnuaireEntree::pluck('tel_hash')->all();
        $this->assertCount(1, $entrees);
        $this->assertStringNotContainsString('699001122', implode('', $entrees));
    }

    public function test_le_portail_editeur_est_ferme_aux_comptes_d_etablissement(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/central/etablissements')->assertForbidden();
    }

    public function test_le_support_lit_le_parc_mais_ne_cree_pas_d_etablissement(): void
    {
        Sanctum::actingAs(PlatformUser::create([
            'name' => 'Support',
            'email' => 'support@auditronx.com',
            'password' => 'secret1234',
            'role' => PlatformUser::ROLE_SUPPORT,
        ]));

        $this->getJson('/api/central/etablissements')->assertOk();

        $this->postJson('/api/central/etablissements', [
            'code' => 'NOUVEAU',
            'nom' => 'Lycée Nouveau',
            'provisionner' => false,
        ])->assertForbidden();
    }

    public function test_un_super_admin_ouvre_un_etablissement_et_sa_base_est_amorcee(): void
    {
        Sanctum::actingAs(PlatformUser::create([
            'name' => 'Admin',
            'email' => 'admin@auditronx.com',
            'password' => 'secret1234',
            'role' => PlatformUser::ROLE_SUPER_ADMIN,
        ]));

        $reponse = $this->postJson('/api/central/etablissements', [
            'code' => 'ltn',
            'nom' => 'Lycée Technique Neuf',
            'ville' => 'Meiganga',
            'direction_email' => 'direction@ltn.cm',
        ])->assertCreated();

        $reponse->assertJsonPath('data.code', 'LTN')
            ->assertJsonPath('data.statut', Etablissement::STATUT_ACTIF)
            ->assertJsonPath('identifiants_direction.email', 'direction@ltn.cm');

        $nouveau = Etablissement::where('code', 'LTN')->firstOrFail();
        $this->assertNotNull($nouveau->provisionne_le);

        // La base du nouvel établissement est utilisable immédiatement : le
        // compte de direction s'y connecte, et il n'y a aucune donnée de
        // démonstration dedans.
        [$comptes, $personnel] = app(TenantManager::class)->execute(
            $nouveau,
            fn () => [User::count(), Enseignant::count()]
        );

        $this->assertSame(1, $comptes);
        $this->assertSame(0, $personnel);

        $this->withHeader('X-Tenant', 'LTN')
            ->postJson('/api/login', [
                'email' => 'direction@ltn.cm',
                'password' => $reponse->json('identifiants_direction.password'),
            ])->assertOk();
    }

    public function test_le_tableau_de_bord_editeur_agrege_les_etablissements(): void
    {
        $this->etablissementSupplementaire('AGG');
        Enseignant::factory()->count(2)->create();

        Sanctum::actingAs(PlatformUser::create([
            'name' => 'Admin',
            'email' => 'admin2@auditronx.com',
            'password' => 'secret1234',
            'role' => PlatformUser::ROLE_SUPER_ADMIN,
        ]));

        $reponse = $this->getJson('/api/central/tableau-de-bord')->assertOk();

        $this->assertSame(2, $reponse->json('etablissements.total'));

        $usage = collect($reponse->json('usage'))->keyBy('code');
        $this->assertSame(2, $usage['TEST']['personnel']);
        $this->assertSame(0, $usage['AGG']['personnel']);
    }

    public function test_un_etablissement_peut_avoir_ses_propres_identifiants_mysql(): void
    {
        // Cas de l'hébergement mutualisé : chaque base vient avec son
        // utilisateur, qui n'a de privilèges que sur elle.
        $heberge = $this->etablissementSupplementaire('MUTU', [
            'db_username' => 'u133979320_mutu',
            'db_password' => 'secret-mutu',
        ]);

        app(TenantManager::class)->execute($heberge, function () {
            $this->assertSame('u133979320_mutu', config('database.connections.tenant.username'));
            $this->assertSame('secret-mutu', config('database.connections.tenant.password'));
        });

        // Hors contexte, les identifiants du .env sont rendus : un
        // établissement ne doit pas laisser traîner les accès du précédent.
        $this->assertNotSame('u133979320_mutu', config('database.connections.tenant.username'));

        // Le mot de passe est chiffré en base et absent des réponses JSON.
        $brut = \Illuminate\Support\Facades\DB::connection('central')
            ->table('etablissements')
            ->where('code', 'MUTU')
            ->value('db_password');

        $this->assertNotSame('secret-mutu', $brut);
        $this->assertStringNotContainsString('secret-mutu', json_encode($heberge->fresh()));
    }
}
