<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Consultation du journal d'audit (§4.2).
 *
 * Lecture seule et sans route d'écriture ni de purge : le journal sert à
 * établir les responsabilités, il ne doit pas être modifiable depuis
 * l'application qu'il surveille.
 */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filtres = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'max:255'],
            'auteur' => ['nullable', 'string', 'max:255'],
            'sujet_type' => ['nullable', 'string', 'max:255'],
            'sujet_id' => ['nullable', 'integer'],
            'debut' => ['nullable', 'date'],
            'fin' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'between:1,500'],
        ]);

        $query = AuditLog::query()
            ->when($filtres['action'] ?? null, fn($q, $v) => $q->where('action', 'like', "{$v}%"))
            ->when($filtres['auteur'] ?? null, fn($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('auteur_nom', 'like', "%{$v}%")->orWhere('auteur_email', 'like', "%{$v}%");
            }))
            ->when($filtres['sujet_type'] ?? null, fn($q, $v) => $q->where('sujet_type', 'like', "%{$v}"))
            ->when($filtres['sujet_id'] ?? null, fn($q, $v) => $q->where('sujet_id', $v))
            ->when($filtres['debut'] ?? null, fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filtres['fin'] ?? null, fn($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filtres['q'] ?? null, fn($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('auteur_nom', 'like', "%{$v}%")
                    ->orWhere('auteur_email', 'like', "%{$v}%")
                    ->orWhere('sujet_libelle', 'like', "%{$v}%")
                    ->orWhere('action', 'like', "%{$v}%")
                    ->orWhere('ip', 'like', "%{$v}%");
            }));

        return response()->json(
            $query->orderByDesc('id')->paginate($filtres['per_page'] ?? 50),
        );
    }

    /** Valeurs distinctes présentes dans le journal, pour alimenter les filtres. */
    public function actions()
    {
        return response()->json([
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'auteurs' => AuditLog::query()
                ->whereNotNull('auteur_nom')
                ->distinct()
                ->orderBy('auteur_nom')
                ->pluck('auteur_nom'),
        ]);
    }
}
