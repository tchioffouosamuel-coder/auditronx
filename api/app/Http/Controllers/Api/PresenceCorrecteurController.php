<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\Enseignant;
use App\Models\Presence;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Correcteur de présences (§4.2) — prévisualisation et correction groupée des anomalies. */
class PresenceCorrecteurController extends Controller
{
    use AccessibleEnseignants;

    /** GET /api/presences/anomalies?date=&section= — arrivée sans départ, absence de pointage un jour ouvré, etc. */
    public function anomalies(Request $request)
    {
        $date = Carbon::parse($request->query('date', now()));

        $enseignants = $this->enseignantsAccessibles($request->user())
            ->when($request->query('section'), fn ($q, $v) => $q->where('section', $v))
            ->get();

        $presences = Presence::whereDate('date', $date->toDateString())
            ->whereIn('enseignant_id', $enseignants->pluck('id'))
            ->get()
            ->keyBy('enseignant_id');

        $anomalies = [];

        foreach ($enseignants as $enseignant) {
            $presence = $presences->get($enseignant->id);

            if ($presence && $presence->heure_arrivee && ! $presence->heure_depart) {
                $anomalies[] = [
                    'type' => 'depart_manquant',
                    'enseignant_id' => $enseignant->id,
                    'nom' => $enseignant->nom,
                    'presence_id' => $presence->id,
                ];
            } elseif (! $presence && $enseignant->emploiDuTemps()->where('jour', $date->isoWeekday())->exists()) {
                $anomalies[] = [
                    'type' => 'pointage_manquant',
                    'enseignant_id' => $enseignant->id,
                    'nom' => $enseignant->nom,
                    'presence_id' => null,
                ];
            }
        }

        return response()->json(['date' => $date->toDateString(), 'anomalies' => $anomalies]);
    }

    /** POST /api/presences/corriger — correction groupée, journalisée (§7 — traçabilité). */
    public function corriger(Request $request)
    {
        $data = $request->validate([
            'corrections' => ['required', 'array', 'min:1'],
            'corrections.*.enseignant_id' => ['required', 'exists:enseignants,id'],
            'corrections.*.date' => ['required', 'date'],
            'corrections.*.heure_arrivee' => ['nullable', 'date_format:H:i'],
            'corrections.*.heure_depart' => ['nullable', 'date_format:H:i'],
            'corrections.*.motif' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $resultats = [];

        foreach ($data['corrections'] as $correction) {
            $enseignant = Enseignant::findOrFail($correction['enseignant_id']);
            abort_unless($this->peutAccederA($user, $enseignant), 403);

            $presence = Presence::firstOrNew([
                'enseignant_id' => $correction['enseignant_id'],
                'date' => $correction['date'],
            ]);

            if (! empty($correction['heure_arrivee'])) {
                $presence->heure_arrivee = $correction['date'].' '.$correction['heure_arrivee'];
            }
            if (! empty($correction['heure_depart'])) {
                $presence->heure_depart = $correction['date'].' '.$correction['heure_depart'];
            }

            $presence->source = 'manuel';
            $presence->recorded_by = $user->id;
            $presence->on_behalf_of = $correction['enseignant_id'];
            $presence->reason = $correction['motif'];
            $presence->save();

            $resultats[] = $presence;
        }

        return response()->json($resultats, 200);
    }
}
