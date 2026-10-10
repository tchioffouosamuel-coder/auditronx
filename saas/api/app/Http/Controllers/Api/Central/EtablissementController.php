<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\Etablissement;
use App\Models\Device;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Tenancy\ProvisionneurEtablissement;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Gestion des abonnés depuis le portail éditeur : créer, provisionner,
 * suspendre, réactiver, mesurer l'usage.
 *
 * C'est la contrepartie HTTP des commandes artisan `etablissement:*`. Les deux
 * passent par le même provisionneur : un client ouvert depuis le portail et un
 * client ouvert en ligne de commande sont strictement identiques.
 */
class EtablissementController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly ProvisionneurEtablissement $provisionneur,
    ) {}

    public function index(Request $request)
    {
        $etablissements = Etablissement::query()
            ->with(['abonnementActif.plan'])
            ->when($request->query('statut'), fn ($q, $v) => $q->where('statut', $v))
            ->when($request->query('recherche'), function ($q, $v) {
                $q->where(fn ($q) => $q->where('nom', 'like', "%{$v}%")
                    ->orWhere('code', 'like', "%{$v}%")
                    ->orWhere('ville', 'like', "%{$v}%"));
            })
            ->orderBy('nom')
            ->get();

        return response()->json(['data' => $etablissements]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'nom' => ['required', 'string', 'max:255'],
            'nom_court' => ['nullable', 'string', 'max:64'],
            'ville' => ['nullable', 'string', 'max:255'],
            'pays' => ['nullable', 'string', 'max:64'],
            'fuseau' => ['nullable', 'timezone'],
            'couleur_primaire' => ['nullable', 'string', 'max:16'],
            'couleur_secondaire' => ['nullable', 'string', 'max:16'],
            'contact_nom' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_tel' => ['nullable', 'string', 'max:32'],
            'db_name' => ['nullable', 'string', 'max:64'],
            // Hébergement mutualisé : chaque base vient avec son utilisateur.
            'db_username' => ['nullable', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            // Provisionner tout de suite (défaut) ou créer la fiche seule, le
            // temps que l'hébergeur crée la base à la main.
            'provisionner' => ['sometimes', 'boolean'],
            'direction_email' => ['nullable', 'email'],
            'direction_password' => ['nullable', 'string', 'min:10'],
        ]);

        $code = Etablissement::normaliseCode($data['code']);

        if ($code === '' || Etablissement::where('code', $code)->exists()) {
            return response()->json([
                'message' => 'Code d’établissement invalide ou déjà utilisé.',
                'errors' => ['code' => ['Code invalide ou déjà pris.']],
            ], 422);
        }

        $etablissement = Etablissement::create([
            ...collect($data)->except([
                'code', 'provisionner', 'direction_email', 'direction_password',
            ])->all(),
            'code' => $code,
            'fuseau' => $data['fuseau'] ?? 'Africa/Douala',
            'statut' => Etablissement::STATUT_EN_ATTENTE,
        ]);

        $identifiants = null;

        if ($request->boolean('provisionner', true)) {
            try {
                $this->provisionneur->provisionne($etablissement, direction: array_filter([
                    'email' => $data['direction_email'] ?? null,
                    'password' => $data['direction_password'] ?? null,
                ]));
                $identifiants = $this->provisionneur->dernieresIdentifiants();
            } catch (Throwable $e) {
                // La fiche est conservée en `en_attente` : le provisioning est
                // rejouable depuis l'action dédiée, sans ressaisir le dossier.
                return response()->json([
                    'message' => 'Établissement créé, mais le provisioning a échoué : ' . $e->getMessage(),
                    'data' => $etablissement->fresh(),
                ], 500);
            }
        }

        return response()->json([
            'data' => $etablissement->fresh(),
            // Affichés une seule fois, à transmettre au client : le mot de
            // passe n'est stocké que haché dans sa base.
            'identifiants_direction' => $identifiants,
        ], 201);
    }

    public function show(Etablissement $etablissement)
    {
        return response()->json([
            'data' => $etablissement->load(['abonnementActif.plan', 'abonnements.plan', 'bornes']),
            'usage' => $this->usage($etablissement),
        ]);
    }

    public function update(Request $request, Etablissement $etablissement)
    {
        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'nom_court' => ['nullable', 'string', 'max:64'],
            'ville' => ['nullable', 'string', 'max:255'],
            'pays' => ['nullable', 'string', 'max:64'],
            'fuseau' => ['nullable', 'timezone'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'couleur_primaire' => ['nullable', 'string', 'max:16'],
            'couleur_secondaire' => ['nullable', 'string', 'max:16'],
            'contact_nom' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_tel' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
            // Modifiables : un hébergeur peut imposer une rotation du mot de
            // passe MySQL d'un client sans que rien d'autre ne change.
            'db_username' => ['nullable', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ]);

        // `code` et `db_name` sont absents à dessein : les changer imposerait
        // de renommer la base et de déplacer les fichiers du client, et
        // invaliderait l'en-tête mémorisé par toutes ses apps installées.
        $etablissement->update($data);

        return response()->json(['data' => $etablissement->fresh()]);
    }

    /** Rejoue le provisioning d'un établissement resté en attente. */
    public function provision(Request $request, Etablissement $etablissement)
    {
        $data = $request->validate([
            'creer_la_base' => ['sometimes', 'boolean'],
            'amorcer' => ['sometimes', 'boolean'],
            'direction_email' => ['nullable', 'email'],
            'direction_password' => ['nullable', 'string', 'min:10'],
        ]);

        $this->provisionneur->provisionne(
            $etablissement,
            direction: array_filter([
                'email' => $data['direction_email'] ?? null,
                'password' => $data['direction_password'] ?? null,
            ]),
            creerLaBase: $request->boolean('creer_la_base', true),
            amorcer: $request->boolean('amorcer', true),
        );

        return response()->json([
            'data' => $etablissement->fresh(),
            'identifiants_direction' => $this->provisionneur->dernieresIdentifiants(),
        ]);
    }

    /** Suspension (non-paiement), réactivation, archivage. */
    public function statut(Request $request, Etablissement $etablissement)
    {
        $data = $request->validate([
            'statut' => ['required', Rule::in(Etablissement::STATUTS)],
            'motif' => ['nullable', 'string', 'max:500'],
        ]);

        $etablissement->forceFill([
            'statut' => $data['statut'],
            'suspendu_le' => $data['statut'] === Etablissement::STATUT_SUSPENDU ? now() : null,
            'notes' => $data['motif']
                ? trim($etablissement->notes . "\n" . now()->toDateString() . ' — ' . $data['motif'])
                : $etablissement->notes,
        ])->save();

        return response()->json(['data' => $etablissement->fresh()]);
    }

    /** Met à niveau le schéma de ce seul établissement (après déploiement). */
    public function migre(Etablissement $etablissement)
    {
        $this->provisionneur->migre($etablissement);

        return response()->json(['message' => 'Migrations jouées.']);
    }

    public function destroy(Request $request, Etablissement $etablissement)
    {
        // Même garde-fou qu'en ligne de commande : retaper le code. Une
        // suppression efface la base et les fichiers d'un client entier.
        $request->validate(['confirmation' => ['required', 'string']]);

        if (Etablissement::normaliseCode($request->input('confirmation')) !== $etablissement->code) {
            return response()->json([
                'message' => 'Confirmation incorrecte : retapez le code de l’établissement.',
            ], 422);
        }

        $this->provisionneur->supprime($etablissement);
        $etablissement->delete();

        return response()->json(['message' => 'Établissement supprimé.']);
    }

    /**
     * Mesure d'usage, lue dans la base du client — sert au suivi des quotas et
     * au support (« la borne de LCM a-t-elle pointé aujourd'hui ? »).
     *
     * @return array<string, int|string|null>
     */
    private function usage(Etablissement $etablissement): array
    {
        if (! $etablissement->estProvisionne()) {
            return [];
        }

        return $this->tenants->execute($etablissement, fn () => [
            'personnel' => Enseignant::count(),
            'devices_actifs' => Device::whereNull('revoked_at')->count(),
            'presences_30j' => Presence::where('date', '>=', now()->subDays(30)->toDateString())->count(),
            'derniere_presence' => Presence::max('date'),
        ]);
    }
}
