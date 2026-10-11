<?php

namespace App\Console\Commands;

use App\Models\Central\Etablissement;
use App\Models\Enseignant;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * État de santé d'une installation, en une commande.
 *
 * Écrite après un déploiement où chaque symptôme (500 opaque, « Access denied
 * for user root », journal vide) a coûté un aller-retour pour être compris.
 * Tout ce qu'elle affiche répond à une question qui s'est réellement posée :
 * le `.env` est-il lu ? la configuration est-elle figée dans un cache ? quelle
 * base est réellement jointe ? quel établissement répond ?
 *
 * Ne divulgue aucun secret : des longueurs, jamais les mots de passe.
 */
class Diagnostic extends Command
{
    protected $signature = 'auditron:diagnostic
        {--etablissement=* : Limiter le contrôle des bases à ces codes}';

    protected $description = 'Contrôle l’installation : .env, caches, base centrale, bases des établissements.';

    private int $anomalies = 0;

    public function handle(TenantManager $tenants): int
    {
        $this->titre('Fichier .env');
        $this->controleEnv();

        $this->titre('Caches');
        $this->controleCaches();

        $this->titre('Configuration effective');
        $this->controleConfiguration();

        $this->titre('Base centrale');
        $centraleOk = $this->controleCentrale();

        if ($centraleOk) {
            $this->titre('Établissements');
            $this->controleEtablissements($tenants);
        }

        $this->titre('Dossiers');
        $this->controleDossiers();

        $this->newLine();

        if ($this->anomalies === 0) {
            $this->info('Aucune anomalie détectée.');

            return self::SUCCESS;
        }

        $this->error($this->anomalies . ' anomalie(s) détectée(s), voir les lignes ✗ ci-dessus.');

        return self::FAILURE;
    }

    private function controleEnv(): void
    {
        $chemin = base_path('.env');

        if (! file_exists($chemin)) {
            $this->ko('.env absent — Laravel utilise alors les valeurs par défaut du code,');
            $this->ligne('  c’est-à-dire `root` sans mot de passe sur une base `auditron_central`.');

            return;
        }

        if (! is_readable($chemin)) {
            $this->ko('.env présent mais illisible par l’utilisateur courant.');

            return;
        }

        $contenu = (string) file_get_contents($chemin);
        $this->ok('.env présent et lisible (' . strlen($contenu) . ' octets)');

        // Un fichier édité sous Windows puis envoyé tel quel : le \r final est
        // avalé dans la valeur, et `motdepasse\r` n'est pas `motdepasse`.
        if (str_contains($contenu, "\r")) {
            $this->ko('Fins de ligne Windows (CRLF) détectées — corriger : sed -i \'s/\r$//\' .env');
        }

        if (str_starts_with($contenu, "\u{FEFF}")) {
            $this->ko('Marque d’ordre des octets (BOM) en tête de fichier — à retirer.');
        }

        foreach (['APP_KEY', 'DB_CENTRAL_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $cle) {
            if (! preg_match('/^' . $cle . '=(.*)$/m', $contenu, $trouve) || trim($trouve[1]) === '') {
                $this->ko("{$cle} absent ou vide dans le .env");
            }
        }
    }

    private function controleCaches(): void
    {
        if (app()->configurationIsCached()) {
            $this->ligne('⚠ Configuration mise en cache : le .env n’est PLUS relu à chaque requête.');
            $this->ligne('  Après toute modification du .env : php artisan optimize:clear');
        } else {
            $this->ok('Configuration non mise en cache (le .env est relu à chaque requête)');
        }

        $this->ligne('  Routes en cache : ' . (app()->routesAreCached() ? 'oui' : 'non'));
    }

    private function controleConfiguration(): void
    {
        $central = config('database.connections.central');

        $this->ligne('  APP_ENV   : ' . app()->environment() . ' | APP_DEBUG : ' . (config('app.debug') ? 'ON (à couper en production)' : 'off'));
        $this->ligne('  APP_URL   : ' . config('app.url'));
        $this->ligne('  Connexion par défaut : ' . config('database.default'));
        $this->ligne('  Base centrale : ' . ($central['database'] ?? '—') . ' @ ' . ($central['host'] ?? '—'));
        $this->ligne('  Utilisateur   : ' . ($central['username'] ?? '—')
            . ' | mot de passe : ' . strlen((string) ($central['password'] ?? '')) . ' caractères');
        $this->ligne('  Sessions : ' . config('session.driver') . ' | Cache : ' . config('cache.default') . ' | File : ' . config('queue.default'));
        $this->ligne('  Journal  : ' . config('logging.default') . ' → storage/logs/'
            . (in_array('daily', (array) config('logging.channels.stack.channels'), true)
                ? 'laravel-' . now()->toDateString() . '.log'
                : 'laravel.log'));

        // Le symptôme exact rencontré en production, nommé explicitement.
        if (($central['username'] ?? null) === 'root' && ($central['password'] ?? '') === '') {
            $this->ko('Identifiants = root sans mot de passe : ce sont les valeurs par défaut du code,');
            $this->ligne('  donc le .env n’est pas lu (absent, illisible, ou configuration figée en cache).');
        }
    }

    private function controleCentrale(): bool
    {
        try {
            $total = DB::connection('central')->table('etablissements')->count();
            $this->ok("Connexion établie — {$total} établissement(s) déclaré(s)");

            return true;
        } catch (Throwable $e) {
            $this->ko('Connexion impossible : ' . $e->getMessage());

            return false;
        }
    }

    private function controleEtablissements(TenantManager $tenants): void
    {
        $codes = array_map(
            fn ($code) => Etablissement::normaliseCode($code),
            (array) $this->option('etablissement')
        );

        $etablissements = Etablissement::query()
            ->when($codes !== [], fn ($q) => $q->whereIn('code', $codes))
            ->orderBy('code')
            ->get();

        if ($etablissements->isEmpty()) {
            $this->ligne('  (aucun établissement déclaré)');

            return;
        }

        foreach ($etablissements as $etablissement) {
            $prefixe = str_pad($etablissement->code, 6);

            if (! $etablissement->estProvisionne()) {
                $this->ligne("  {$prefixe} en attente de provisioning — non servi aux clients");

                continue;
            }

            // Avant la connexion : le mot de passe stocké est-il lisible ?
            // Sans ce contrôle, une APP_KEY changée faisait rapporter les cinq
            // établissements « injoignables : The MAC is invalid » — le
            // symptôme, jamais la cause, et la connexion n'est même pas tentée.
            if (! $this->motDePasseLisible($etablissement, $prefixe)) {
                continue;
            }

            try {
                [$tables, $agents] = $tenants->execute($etablissement, fn () => [
                    count(DB::connection('tenant')->getSchemaBuilder()->getTableListing()),
                    Enseignant::count(),
                ]);

                $message = "{$prefixe} {$tables} tables, {$agents} agents, statut {$etablissement->statut}";

                // Le schéma de référence compte une trentaine de tables ; très
                // en dessous, les migrations métier n'ont pas été jouées.
                $tables < 25
                    ? $this->ko($message . ' — schéma incomplet, lancer tenants:migrate')
                    : $this->ok($message);
            } catch (Throwable $e) {
                $this->ko("{$prefixe} injoignable : " . $e->getMessage());
            }
        }
    }

    private function controleDossiers(): void
    {
        foreach ([
            'storage/framework/views',
            'storage/framework/sessions',
            'storage/framework/cache/data',
            'storage/logs',
            'bootstrap/cache',
            'public/tenants',
        ] as $relatif) {
            $chemin = base_path($relatif);

            match (true) {
                ! is_dir($chemin) => $this->ko("{$relatif} absent — mkdir -p {$relatif}"),
                ! is_writable($chemin) => $this->ko("{$relatif} non accessible en écriture"),
                default => $this->ok($relatif),
            };
        }
    }

    /**
     * Le `db_password` chiffré de cet établissement se déchiffre-t-il avec
     * l'APP_KEY en service ?
     *
     * Lecture par le constructeur de requêtes et non par le modèle : le cast
     * `encrypted` lèverait au premier accès à l'attribut, ce qu'on veut
     * justement attraper pour le nommer.
     */
    private function motDePasseLisible(Etablissement $etablissement, string $prefixe): bool
    {
        $chiffre = DB::connection('central')
            ->table('etablissements')
            ->where('id', $etablissement->id)
            ->value('db_password');

        if ($chiffre === null || $chiffre === '') {
            return true; // Les identifiants du .env s'appliquent.
        }

        try {
            Crypt::decryptString((string) $chiffre);

            return true;
        } catch (DecryptException $e) {
            $this->ko("{$prefixe} mot de passe de base illisible : {$e->getMessage()}");
            $this->ligne('         chiffré avec une autre APP_KEY que celle en service.');
            $this->ligne('         → restaurer l’ancienne clé (APP_KEY ou APP_PREVIOUS_KEYS),');
            $this->ligne('           ou resaisir : php artisan etablissement:identifiants --tous');

            return false;
        }
    }

    private function titre(string $texte): void
    {
        $this->newLine();
        $this->line("<options=bold>{$texte}</>");
    }

    private function ok(string $texte): void
    {
        $this->line("  <fg=green>✓</> {$texte}");
    }

    private function ko(string $texte): void
    {
        $this->anomalies++;
        $this->line("  <fg=red>✗</> {$texte}");
    }

    private function ligne(string $texte): void
    {
        $this->line($texte);
    }
}
