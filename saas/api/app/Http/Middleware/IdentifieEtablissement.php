<?php

namespace App\Http\Middleware;

use App\Models\Central\Etablissement;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Résout l'établissement de la requête et bascule l'application sur sa base.
 *
 * URL unique : tous les clients tapent la même API, et désignent leur
 * établissement par l'en-tête `X-Tenant` qu'ils ont mémorisé une fois pour
 * toutes (connexion du portail, activation de l'app, `config.h` de la borne).
 * Un repli par champ de requête existe pour les cas où l'en-tête n'est pas
 * maîtrisable, et un repli par sous-domaine dort derrière un drapeau de
 * configuration pour le jour où `ltm.auditronx.com` serait souhaité.
 *
 * Le contexte n'est volontairement PAS refermé à la sortie de `handle()` :
 * d'autres intergiciels écrivent encore après (le journal d'audit, par
 * exemple), et ils doivent écrire dans la base du client. Le nettoyage a lieu
 * dans `terminate()`, après l'envoi de la réponse.
 */
class IdentifieEtablissement
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $code = $this->codeDemande($request);

        if ($code === '') {
            return $this->refus(
                400,
                'etablissement_absent',
                'Établissement non précisé : envoyez l’en-tête ' . config('auditron.tenant.entete') . '.'
            );
        }

        $etablissement = $this->tenants->resoudre($code);

        if (! $etablissement || ! $etablissement->estProvisionne()) {
            return $this->refus(404, 'etablissement_inconnu', 'Établissement inconnu.');
        }

        if ($etablissement->estSuspendu()) {
            return $this->refus(
                402,
                'abonnement_suspendu',
                'L’abonnement de cet établissement est suspendu. Contactez Auditron.'
            );
        }

        if (! $etablissement->estActif()) {
            return $this->refus(403, 'etablissement_inactif', 'Cet établissement n’est pas actif.');
        }

        $this->tenants->initialisePourLaRequete($etablissement);

        $response = $next($request);

        // Permet au client de vérifier sur quel établissement il a travaillé —
        // utile quand un support reproduit un bug sur le mauvais compte.
        $response->headers->set('X-Tenant', $etablissement->code);

        return $response;
    }

    /** Referme le contexte après envoi de la réponse (nécessaire sous Octane). */
    public function terminate(Request $request, Response $response): void
    {
        $this->tenants->termineLaRequete();
    }

    private function codeDemande(Request $request): string
    {
        $entete = (string) config('auditron.tenant.entete', 'X-Tenant');

        if ($code = $request->header($entete)) {
            return Etablissement::normaliseCode($code);
        }

        $champ = (string) config('auditron.tenant.champ', 'etablissement');

        if ($code = $request->input($champ)) {
            return Etablissement::normaliseCode(is_string($code) ? $code : null);
        }

        return $this->codeDepuisHote($request);
    }

    /** Repli `ltm.auditronx.com` → `LTM`, inactif par défaut. */
    private function codeDepuisHote(Request $request): string
    {
        if (! config('auditron.tenant.sous_domaine')) {
            return '';
        }

        $hote = strtolower($request->getHost());
        $base = strtolower((string) config('auditron.tenant.domaine_base'));

        if ($base === '' || ! str_ends_with($hote, '.' . $base)) {
            return '';
        }

        return Etablissement::normaliseCode(substr($hote, 0, -strlen('.' . $base)));
    }

    private function refus(int $statut, string $code, string $message): Response
    {
        return response()->json([
            'message' => $message,
            'erreur' => $code,
        ], $statut);
    }
}
