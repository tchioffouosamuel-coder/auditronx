<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\AbsenceAlertLog;
use Illuminate\Http\Request;

/** Alertes (§4.2) — historique des notifications d'absences répétées. */
class AbsenceAlertController extends Controller
{
    use AccessibleEnseignants;

    public function index(Request $request)
    {
        $enseignantIds = $this->enseignantsAccessibles($request->user())->pluck('id');

        $alertes = AbsenceAlertLog::with('enseignant')
            ->whereIn('enseignant_id', $enseignantIds)
            ->orderByDesc('sent_at')
            ->paginate(30);

        return response()->json($alertes);
    }
}
