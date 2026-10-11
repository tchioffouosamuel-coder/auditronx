<?php

namespace App\Http\Middleware;

use App\Support\Audit;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filet de sécurité du journal d'audit (§4.2).
 *
 * `AuditObserver` couvre tout ce qui écrit en base. Restent les actions qui ne
 * modifient aucun modèle mais engagent quand même une responsabilité : un
 * export de la liste du personnel, un PDF d'assiduité, une tentative refusée.
 * Ce middleware les enregistre, et seulement si rien n'a déjà été écrit pendant
 * la requête — sinon chaque création produirait deux entrées.
 */
class JournaliseAction
{
    /** Méthodes considérées comme modifiantes. */
    private const METHODES_ECRITURE = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** Lectures journalisées malgré tout : elles font sortir des données. */
    private const LECTURES_SENSIBLES = [
        'api/spreadsheet/*/export',
        'api/spreadsheet/personnel/export-pdf',
        'api/assiduite/journal/pdf',
        'api/assiduite/journal-hebdomadaire/pdf',
        'api/assiduite/sans-presence/pdf',
        'api/statistiques/export-zip',
        'api/central/factures/*/pdf',
        'api/audit-logs',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $plateforme = $request->is('api/central/*');
        if (! $plateforme && ! app(TenantManager::class)->estInitialise()) {
            return $next($request);
        }

        $dejaEcrites = Audit::nombreEntreesEcrites();

        $response = $next($request);

        if (! $this->doitJournaliser($request, $response)) {
            return $response;
        }

        // Une écriture de modèle a déjà tout dit pendant cette requête.
        if (Audit::nombreEntreesEcrites() > $dejaEcrites && $response->getStatusCode() < 400) {
            return $response;
        }

        $arguments = [
            $this->action($request, $response),
            $this->sujet($request),
            [],
            $this->contexte($request, $response),
            $response->getStatusCode(),
        ];
        if ($plateforme) {
            Audit::enregistrerPlateforme(...$arguments);
        } else {
            Audit::enregistrer(...$arguments);
        }

        return $response;
    }

    private function doitJournaliser(Request $request, Response $response): bool
    {
        if ($request->is('api/relay/heartbeat')
            || $request->is('api/relay/logs')
            || $request->is('api/central/catalogue')
            || $request->is('api/central/annuaire/resolve')) {
            return false;
        }

        if (in_array($request->method(), self::METHODES_ECRITURE, true)) {
            return true;
        }

        if ($request->isMethod('GET') && $request->is('api/central/*')) {
            return true;
        }

        return $request->isMethod('GET') && $request->is(...self::LECTURES_SENSIBLES);
    }

    private function sujet(Request $request): ?Model
    {
        foreach ($request->route()?->parameters() ?? [] as $parametre) {
            if ($parametre instanceof Model) {
                return $parametre;
            }
        }

        return null;
    }

    /** `DELETE api/personnel/{enseignant}` → `personnel.delete` (ou `.refuse`). */
    private function action(Request $request, Response $response): string
    {
        $uri = (string) ($request->route()?->uri() ?? $request->path());
        $chemin = Str::of($uri)
            ->after('api/')
            ->replaceMatches('/\{[^}]+\}/', '')
            ->replace('//', '/')
            ->trim('/')
            ->replace('/', '.')
            ->value();

        $suffixe = $response->getStatusCode() >= 400
            ? 'refuse'
            : strtolower($request->method());

        return ($chemin ?: 'requete').'.'.$suffixe;
    }

    private function contexte(Request $request, Response $response): array
    {
        $contexte = ['parametres_route' => $request->route()?->parameters() ?? []];

        if ($request->isMethod('GET')) {
            $contexte['filtres'] = Audit::nettoyer($request->query());
        } else {
            $contexte['donnees'] = Audit::nettoyer($request->except(['file', 'fichier']));

            if ($request->allFiles() !== []) {
                $contexte['fichiers'] = array_keys($request->allFiles());
            }
        }

        if ($response->getStatusCode() >= 400) {
            $contexte['echec'] = $this->motifEchec($response);
        }

        return array_filter($contexte, fn ($v) => $v !== [] && $v !== null);
    }

    private function motifEchec(Response $response): string
    {
        $corps = json_decode((string) $response->getContent(), true);

        return Str::limit((string) ($corps['message'] ?? 'Erreur '.$response->getStatusCode()), 300, '');
    }
}
