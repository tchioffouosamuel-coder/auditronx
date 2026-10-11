<?php

namespace Tests\Feature;

use App\Models\Central\Etablissement;
use App\Models\Central\PlatformUser;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Consultation des présences de tout le parc depuis le portail éditeur.
 *
 * C'est la seule route centrale qui lit des données métier en volume : les
 * frontières d'accès y comptent autant que le contenu.
 */
class CentralPresencesTest extends TestCase
{
    use RefreshDatabase;

    private function support(): PlatformUser
    {
        return PlatformUser::create([
            'name' => 'Support',
            'email' => 'support@auditronx.com',
            'password' => 'secret1234',
            'role' => PlatformUser::ROLE_SUPPORT,
        ]);
    }

    public function test_un_compte_d_etablissement_n_y_a_pas_acces(): void
    {
        // La direction d'un lycée ne doit pas lire les présences des autres.
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/central/presences')->assertForbidden();
    }

    public function test_sans_authentification_la_route_est_fermee(): void
    {
        $this->getJson('/api/central/presences')->assertUnauthorized();
    }

    public function test_le_support_lit_le_releve_de_la_journee(): void
    {
        $enseignant = Enseignant::factory()->create(['nom' => 'KOUADIO Awa']);
        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => now()->toDateString(),
            'heure_arrivee' => now()->setTime(7, 30),
        ]);

        Sanctum::actingAs($this->support());

        $reponse = $this->getJson('/api/central/presences')->assertOk();

        $etablissement = collect($reponse->json('etablissements'))
            ->firstWhere('code', $this->codeEtablissement);

        $this->assertNotNull($etablissement, 'L’établissement de test doit figurer au relevé.');
        $this->assertFalse($etablissement['injoignable']);
        $this->assertSame(1, $etablissement['pointages']);
        $this->assertSame(1, $etablissement['arrivees']);
        // Une arrivée sans départ : le symptôme qu'on vient chercher ici.
        $this->assertSame(1, $etablissement['sans_depart']);
        $this->assertSame(0, $etablissement['departs']);
    }

    /*
      Une requête par test, volontairement.

      Le harnais partage un fichier SQLite locataire et isole chaque test par
      une transaction (voir Tests\TestCase). Or la fin d'une requête referme
      le contexte locataire, et la requête suivante rouvre une connexion qui ne
      voit pas la transaction en cours : le second appel d'un même test lit une
      base vide. Deux tests valent mieux qu'un test faux.
    */
    public function test_par_defaut_le_releve_ne_nomme_personne(): void
    {
        $enseignant = Enseignant::factory()->create(['nom' => 'KOUADIO Awa']);
        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => now()->toDateString(),
            'heure_arrivee' => now()->setTime(7, 30),
        ]);

        Sanctum::actingAs($this->support());

        // Le relevé du parc n'a pas besoin de nommer les agents, et une
        // réponse qui les nomme tous traverserait le réseau pour rien.
        $reponse = $this->getJson('/api/central/presences')->assertOk();

        $etablissement = collect($reponse->json('etablissements'))
            ->firstWhere('code', $this->codeEtablissement);

        $this->assertArrayNotHasKey('lignes', $etablissement);
        $this->assertSame(1, $etablissement['pointages']);
    }

    public function test_les_lignes_nominatives_viennent_sur_demande(): void
    {
        $enseignant = Enseignant::factory()->create(['nom' => 'KOUADIO Awa']);
        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => now()->toDateString(),
            'heure_arrivee' => now()->setTime(7, 30),
            'heure_depart' => now()->setTime(16, 0),
        ]);

        Sanctum::actingAs($this->support());

        $reponse = $this->getJson('/api/central/presences?avec_lignes=1')->assertOk();

        $ligne = collect($reponse->json('etablissements'))
            ->firstWhere('code', $this->codeEtablissement)['lignes'][0];

        $this->assertSame('KOUADIO Awa', $ligne['nom']);
        $this->assertSame('07:30', $ligne['heure_arrivee']);
        $this->assertSame('16:00', $ligne['heure_depart']);
    }

    public function test_un_etablissement_injoignable_est_signale_sans_faire_echouer_le_relevé(): void
    {
        // Base inexistante : exactement ce qu'on veut voir rapporté plutôt que
        // ramené à zéro, qui ferait passer une panne pour une journée calme.
        Etablissement::create([
            'code' => 'CASSE',
            'nom' => 'Lycée injoignable',
            'db_name' => '/chemin/absent/base-introuvable.sqlite',
            'statut' => Etablissement::STATUT_ACTIF,
            'provisionne_le' => now(),
        ]);

        Sanctum::actingAs($this->support());

        $reponse = $this->getJson('/api/central/presences')->assertOk();

        $casse = collect($reponse->json('etablissements'))->firstWhere('code', 'CASSE');

        $this->assertTrue($casse['injoignable']);
        $this->assertNull($casse['pointages']);
        $this->assertSame(1, $reponse->json('totaux.injoignables'));
    }
}
