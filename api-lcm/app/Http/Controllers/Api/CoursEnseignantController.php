<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\LeconRealisee;
use App\Models\Presence;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CoursEnseignantController extends Controller
{
    public function index(Request $request)
    {
        $enseignant = $this->enseignant($request);
        $date = Carbon::parse($request->query('date', now()));
        $present = Presence::where('enseignant_id', $enseignant->id)
            ->where('date', $date->toDateString())
            ->whereNotNull('heure_arrivee')
            ->exists();

        $cours = EmploiDuTemps::with(['classe', 'discipline'])
            ->where('enseignant_id', $enseignant->id)
            ->where('jour', $date->isoWeekday())
            ->orderBy('heure_debut')
            ->get();

        $realisees = LeconRealisee::where('enseignant_id', $enseignant->id)
            ->where('date', $date->toDateString())
            ->whereIn('emploi_du_temps_id', $cours->pluck('id'))
            ->get()
            ->groupBy('emploi_du_temps_id')
            ->map(fn($items) => $items->pluck('progression_lecon_id')->all());

        return response()->json([
            'date' => $date->toDateString(),
            'present' => $present,
            'cours' => $cours->map(fn(EmploiDuTemps $cours) => [
                'emploi_du_temps_id' => $cours->id,
                'classe' => $cours->classe->nom,
                'discipline' => $cours->discipline->nom,
                'heure_debut' => $cours->heure_debut,
                'heure_fin' => $cours->heure_fin,
                'lecons' => $cours->discipline->programmes()
                    ->where('classe_id', $cours->classe_id)
                    ->where('annee_scolaire', $this->anneeScolaire($date))
                    ->with('lecons')
                    ->first()?->lecons->map(fn($lecon) => [
                        'id' => $lecon->id,
                        'ordre' => $lecon->ordre,
                        'titre' => $lecon->unite_enseignement,
                        'unite_apprentissage' => $lecon->unite_apprentissage,
                        'duree' => $lecon->duree,
                        'faite' => in_array($lecon->id, $realisees->get($cours->id, []), true),
                    ])->values() ?? collect(),
            ]),
        ]);
    }

    public function toggleLecon(Request $request)
    {
        $data = $request->validate([
            'emploi_du_temps_id' => ['required', 'exists:emploi_du_temps,id'],
            'progression_lecon_id' => ['required', 'exists:progression_lecons,id'],
            'date' => ['required', 'date'],
        ]);
        $enseignant = $this->enseignant($request);
        $date = Carbon::parse($data['date']);

        abort_unless(Presence::where('enseignant_id', $enseignant->id)->where('date', $date->toDateString())->whereNotNull('heure_arrivee')->exists(), 403, 'Présence requise pour déclarer une leçon.');
        $cours = EmploiDuTemps::where('id', $data['emploi_du_temps_id'])->where('enseignant_id', $enseignant->id)->firstOrFail();

        $lecon = LeconRealisee::where([
            'enseignant_id' => $enseignant->id,
            'emploi_du_temps_id' => $cours->id,
            'progression_lecon_id' => $data['progression_lecon_id'],
            'date' => $date->toDateString(),
        ])->first();

        if ($lecon) {
            $lecon->delete();
            return response()->json(['faite' => false]);
        }

        $lecon = LeconRealisee::create([
            'enseignant_id' => $enseignant->id,
            'emploi_du_temps_id' => $cours->id,
            'progression_lecon_id' => $data['progression_lecon_id'],
            'date' => $date->toDateString(),
        ]);

        return response()->json(['faite' => true, 'id' => $lecon->id]);
    }

    private function enseignant(Request $request): Enseignant
    {
        abort_unless($request->user() instanceof Enseignant, 403, 'Authentification enseignant requise.');
        return $request->user();
    }

    private function anneeScolaire(Carbon $date): string
    {
        $debut = $date->month >= 9 ? $date->year : $date->year - 1;
        return "{$debut}/" . ($debut + 1);
    }
}
