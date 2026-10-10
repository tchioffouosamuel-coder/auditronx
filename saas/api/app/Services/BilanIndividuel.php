<?php

namespace App\Services;

use App\Models\Enseignant;
use Carbon\Carbon;

/**
 * Bilan individuel d'assiduité sur une période (§4.2 — Retards & bilans) :
 * jours attendus, présences, retards, départs anticipés, périodes à rattraper
 * et taux d'assiduité. Source unique des chiffres de la fiche PDF individuelle
 * (RetardsController::bilanIndividuel) ET de l'export groupé en ZIP
 * (StatistiquesController::exportZip), pour qu'un même enseignant ait le même
 * bilan quel que soit l'export.
 *
 * Les jours attendus viennent de HoraireAttendu : cours du jour pour un
 * enseignant, journée de travail fixe pour le personnel de la section
 * « Administration ».
 */
class BilanIndividuel
{
    public function __construct(
        private readonly RetardCalculator $retards,
        private readonly HoraireAttendu $horaires,
    ) {}

    /** Données attendues par la vue `pdf.retards-individuel` (hors `debut`/`fin`). */
    public function donnees(Enseignant $enseignant, Carbon $debut, Carbon $fin): array
    {
        $emplois = $enseignant->emploiDuTemps()->with(['discipline', 'classe'])->orderBy('jour')->orderBy('heure_debut')->get();
        $emploiParJour = $emplois->groupBy(fn($emploi) => $this->nomJour($emploi->jour));
        $presences = $enseignant->presences()
            ->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])->get()
            ->keyBy(fn($presence) => $presence->date->toDateString());
        $signalements = $enseignant->signalements()
            ->whereDate('date', '<=', $fin->toDateString())->get();
        $details = [];
        $joursAttendus = $presencesValides = 0;
        $totalRetard = $totalAnticipation = $totalPeriodesPresence = $totalPeriodesAbsence = 0;

        for ($date = $debut->copy(); $date->lte($fin); $date->addDay()) {
            // Enseignant : jours avec cours ; personnel administratif : journée
            // de travail fixe, sans emploi du temps (voir HoraireAttendu).
            $plage = $this->horaires->plage($enseignant, $date, $emplois);
            if (! $plage) continue;
            $coursDuJour = $emplois->where('jour', $date->isoWeekday())->values();
            $periodesPrevues = max(1, (int) ceil($plage['minutes'] / 40));
            $presence = $presences->get($date->toDateString());
            $signalement = $signalements->first(fn($item) => $date->between($item->date, $item->date->copy()->addDays(max(0, $item->duree_jours - 1))));
            $futur = $date->isFuture();
            $heureFin = Carbon::parse($date->toDateString() . ' ' . $plage['heure_fin']);
            $retard = $presence?->heure_arrivee ? $this->retards->minutesDeRetard($enseignant, $presence) ?? 0 : 0;
            $anticipation = $presence?->heure_depart ? max(0, (int) floor(($heureFin->timestamp - $presence->heure_depart->timestamp) / 60)) : 0;
            $signale = $signalement !== null;
            $absent = !$futur && !$signale && (!$presence || (!$presence->heure_arrivee && !$presence->heure_depart));
            $periodesRattraper = $absent ? $periodesPrevues : (int) ceil($anticipation / 40);
            $valide = $signale || (bool) ($presence?->heure_arrivee || $presence?->heure_depart);
            $joursAttendus++;
            if ($valide && !$futur) $presencesValides++;
            if (!$futur) {
                $totalRetard += $retard;
                $totalAnticipation += $anticipation;
                if ($absent) $totalPeriodesAbsence += $periodesPrevues;
                else $totalPeriodesPresence += $periodesRattraper;
            }
            $details[] = [
                'date' => $date->format('d/m/Y'),
                'jour' => $this->nomJour($date->isoWeekday()),
                'nb_cours' => $coursDuJour->count(),
                'heure_debut_prevue' => $plage['heure_debut'],
                'heure_fin_prevue' => $plage['heure_fin'],
                'heure_arrivee' => $presence?->heure_arrivee?->format('H:i'),
                'heure_depart' => $presence?->heure_depart?->format('H:i'),
                'retard_minutes' => $retard,
                'anticipation_minutes' => $anticipation,
                'periodes_a_rattraper' => $periodesRattraper,
                'futur' => $futur,
                'absent' => $absent,
                'est_signale' => $signale,
                'type_signalement' => $signalement?->motif,
            ];
        }

        return [
            'enseignant' => $enseignant,
            'mois' => $debut->format('m'),
            'annee' => $debut->year,
            'emploi_par_jour' => $emploiParJour,
            'horaire_administratif' => $this->horaires->estAdministratif($enseignant) ? $this->horaires->parametresAdministratifs() : null,
            'details' => $details,
            'total_retard_minutes' => $totalRetard,
            'total_anticipation_minutes' => $totalAnticipation,
            'total_periodes_a_rattraper' => $totalPeriodesPresence,
            'total_periodes_absence' => $totalPeriodesAbsence,
            'jours_attendus' => $joursAttendus,
            'presences_valides' => $presencesValides,
            'taux_assiduite' => $joursAttendus > 0 ? $presencesValides / $joursAttendus * 100 : 0,
        ];
    }

    private function nomJour(int $jour): string
    {
        return [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'][$jour] ?? 'Inconnu';
    }
}
