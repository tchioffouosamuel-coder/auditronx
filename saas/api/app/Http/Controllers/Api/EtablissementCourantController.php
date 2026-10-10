<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Enseignant;
use App\Tenancy\TenantManager;

/**
 * L'établissement courant, vu depuis ses propres clients.
 *
 * Une seule app et un seul portail pour tous les abonnés : le nom, le logo et
 * les couleurs ne peuvent donc plus être compilés dans le binaire. Ils sont
 * lus ici, sans authentification, dès que le client connaît son code — c'est
 * ce qui permet d'afficher l'écran de connexion déjà aux couleurs du lycée.
 */
class EtablissementCourantController extends Controller
{
    public function __construct(private readonly TenantManager $tenants) {}

    /** GET /api/etablissement — branding, accessible avant toute connexion. */
    public function show()
    {
        return response()->json(['data' => $this->tenants->exigeCourant()->branding()]);
    }

    /**
     * GET /api/etablissement/abonnement — plan, quotas et consommation.
     *
     * Réservé aux comptes d'administration de l'établissement : c'est à la
     * direction de savoir qu'elle approche de son quota de personnel, pas à
     * chaque enseignant.
     */
    public function abonnement()
    {
        $etablissement = $this->tenants->exigeCourant();
        $abonnement = $etablissement->abonnementActif()->with('plan')->first();
        $plan = $abonnement?->plan;

        $personnel = Enseignant::count();
        $bornes = Device::where('device_type', 'relay_gateway')->whereNull('revoked_at')->count();

        return response()->json([
            'data' => [
                'statut' => $etablissement->statut,
                'plan' => $plan?->only(['code', 'nom', 'max_personnel', 'max_bornes', 'fonctionnalites']),
                'fin_le' => $abonnement?->fin_le?->toDateString(),
                'jours_restants' => $abonnement?->joursRestants(),
                'consommation' => [
                    'personnel' => $personnel,
                    'personnel_max' => $plan?->max_personnel,
                    'bornes' => $bornes,
                    'bornes_max' => $plan?->max_bornes,
                ],
                // Signalé au portail pour afficher un bandeau avant que le
                // quota ne bloque réellement une création.
                'quota_personnel_atteint' => $plan?->max_personnel !== null && $personnel >= $plan->max_personnel,
            ],
        ]);
    }
}
