<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmploiDuTemps;
use App\Models\PeriodeCours;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Périodes de cours de l'établissement (§4.2 — Configuration). C'est cette
 * grille qui alimente les listes déroulantes « Heure de début » / « Heure de
 * fin » de l'emploi du temps, côté web comme côté mobile.
 */
class PeriodeCoursController extends Controller
{
    /**
     * GET /api/periodes-cours
     *
     * La migration peuple la table avec la grille historique : les listes
     * déroulantes d'un établissement déjà en service ne se vident pas à la
     * mise à jour.
     */
    public function index()
    {
        return response()->json(['data' => PeriodeCours::grille()->values()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->assertPlageLibre($data);

        return response()->json(PeriodeCours::create($data), 201);
    }

    public function show(PeriodeCours $periodeCour)
    {
        return response()->json($periodeCour);
    }

    public function update(Request $request, PeriodeCours $periodeCour)
    {
        $data = $this->validated($request, $periodeCour->id);
        $this->assertPlageLibre($data, $periodeCour->id);

        $periodeCour->update($data);

        return response()->json($periodeCour);
    }

    /**
     * Une période utilisée par des créneaux ne disparaît pas en silence :
     * supprimée, ces créneaux ne correspondraient plus à rien dans les listes
     * déroulantes et ne pourraient plus être réenregistrés sans être
     * entièrement ressaisis.
     */
    public function destroy(PeriodeCours $periodeCour)
    {
        // `getRawOriginal` : l'accesseur rend « 07:30 » alors que la colonne
        // porte « 07:30:00 ».
        $utilisations = EmploiDuTemps::where('heure_debut', $periodeCour->getRawOriginal('heure_debut'))
            ->where('heure_fin', $periodeCour->getRawOriginal('heure_fin'))
            ->count();

        if ($utilisations > 0) {
            throw ValidationException::withMessages([
                'heure_debut' => [
                    "Cette période est utilisée par {$utilisations} créneau(x) d'emploi du temps. "
                    .'Déplacez-les avant de la supprimer.',
                ],
            ]);
        }

        $periodeCour->delete();

        return response()->json(status: 204);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $rule = $ignoreId ? 'sometimes' : 'required';

        return $request->validate([
            'ordre' => [$rule, 'integer', 'between:1,99'],
            'libelle' => ['nullable', 'string', 'max:60'],
            'heure_debut' => [$rule, 'date_format:H:i'],
            'heure_fin' => [$rule, 'date_format:H:i', 'after:heure_debut'],
        ]);
    }

    /**
     * Deux périodes ne peuvent pas porter le même couple début/fin : la base
     * l'interdit par un index unique, qui remonterait sinon en erreur 500 au
     * lieu d'un message de saisie.
     */
    private function assertPlageLibre(array $data, ?int $ignoreId = null): void
    {
        if (! isset($data['heure_debut'], $data['heure_fin'])) {
            return;
        }

        $existe = PeriodeCours::where('heure_debut', $data['heure_debut'])
            ->where('heure_fin', $data['heure_fin'])
            ->when($ignoreId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'heure_debut' => ['Une période couvre déjà cette plage horaire.'],
            ]);
        }
    }
}
