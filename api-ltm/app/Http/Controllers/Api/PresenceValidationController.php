<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\CoursValidation;
use App\Models\EmploiDuTemps;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Validation des présences (§4.2) — calendrier de validation, bascule fait/non fait. */
class PresenceValidationController extends Controller
{
    use AccessibleEnseignants;

    /** GET /api/presences/validation?date=&classe_id= — calendrier des cours du jour avec leur statut. */
    public function index(Request $request)
    {
        $date = Carbon::parse($request->query('date', now()));

        $enseignantIds = $this->enseignantsAccessibles($request->user())->pluck('id');

        $cours = EmploiDuTemps::with(['enseignant', 'classe', 'discipline'])
            ->where('jour', $date->isoWeekday())
            ->whereIn('enseignant_id', $enseignantIds)
            ->when($request->query('classe_id'), fn ($q, $v) => $q->where('classe_id', $v))
            ->orderBy('heure_debut')
            ->get();

        $validations = CoursValidation::whereDate('date', $date->toDateString())
            ->whereIn('emploi_du_temps_id', $cours->pluck('id'))
            ->get()
            ->keyBy('emploi_du_temps_id');

        $calendrier = $cours->map(fn (EmploiDuTemps $c) => [
            'emploi_du_temps_id' => $c->id,
            'enseignant' => $c->enseignant->nom,
            'classe' => $c->classe->nom,
            'discipline' => $c->discipline->nom,
            'heure_debut' => $c->heure_debut,
            'heure_fin' => $c->heure_fin,
            'status' => $validations->get($c->id)?->status ?? 'non_fait',
        ]);

        return response()->json(['date' => $date->toDateString(), 'cours' => $calendrier]);
    }

    /** POST /api/presences/validation/toggle — bascule le statut fait/non_fait d'un cours à une date. */
    public function toggle(Request $request)
    {
        $data = $request->validate([
            'emploi_du_temps_id' => ['required', 'exists:emploi_du_temps,id'],
            'date' => ['required', 'date'],
        ]);

        $emploiDuTemps = EmploiDuTemps::findOrFail($data['emploi_du_temps_id']);

        $validation = CoursValidation::firstOrNew([
            'emploi_du_temps_id' => $data['emploi_du_temps_id'],
            'date' => $data['date'],
        ]);

        $validation->enseignant_id = $emploiDuTemps->enseignant_id;
        $validation->status = $validation->status === 'fait' ? 'non_fait' : 'fait';
        $validation->save();

        return response()->json($validation);
    }
}
