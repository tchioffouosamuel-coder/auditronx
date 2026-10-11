<?php

namespace App\Console\Commands;

use App\Models\Central\Etablissement;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Terminal;
use Throwable;

/**
 * Lit et remplace les identifiants MySQL propres à la base d'un établissement.
 *
 * `etablissement:create` les pose à l'ouverture d'un abonné, mais rien ne
 * permettait de les corriger ensuite : le seul recours était une requête
 * écrite à la main, ce qui a déjà conduit à écrire un texte de remplacement
 * (« … ») dans les cinq établissements de production d'un coup.
 *
 * D'où les deux garde-fous de cette commande :
 *
 *  - le mot de passe est **demandé en saisie masquée** par défaut, donc jamais
 *    collé dans une ligne de commande qui finit dans l'historique du shell ;
 *  - la connexion est **testée avant d'écrire**. Des identifiants qui ne
 *    joignent pas la base ne sont pas enregistrés, sauf `--forcer`.
 *
 * Elle contourne volontairement Eloquent pour l'écriture. Le cast `encrypted`
 * de `db_password` fait comparer l'ancienne et la nouvelle valeur par
 * `getDirty()`, qui **déchiffre l'ancien chiffré** : si celui-ci a été produit
 * avec une autre APP_KEY — la situation même qu'on vient réparer — un
 * `$etablissement->save()` lève `DecryptException` au lieu d'écrire.
 *
 *   php artisan etablissement:identifiants LTM
 *   php artisan etablissement:identifiants --tous
 *   php artisan etablissement:identifiants --tester
 */
class EtablissementIdentifiants extends Command
{
    protected $signature = 'etablissement:identifiants
        {code? : Code de l\'établissement (inutile avec --tous)}
        {--username= : Utilisateur MySQL (inchangé si absent)}
        {--password= : Mot de passe en clair — déconseillé, il reste dans l\'historique du shell}
        {--password-file= : Lire le mot de passe dans ce fichier (1re ligne) plutôt qu’au clavier}
        {--tous : Parcourir tous les établissements}
        {--tester : Ne rien écrire : tester les identifiants déjà enregistrés}
        {--forcer : Enregistrer même si la connexion échoue}
        {--visible : Autoriser la saisie en clair quand le masquage est impossible}';

    protected $description = 'Teste et remplace les identifiants MySQL de la base d’un établissement.';

    /**
     * Valeurs qu'on refuse d'enregistrer : marqueurs de documentation laissés
     * par mégarde. Elles ne sont jamais un mot de passe, et le refus coûte
     * moins cher qu'une panne de plus.
     */
    private const MARQUEURS = ['REMPLACER', '…', '...', 'xxx', 'XXX', 'motdepasse', 'password'];

    private int $echecs = 0;

    public function handle(TenantManager $tenants): int
    {
        $etablissements = $this->etablissements();

        if ($etablissements->isEmpty()) {
            return self::FAILURE;
        }

        foreach ($etablissements as $etablissement) {
            $this->newLine();
            $this->line("<options=bold>{$etablissement->code}</> — " . $tenants->nomBase($etablissement));

            $this->option('tester')
                ? $this->teste($etablissement, $tenants)
                : $this->remplace($etablissement, $tenants);
        }

        $this->newLine();

        if ($this->echecs > 0) {
            $this->error("{$this->echecs} établissement(s) en échec.");

            return self::FAILURE;
        }

        $this->info('Terminé.');

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, Etablissement> */
    private function etablissements()
    {
        // `--tester` sans code porte sur toute la plateforme : c'est un bilan,
        // pas une modification, et exiger `--tous` en plus n'apportait rien
        // qu'un refus déroutant.
        if ($this->option('tous') || ($this->option('tester') && ! $this->argument('code'))) {
            $tous = Etablissement::orderBy('code')->get();

            if ($tous->isEmpty()) {
                $this->error('Aucun établissement déclaré.');
            }

            return $tous;
        }

        $code = Etablissement::normaliseCode((string) $this->argument('code'));

        if ($code === '') {
            $this->error('Indiquez un code d’établissement, ou --tous.');

            return collect();
        }

        $etablissement = Etablissement::where('code', $code)->first();

        if (! $etablissement) {
            $this->error("Établissement {$code} inconnu.");

            return collect();
        }

        return collect([$etablissement]);
    }

    /**
     * Contrôle les identifiants enregistrés, sans rien modifier.
     *
     * Distingue les deux pannes qui se ressemblaient à l'écran : un chiffré
     * illisible (APP_KEY changée) et un mot de passe refusé par MySQL.
     */
    private function teste(Etablissement $etablissement, TenantManager $tenants): void
    {
        $chiffre = $this->chiffreStocke($etablissement);

        if ($chiffre === null) {
            $this->line('  mot de passe : aucun — les identifiants du .env s’appliquent');
            $motDePasse = null;
        } else {
            try {
                $motDePasse = Crypt::decryptString($chiffre);
                $this->line('  mot de passe : déchiffré (' . strlen($motDePasse) . ' caractères)');
            } catch (DecryptException $e) {
                $this->echecs++;
                $this->error('  mot de passe ILLISIBLE : ' . $e->getMessage());
                $this->line('  → le chiffré a été produit avec une autre APP_KEY que celle en service.');
                $this->line('  → remettez l’ancienne clé (APP_KEY, ou APP_PREVIOUS_KEYS pour les garder');
                $this->line('    toutes les deux), ou resaisissez le mot de passe avec cette commande.');

                return;
            }
        }

        $this->connexion($etablissement, $tenants, $this->usernameEffectif($etablissement), $motDePasse)
            ? $this->info('  connexion : OK')
            : null;
    }

    private function remplace(Etablissement $etablissement, TenantManager $tenants): void
    {
        $username = $this->option('username') ?: $this->usernameEffectif($etablissement);
        $motDePasse = $this->motDePasseFourni();

        if ($motDePasse === null) {
            $motDePasse = $this->demandeMotDePasse($username);
        }

        if ($motDePasse === null || $motDePasse === '') {
            $this->line('  ignoré.');

            return;
        }

        if (in_array(trim($motDePasse), self::MARQUEURS, true)) {
            $this->echecs++;
            $this->error('  REFUSÉ : « ' . trim($motDePasse) . ' » est un marqueur de documentation, pas un mot de passe.');

            return;
        }

        $joignable = $this->connexion($etablissement, $tenants, $username, $motDePasse);

        if (! $joignable && ! $this->option('forcer')) {
            $this->echecs++;
            $this->error('  rien enregistré — ces identifiants ne joignent pas la base (--forcer pour passer outre).');

            return;
        }

        /*
          Écriture par le constructeur de requêtes, pas par le modèle : voir le
          commentaire de classe — le cast `encrypted` ferait déchiffrer
          l'ancienne valeur, qui est précisément celle qu'on ne sait plus lire.
        */
        DB::connection('central')
            ->table('etablissements')
            ->where('id', $etablissement->id)
            ->update([
                'db_username' => $username,
                'db_password' => Crypt::encryptString($motDePasse),
                'updated_at' => now(),
            ]);

        $this->info('  enregistré (' . strlen($motDePasse) . ' caractères, chiffré avec l’APP_KEY en service).');

        if (! $joignable) {
            $this->warn('  … mais la connexion échoue : --forcer a été utilisé.');
        }
    }

    /**
     * Mot de passe passé en option, s'il y en a un. `null` = rien de fourni,
     * il faudra le demander.
     */
    private function motDePasseFourni(): ?string
    {
        $fichier = $this->option('password-file');

        if ($fichier) {
            if (! is_readable($fichier)) {
                $this->echecs++;
                $this->error("  fichier illisible : {$fichier}");

                return '';
            }

            // Première ligne seulement : un éditeur ajoute volontiers un saut
            // de ligne final, qui ferait un mot de passe faux de justesse.
            $lignes = file($fichier, FILE_IGNORE_NEW_LINES);

            return trim($lignes[0] ?? '');
        }

        $direct = $this->option('password');

        return $direct === null || $direct === '' ? null : $direct;
    }

    /**
     * Demande le mot de passe au clavier, masqué.
     *
     * Et refuse de le demander quand le masquage est impossible. Symfony
     * masque la saisie via `stty`, qu'il appelle avec `shell_exec` ; sur un
     * hébergement mutualisé où cette fonction est désactivée, il retombe
     * **silencieusement** sur une saisie en clair. Le mot de passe s'affiche
     * alors à l'écran et reste dans l'historique du terminal, exactement ce
     * que cette commande était censée éviter.
     *
     * On échoue donc fermé : --password-file est la bonne réponse, --visible
     * la dérogation explicite.
     */
    private function demandeMotDePasse(string $username): ?string
    {
        if (! $this->peutMasquerLaSaisie() && ! $this->option('visible')) {
            $this->echecs++;
            $this->error('  saisie masquée impossible sur ce système (stty indisponible,');
            $this->error('  shell_exec probablement désactivé). Rien n’a été demandé.');
            $this->line('  → mettez le mot de passe dans un fichier :');
            $this->line('      printf %s \'MotDePasse\' > /tmp/mdp && chmod 600 /tmp/mdp');
            $this->line('      php artisan etablissement:identifiants ' . $this->argument('code')
                . ' --password-file=/tmp/mdp && rm -f /tmp/mdp');
            $this->line('  → ou --visible pour saisir en clair en connaissance de cause.');

            return null;
        }

        return (string) $this->secret("  Mot de passe MySQL de {$username} (vide = ignorer)");
    }

    /** Le terminal permet-il réellement de masquer une saisie ? */
    private function peutMasquerLaSaisie(): bool
    {
        if (! function_exists('shell_exec')) {
            return false;
        }

        // Sous Windows, Symfony passe par un binaire dédié (hiddeninput.exe)
        // plutôt que par stty.
        if (DIRECTORY_SEPARATOR === '\\') {
            return true;
        }

        return Terminal::hasSttyAvailable();
    }

    /**
     * Chiffré brut tel qu'il est en base, sans passer par le cast du modèle —
     * seul moyen de l'examiner quand il ne se déchiffre plus.
     */
    private function chiffreStocke(Etablissement $etablissement): ?string
    {
        $valeur = DB::connection('central')
            ->table('etablissements')
            ->where('id', $etablissement->id)
            ->value('db_password');

        return $valeur === null || $valeur === '' ? null : (string) $valeur;
    }

    /** Utilisateur qui sera réellement employé : colonne dédiée, sinon .env. */
    private function usernameEffectif(Etablissement $etablissement): string
    {
        return (string) ($etablissement->db_username
            ?: config('database.connections.tenant.username'));
    }

    /**
     * Ouvre une connexion d'essai sur la base du locataire. Une connexion
     * dédiée et jetable : toucher à `tenant` purgerait la connexion en cours
     * d'usage par la commande elle-même.
     */
    private function connexion(
        Etablissement $etablissement,
        TenantManager $tenants,
        string $username,
        ?string $motDePasse,
    ): bool {
        $nom = 'tenant_essai';

        config([
            'database.connections.' . $nom => array_merge(
                config('database.connections.tenant'),
                [
                    'database' => $tenants->nomBase($etablissement),
                    'username' => $username,
                    'password' => $motDePasse ?? config('database.connections.tenant.password'),
                ],
            ),
        ]);

        DB::purge($nom);

        try {
            DB::connection($nom)->getPdo();

            return true;
        } catch (Throwable $e) {
            $this->echecs++;
            $this->error('  connexion REFUSÉE : ' . $this->resume($e->getMessage()));

            return false;
        } finally {
            DB::purge($nom);
        }
    }

    /** Première ligne du message, sans la requête SQL ni la trace. */
    private function resume(string $message): string
    {
        $message = strtok($message, "\n") ?: $message;

        return strlen($message) > 160 ? substr($message, 0, 157) . '…' : $message;
    }
}
