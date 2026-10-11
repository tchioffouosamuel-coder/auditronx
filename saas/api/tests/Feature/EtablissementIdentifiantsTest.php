<?php

namespace Tests\Feature;

use App\Models\Central\Etablissement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Garde-fous de `etablissement:identifiants`.
 *
 * Écrits après un incident de production : l'`APP_KEY` ayant changé, les
 * `db_password` chiffrés de tous les établissements étaient devenus illisibles
 * et l'API répondait 500 sur chaque requête. Faute de commande dédiée, la
 * réparation est passée par une requête SQL écrite à la main — qui a inscrit
 * le marqueur « … » d'un modèle de commande dans les cinq établissements.
 *
 * Les trois comportements vérifiés ici sont ceux qui auraient évité chacune
 * des deux pannes.
 */
class EtablissementIdentifiantsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Chiffré bien formé mais dont le MAC ne correspond plus : c'est
     * exactement ce que laisse en base un changement d'`APP_KEY`.
     */
    private function chiffreIllisible(): string
    {
        $payload = json_decode(base64_decode(Crypt::encryptString('ancien')), true);
        $payload['mac'] = str_repeat('0', strlen($payload['mac']));

        return base64_encode(json_encode($payload));
    }

    private function motDePasseStocke(Etablissement $etablissement): ?string
    {
        return DB::connection('central')
            ->table('etablissements')
            ->where('id', $etablissement->id)
            ->value('db_password');
    }

    public function test_un_marqueur_de_documentation_est_refuse(): void
    {
        $etablissement = Etablissement::where('code', 'TEST')->firstOrFail();
        $avant = $this->motDePasseStocke($etablissement);

        $this->artisan('etablissement:identifiants TEST --password=…')
            ->expectsOutputToContain('marqueur de documentation')
            ->assertFailed();

        $this->assertSame(
            $avant,
            $this->motDePasseStocke($etablissement),
            'Un marqueur refusé ne doit rien écrire en base.',
        );
    }

    public function test_un_chiffre_illisible_est_diagnostique_comme_un_changement_de_cle(): void
    {
        $etablissement = Etablissement::where('code', 'TEST')->firstOrFail();

        DB::connection('central')->table('etablissements')
            ->where('id', $etablissement->id)
            ->update(['db_password' => $this->chiffreIllisible()]);

        $this->artisan('etablissement:identifiants TEST --tester')
            ->expectsOutputToContain('ILLISIBLE')
            ->expectsOutputToContain('APP_KEY')
            ->assertFailed();
    }

    public function test_tester_sans_code_porte_sur_tous_les_etablissements(): void
    {
        // `--tester` est un bilan : exiger `--tous` en plus ne produisait
        // qu'un refus « Indiquez un code d'établissement ».
        $this->artisan('etablissement:identifiants --tester')
            ->expectsOutputToContain('TEST')
            ->assertSuccessful();
    }

    public function test_le_mot_de_passe_peut_venir_d_un_fichier(): void
    {
        $etablissement = Etablissement::where('code', 'TEST')->firstOrFail();

        // Saisie par fichier : sur un hébergement où `stty` est indisponible,
        // Symfony ne masque pas la saisie clavier et le mot de passe
        // s'afficherait en clair. Le fichier est la voie sûre.
        $fichier = tempnam(sys_get_temp_dir(), 'mdp');
        file_put_contents($fichier, "depuisUnFichier2026\n");

        try {
            // Arguments en tableau : le chemin temporaire peut contenir une
            // espace, qu'un appel sous forme de chaîne découperait.
            $this->artisan('etablissement:identifiants', [
                'code' => 'TEST',
                '--password-file' => $fichier,
            ])->assertSuccessful();

            $this->assertSame(
                'depuisUnFichier2026',
                Crypt::decryptString($this->motDePasseStocke($etablissement)),
                'Le saut de ligne final laissé par un éditeur doit être retiré.',
            );
        } finally {
            @unlink($fichier);
        }
    }

    /**
     * Le cœur du problème : `$etablissement->save()` lèverait ici, parce que
     * le cast `encrypted` fait déchiffrer l'ancienne valeur pour détecter le
     * changement. La commande doit écrire malgré un ancien chiffré illisible.
     */
    public function test_le_mot_de_passe_se_remplace_meme_si_l_ancien_est_illisible(): void
    {
        $etablissement = Etablissement::where('code', 'TEST')->firstOrFail();

        DB::connection('central')->table('etablissements')
            ->where('id', $etablissement->id)
            ->update(['db_password' => $this->chiffreIllisible()]);

        $this->artisan('etablissement:identifiants TEST --password=motDePasseReel2026')
            ->assertSuccessful();

        $this->assertSame(
            'motDePasseReel2026',
            Crypt::decryptString($this->motDePasseStocke($etablissement)),
            'Le nouveau mot de passe doit être lisible avec l’APP_KEY en service.',
        );
    }
}
