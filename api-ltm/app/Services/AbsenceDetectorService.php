<?php

namespace App\Services;

use App\Models\AbsenceAlertLog;
use App\Models\AbsenceCheckpoint;
use App\Models\Enseignant;
use App\Models\Presence;
use Carbon\Carbon;

/**
 * Détecte les absences répétées (§4.2 — Alertes) : pour chaque personne attendue
 * un jour donné (cours ou journée administrative) mais sans pointage, incrémente son compteur d'absences
 * consécutives et déclenche une alerte au-delà du seuil configuré.
 */
class AbsenceDetectorService
{
    public const SEUIL_ALERTE = 3;

    public function __construct(private HoraireAttendu $horaires) {}

    public function detecterPour(Carbon $date): void
    {
        // Attendus ce jour : enseignants ayant cours + personnel administratif
        // un jour ouvré de son horaire fixe (voir HoraireAttendu).
        Enseignant::with('emploiDuTemps')->get()
            ->filter(fn (Enseignant $enseignant) => $this->horaires->absenceEvaluable($enseignant, $date))
            ->each(function (Enseignant $enseignant) use ($date) {
                $aPointe = Presence::where('enseignant_id', $enseignant->id)
                    ->whereDate('date', $date->toDateString())
                    ->whereNotNull('heure_arrivee')
                    ->exists();

                $veille = AbsenceCheckpoint::where('enseignant_id', $enseignant->id)
                    ->where('date', $date->copy()->subDay()->toDateString())
                    ->first();

                $consecutives = $aPointe ? 0 : ($veille?->absences_consecutives ?? 0) + 1;

                $checkpoint = AbsenceCheckpoint::updateOrCreate(
                    ['enseignant_id' => $enseignant->id, 'date' => $date->toDateString()],
                    ['absences_consecutives' => $consecutives]
                );

                if ($consecutives >= self::SEUIL_ALERTE) {
                    AbsenceAlertLog::create([
                        'enseignant_id' => $enseignant->id,
                        'absence_checkpoint_id' => $checkpoint->id,
                        'sent_at' => now(),
                        'canal' => 'mail',
                    ]);

                    // TODO: brancher l'envoi effectif (Mail::to(...)->send(new AbsenceAlert($enseignant))).
                }
            });
    }
}
