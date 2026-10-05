<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Point d'entrée unique pour écrire dans le journal d'audit (§4.2).
 *
 * Deux sources alimentent le journal : l'observateur de modèles
 * (`AuditObserver`, pour les créations / modifications / suppressions) et le
 * middleware `JournaliseAction` (pour les actions qui ne touchent aucun
 * modèle : exports, bascules, imports, connexions).
 *
 * Toute écriture est enveloppée dans un try/catch : un journal défaillant ne
 * doit jamais faire échouer l'action métier qu'il observe.
 */
class Audit
{
    /** Champs jamais recopiés en clair dans le journal. */
    private const CHAMPS_SENSIBLES = [
        'password', 'password_confirmation', 'current_password', 'mot_de_passe',
        'token', 'api_token', 'plain_token', 'remember_token', 'fcm_token',
        'secret', 'code', 'otp', 'rfid_uid',
    ];

    private const MASQUE = '•••';

    /** Nombre d'entrées écrites pendant la requête courante. */
    private static int $compteur = 0;

    /** Désactive temporairement le journal (migrations, seeders, tests). */
    private static bool $actif = true;

    public static function desactiver(): void
    {
        self::$actif = false;
    }

    public static function activer(): void
    {
        self::$actif = true;
    }

    public static function nombreEntreesEcrites(): int
    {
        return self::$compteur;
    }

    /**
     * Enregistre une action.
     *
     * @param  string  $action  identifiant stable, ex. `enseignant.supprime`
     * @param  array<string, mixed>  $changements  {champ: {avant, apres}}
     * @param  array<string, mixed>  $contexte  informations libres (payload, filtres)
     */
    public static function enregistrer(
        string $action,
        ?Model $sujet = null,
        array $changements = [],
        array $contexte = [],
        ?int $statut = null,
    ): void {
        if (! self::$actif) {
            return;
        }

        try {
            AuditLog::create(array_merge(
                self::auteur(),
                self::requete(),
                [
                    'action' => $action,
                    'sujet_type' => $sujet ? $sujet::class : null,
                    'sujet_id' => $sujet?->getKey(),
                    'sujet_libelle' => $sujet ? self::libelle($sujet) : null,
                    'changements' => $changements ?: null,
                    'contexte' => $contexte ?: null,
                    'statut' => $statut,
                ],
            ));

            self::$compteur++;
        } catch (Throwable $e) {
            // Le journal ne doit jamais casser l'action journalisée.
            Log::warning('Audit : écriture impossible', ['action' => $action, 'erreur' => $e->getMessage()]);
        }
    }

    /** Identité de l'auteur, recopiée pour survivre à la suppression du compte. */
    private static function auteur(): array
    {
        $auteur = auth()->user();

        if ($auteur instanceof User) {
            return [
                'auteur_type' => 'user',
                'auteur_id' => $auteur->id,
                'auteur_nom' => $auteur->name,
                'auteur_email' => $auteur->email,
                'auteur_accreditation' => $auteur->accreditation?->label,
            ];
        }

        if ($auteur instanceof Enseignant) {
            return [
                'auteur_type' => 'enseignant',
                'auteur_id' => $auteur->id,
                'auteur_nom' => $auteur->nom,
                'auteur_email' => $auteur->email,
                'auteur_accreditation' => null,
            ];
        }

        if ($auteur instanceof Device) {
            return [
                'auteur_type' => 'borne',
                'auteur_id' => $auteur->id,
                'auteur_nom' => $auteur->nom ?? $auteur->identifiant ?? ('Borne #' . $auteur->id),
                'auteur_email' => null,
                'auteur_accreditation' => null,
            ];
        }

        // Commandes artisan, tâches planifiées, détection d'absences...
        return [
            'auteur_type' => self::enContexteHttp() ? 'anonyme' : 'systeme',
            'auteur_id' => null,
            'auteur_nom' => self::enContexteHttp() ? null : 'Système (tâche planifiée)',
            'auteur_email' => null,
            'auteur_accreditation' => null,
        ];
    }

    /**
     * Sommes-nous dans une requête HTTP ?
     *
     * `runningInConsole()` ne suffit pas : la suite de tests tourne en console
     * tout en exerçant de vraies requêtes. La présence d'une route résolue est
     * le signal fiable — une commande artisan n'en a jamais.
     */
    private static function enContexteHttp(): bool
    {
        return Request::route() !== null;
    }

    private static function requete(): array
    {
        if (! self::enContexteHttp()) {
            return ['methode' => 'CLI', 'url' => null, 'route' => null, 'ip' => null, 'user_agent' => null];
        }

        return [
            'methode' => Request::method(),
            'url' => Str::limit(Request::fullUrl(), 2040, ''),
            'route' => Request::route()?->uri(),
            'ip' => Request::ip(),
            'user_agent' => Str::limit((string) Request::userAgent(), 500, ''),
        ];
    }

    /** Libellé lisible d'un modèle : on prend le premier champ parlant disponible. */
    public static function libelle(Model $sujet): ?string
    {
        foreach (['nom', 'label', 'libelle', 'titre', 'name', 'matricule', 'identifiant', 'code', 'date'] as $champ) {
            $valeur = $sujet->getAttribute($champ);

            if (is_scalar($valeur) && (string) $valeur !== '') {
                return Str::limit((string) $valeur, 250, '');
            }
        }

        return class_basename($sujet) . ' #' . $sujet->getKey();
    }

    /** Remplace les valeurs sensibles et tronque ce qui est trop volumineux. */
    public static function nettoyer(array $donnees): array
    {
        $propre = [];

        foreach ($donnees as $champ => $valeur) {
            if (in_array(strtolower((string) $champ), self::CHAMPS_SENSIBLES, true)) {
                $propre[$champ] = self::MASQUE;

                continue;
            }

            if (is_array($valeur)) {
                $propre[$champ] = count($valeur) > 50
                    ? ['_tronque' => count($valeur) . ' éléments']
                    : self::nettoyer($valeur);

                continue;
            }

            if (is_object($valeur)) {
                $propre[$champ] = class_basename($valeur);

                continue;
            }

            $propre[$champ] = is_string($valeur) ? Str::limit($valeur, 500, '…') : $valeur;
        }

        return $propre;
    }
}
