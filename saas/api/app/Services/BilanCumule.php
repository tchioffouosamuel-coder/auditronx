<?php

namespace App\Services;

use App\Models\Enseignant;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Bilan cumulé d'une personne sur une période : retards, départs anticipés,
 * absences, périodes à rattraper et score de ponctualité.
 *
 * Ce calcul vivait dans `RetardsController` ; il sert désormais aussi aux
 * bilans mensuels (`AssiduiteController`), qui classent le personnel par
 * ponctualité. Un seul calcul, donc un seul classement : deux implémentations
 * finiraient par diverger et produiraient deux palmarès contradictoires pour
 * le même mois.
 */
class BilanCumule
{
    public function __construct(
        private readonly RetardCalculator $retards,
        private readonly HoraireAttendu $horaires,
        private readonly PonctualiteCalculator $ponctualite,
    ) {}

    /**
     * Lignes de toutes les personnes données, classées par ponctualité
     * décroissante (puis par nom, pour que deux scores égaux gardent un ordre
     * stable d'un export à l'autre).
     *
     * @param  Collection<int, Enseignant>  $enseignants
     * @return array<int, array<string, mixed>>
     */
    public function classement(Collection $enseignants, Carbon $debut, Carbon $fin): array
    {
        return $enseignants
            ->map(fn (Enseignant $enseignant) => $this->ligne($enseignant, $debut, $fin))
            ->sortBy(fn (array $ligne) => [-$ligne['score_ponctualite'], mb_strtolower($ligne['nom'])])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function ligne(Enseignant $enseignant, Carbon $debut, Carbon $fin): array
    {
        $emplois = $enseignant->emploiDuTemps()->get();
        $presences = $enseignant->presences()->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])->get()->keyBy(fn ($presence) => $presence->date->toDateString());
        $signalements = $enseignant->signalements()->whereDate('date', '<=', $fin->toDateString())->get();
        $result = ['enseignant_id' => $enseignant->id, 'nom' => $enseignant->nom, 'tel' => $enseignant->tel, 'matricule' => $enseignant->matricule, 'specialite' => $enseignant->section, 'section' => $enseignant->section, 'fonction' => $enseignant->fonction, 'nb_jours_retard' => 0, 'total_retard_minutes' => 0, 'nb_jours_anticipation' => 0, 'total_anticipation_minutes' => 0, 'nb_jours_absence' => 0, 'periodes_absence' => 0, 'periodes_presence' => 0, 'periodes_totales' => 0];
        $joursAttendus = $joursValides = $joursPonctuels = 0;

        for ($date = $debut->copy()->startOfDay(); $date->lte($fin) && ! $this->horaires->estAVenir($date); $date->addDay()) {
            // Enseignant : jours avec cours ; personnel administratif : journée
            // de travail fixe, sans emploi du temps (voir HoraireAttendu).
            $plage = $this->horaires->plage($enseignant, $date, $emplois);

            if (! $plage) {
                continue;
            }

            $presence = $presences->get($date->toDateString());
            $signale = $signalements->contains(fn ($item) => $item->couvre($date));
            $joursAttendus++;

            if ($signale || $presence?->heure_arrivee || $presence?->heure_depart) {
                $joursValides++;
            }

            $periodes = GrilleHoraire::periodesPourMinutes($plage['minutes']);
            $finPrevue = Carbon::parse($date->toDateString().' '.$plage['heure_fin']);
            $retard = $presence?->heure_arrivee ? ($this->retards->minutesDeRetard($enseignant, $presence) ?? 0) : 0;
            $anticipation = $presence?->heure_depart ? max(0, (int) floor(($finPrevue->timestamp - $presence->heure_depart->timestamp) / 60)) : 0;
            $absent = ! $signale && (! $presence || (! $presence->heure_arrivee && ! $presence->heure_depart));

            if (! $absent && $retard === 0) {
                $joursPonctuels++;
            }

            if ($retard > 0) {
                $result['nb_jours_retard']++;
                $result['total_retard_minutes'] += $retard;
            }

            if ($anticipation > 0) {
                $result['nb_jours_anticipation']++;
                $result['total_anticipation_minutes'] += $anticipation;
            }

            if ($absent) {
                $result['nb_jours_absence']++;
                $result['periodes_absence'] += $periodes;
            } else {
                $result['periodes_presence'] += (int) ceil($anticipation / GrilleHoraire::DUREE_PERIODE_MINUTES);
            }
        }

        $result['periodes_totales'] = $result['periodes_presence'] + $result['periodes_absence'];
        $result['taux_assiduite'] = $joursAttendus > 0 ? round($joursValides / $joursAttendus * 100, 1) : 0;

        return [...$result, ...$this->ponctualite->calculer($joursAttendus, $joursPonctuels, $result['total_retard_minutes'])];
    }
}
