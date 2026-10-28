<?php

namespace App\Services;

use App\Models\Enseignant;
use App\Models\Presence;
use App\Models\Signalement;
use Carbon\Carbon;

class AssiduiteCalculator
{
    public function __construct(private readonly HoraireAttendu $horaires) {}

    public function bilan(Enseignant $enseignant, Carbon $debut, Carbon $fin): array
    {
        $enseignant->loadMissing([
            'emploiDuTemps',
            'presences' => fn ($query) => $query->whereBetween('date', [$debut->toDateString(), $fin->toDateString()]),
            'signalements' => fn ($query) => $query->whereDate('date', '<=', $fin->toDateString()),
        ]);

        $datesPointees = $enseignant->presences
            ->filter(fn (Presence $presence) => $presence->heure_arrivee || $presence->heure_depart)
            ->map(fn (Presence $presence) => $presence->date->toDateString())
            ->unique();
        $attendus = $valides = $signales = 0;

        for ($jour = $debut->copy()->startOfDay(); $jour->lte($fin); $jour->addDay()) {
            if (! $this->horaires->absenceEvaluable($enseignant, $jour, $enseignant->emploiDuTemps)) {
                continue;
            }

            $signale = $enseignant->signalements->contains(fn (Signalement $signalement) => $signalement->couvre($jour));
            $attendus++;
            if ($signale) {
                $signales++;
            }
            if ($signale || $datesPointees->contains($jour->toDateString())) {
                $valides++;
            }
        }

        // Le chef d'établissement n'a pas d'horaire opposable : ses jours
        // attendus sont tous réputés assurés, taux compris (voir
        // HoraireAttendu::assiduiteGarantie).
        $garantie = $this->horaires->assiduiteGarantie($enseignant);

        return [
            // Champ historique : les signalements valident aussi l'assiduite.
            'jours_presents' => $garantie ? $attendus : $valides,
            'jours_attendus' => $attendus,
            'jours_signales' => $signales,
            'jours_scannes' => $datesPointees->count(),
            'assiduite_garantie' => $garantie,
            'taux_assiduite' => match (true) {
                $garantie => 100.0,
                $attendus > 0 => round($valides / $attendus * 100, 1),
                default => 0.0,
            },
        ];
    }
}
