<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\CentralAuditLog;
use Illuminate\Http\Request;

/** Consultation en lecture seule du journal central Auditron. */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filtres = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'string', 'max:255'],
            'auteur' => ['nullable', 'string', 'max:255'],
            'debut' => ['nullable', 'date'],
            'fin' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'between:1,500'],
        ]);

        $query = CentralAuditLog::query()
            ->when($filtres['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', "{$v}%"))
            ->when($filtres['auteur'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('auteur_nom', 'like', "%{$v}%")->orWhere('auteur_email', 'like', "%{$v}%");
            }))
            ->when($filtres['debut'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filtres['fin'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($filtres['q'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('auteur_nom', 'like', "%{$v}%")
                    ->orWhere('auteur_email', 'like', "%{$v}%")
                    ->orWhere('sujet_libelle', 'like', "%{$v}%")
                    ->orWhere('action', 'like', "%{$v}%")
                    ->orWhere('ip', 'like', "%{$v}%");
            }));

        return response()->json($query->orderByDesc('id')->paginate($filtres['per_page'] ?? 50));
    }
}
