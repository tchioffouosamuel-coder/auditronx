<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Enseignant;
use App\Models\VisageEmbedding;
use Illuminate\Http\Request;

/**
 * Synchronisation d'une borne de reconnaissance faciale (§5, ESP32-S3 + ESP-WHO) :
 * la borne récupère ici les photos d'enseignants à enrôler localement (calcul de
 * l'embedding embarqué) ainsi que les embeddings déjà enrôlés par d'autres bornes
 * (référentiel partagé, pas de recalcul redondant).
 */
class KioskManifestController extends Controller
{
    /** GET /api/kiosks/manifest?since=... */
    public function index(Request $request)
    {
        $principal = $request->user();

        if (! $principal instanceof Device || $principal->device_type !== 'kiosk_facial' || $principal->isRevoked()) {
            abort(403, 'Authentification poste de reconnaissance faciale requise.');
        }

        $since = $request->query('since');

        $enseignants = Enseignant::query()
            ->whereNotNull('photo_path')
            ->when($since, fn ($q, $v) => $q->where('photo_updated_at', '>', $v))
            ->get(['id', 'nom', 'photo_path', 'photo_updated_at'])
            ->map(fn (Enseignant $e) => [
                'id' => $e->id,
                'nom' => $e->nom,
                'photo_url' => $e->photo_url,
                'photo_updated_at' => $e->photo_updated_at,
            ]);

        $embeddings = VisageEmbedding::query()
            ->whereNull('revoked_at')
            ->when($since, fn ($q, $v) => $q->where('enrolled_at', '>', $v))
            ->get(['enseignant_id', 'embedding', 'enrolled_at'])
            ->map(fn (VisageEmbedding $v) => [
                'enseignant_id' => $v->enseignant_id,
                'embedding' => $v->embedding,
                'enrolled_at' => $v->enrolled_at,
            ]);

        return response()->json([
            'server_time' => now(),
            'enseignants' => $enseignants,
            'embeddings' => $embeddings,
        ]);
    }
}
