<?php

namespace App\Services;

use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Inscription;
use App\Models\NoteModule;
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Conseil de classe (§bulletins APC, lot 5) : ce que le conseil voit, ce
 * qu'il peut changer, et le geste qui fige.
 *
 * Le calcul propose, le conseil dispose. Les décisions du barème sont des
 * propositions ; le conseil les modifie bulletin par bulletin, avec son nom
 * et la date, puis **valide la classe entière** — on ne signe pas la moitié
 * d'un PV. La validation verrouille les notes qui ont servi : modifier une
 * note derrière un bulletin signé exige de le rouvrir, et cela se voit.
 */
class ConseilClasse
{
    /**
     * L'état d'un conseil : les élèves triés par rang, leur bulletin courant,
     * et ce qui empêcherait de valider.
     *
     * @return array<string, mixed>
     */
    public static function etat(Classe $classe, string $anneeScolaire, string $periode): array
    {
        ['type' => $type, 'trimestre' => $trimestre] = Bulletin::decoderPeriode($periode);

        $inscriptions = SaisieNotes::inscriptions($classe, $anneeScolaire);
        $bulletins = self::courants($classe, $anneeScolaire, $periode)->keyBy('inscription_id');
        $empreinte = $bulletins->isEmpty() ? null : CalculBulletin::empreinte($classe, $anneeScolaire, $periode);

        $eleves = $inscriptions->map(fn (Inscription $inscription) => [
            'inscription_id' => $inscription->id,
            'matricule' => $inscription->eleve?->matricule,
            'nom_complet' => $inscription->eleve?->nom_complet,
            'statut_inscription' => $inscription->statut,
            'bulletin' => ($b = $bulletins->get($inscription->id)) ? self::resume($b) : null,
        ])
            // Par rang ; les non classés à la fin, dans l'ordre de la liste.
            ->sortBy(fn (array $e, int $i) => sprintf('%05d-%05d', $e['bulletin']['rang'] ?? 99999, $i))
            ->values();

        $premier = $bulletins->first();
        $statut = match (true) {
            $bulletins->isEmpty() => 'aucun',
            $bulletins->every(fn (Bulletin $b) => $b->estValide()) => Bulletin::STATUT_VALIDE,
            default => Bulletin::STATUT_BROUILLON,
        };

        return [
            'classe' => $classe->only(['id', 'code', 'nom', 'niveau']),
            'annee_scolaire' => $anneeScolaire,
            'periode' => $periode,
            'type' => $type,
            'trimestre' => $trimestre,
            'statut' => $statut,
            'a_jour' => $bulletins->isEmpty() || $bulletins->every(fn (Bulletin $b) => $b->empreinte === $empreinte),
            'genere_at' => $premier?->genere_at?->toIso8601String(),
            'valide_at' => $premier?->valide_at?->toIso8601String(),
            'effectif' => $inscriptions->count(),
            'effectif_classe' => $premier?->effectif_classe,
            'moyenne_classe' => $premier?->moyenne_classe,
            'moyenne_max' => $premier?->moyenne_max,
            'moyenne_min' => $premier?->moyenne_min,
            'obstacles' => $statut === Bulletin::STATUT_BROUILLON
                ? self::obstaclesValidation($classe, $anneeScolaire, $periode, $bulletins->values(), $empreinte)
                : [],
            'avancement' => array_map(
                fn (int $t) => ['trimestre' => $t, 'prete' => SaisieNotes::avancement($classe, $t, $anneeScolaire)['prete']],
                CalculBulletin::trimestresDe($periode),
            ),
            'decisions_possibles' => array_map(
                fn (string $d) => ['valeur' => $d, 'libelle' => BaremeApc::libelleDecision($d)],
                Bulletin::decisionsDuType($type),
            ),
            'eleves' => $eleves->all(),
            'versions_remplacees' => Bulletin::duConseil($classe->id, $anneeScolaire, $periode)
                ->whereNotNull('remplace_at')->count(),
        ];
    }

    /**
     * Décision et observation du conseil sur un bulletin.
     *
     * Une décision égale à la proposition n'est pas stockée comme un choix du
     * conseil : elle effacerait la différence entre « le conseil a suivi » et
     * « le conseil a tranché », que le PV doit pouvoir montrer.
     *
     * @param  array{decision?: ?string, observation?: ?string}  $valeurs
     */
    public static function modifier(Bulletin $bulletin, array $valeurs, ?int $auteurId): Bulletin
    {
        if ($bulletin->remplace_at !== null || $bulletin->estValide()) {
            throw ValidationException::withMessages([
                'bulletin' => 'Ce bulletin est validé ou remplacé : rouvrez le conseil pour le modifier.',
            ]);
        }

        if (array_key_exists('decision', $valeurs)) {
            $decision = $valeurs['decision'];

            if ($decision !== null && ! in_array($decision, $bulletin->decisionsPossibles(), true)) {
                throw ValidationException::withMessages([
                    'decision' => 'Décision inconnue pour un bulletin '.($bulletin->estAnnuel() ? 'annuel' : 'trimestriel').'.',
                ]);
            }

            $choix = $decision === $bulletin->decision_proposee ? null : $decision;

            if ($choix !== $bulletin->decision_conseil) {
                $bulletin->fill([
                    'decision_conseil' => $choix,
                    'decision_conseil_par' => $choix === null ? null : $auteurId,
                    'decision_conseil_at' => $choix === null ? null : now(),
                ]);
            }
        }

        if (array_key_exists('observation', $valeurs)) {
            $observation = trim((string) $valeurs['observation']);
            $bulletin->observation = $observation === '' ? null : $observation;
        }

        $bulletin->save();

        return $bulletin;
    }

    /**
     * Fige les bulletins de la classe et verrouille les notes qui ont servi.
     *
     * @return Collection<int, Bulletin>
     */
    public static function valider(Classe $classe, string $anneeScolaire, string $periode, ?int $auteurId): Collection
    {
        $bulletins = self::courants($classe, $anneeScolaire, $periode);

        if ($bulletins->isNotEmpty() && $bulletins->every(fn (Bulletin $b) => $b->estValide())) {
            throw ValidationException::withMessages(['periode' => 'Ces bulletins sont déjà validés.']);
        }

        $empreinte = $bulletins->isEmpty() ? null : CalculBulletin::empreinte($classe, $anneeScolaire, $periode);
        $obstacles = self::obstaclesValidation($classe, $anneeScolaire, $periode, $bulletins, $empreinte);

        if ($obstacles !== []) {
            throw ValidationException::withMessages(['periode' => $obstacles]);
        }

        return DB::transaction(function () use ($classe, $anneeScolaire, $periode, $bulletins, $auteurId) {
            $maintenant = now();

            foreach ($bulletins as $bulletin) {
                $bulletin->update([
                    'statut' => Bulletin::STATUT_VALIDE,
                    'valide_at' => $maintenant,
                    'valide_par' => $auteurId,
                ]);
            }

            self::synchroniserVerrous($classe, $anneeScolaire);

            Audit::enregistrer('conseil.valide', $classe, [], [
                'annee_scolaire' => $anneeScolaire,
                'periode' => $periode,
                'bulletins' => $bulletins->count(),
            ]);

            return $bulletins;
        });
    }

    /**
     * Rouvre un conseil validé : les bulletins signés sont marqués remplacés
     * — jamais effacés — et les notes déverrouillées. La génération suivante
     * produira la version n+1.
     *
     * Un trimestre ne se rouvre pas tant que le bulletin annuel est validé :
     * l'annuel a été calculé sur ces notes, et ses verrous les retiendraient
     * de toute façon. Mieux vaut le dire que de laisser croire que c'est
     * rouvert.
     */
    public static function rouvrir(Classe $classe, string $anneeScolaire, string $periode, string $motif, ?int $auteurId): int
    {
        $bulletins = self::courants($classe, $anneeScolaire, $periode)->filter(fn (Bulletin $b) => $b->estValide());

        if ($bulletins->isEmpty()) {
            throw ValidationException::withMessages(['periode' => 'Aucun bulletin validé à rouvrir pour cette période.']);
        }

        if ($periode !== Bulletin::PERIODE_ANNUELLE && self::annuelValide($classe, $anneeScolaire)) {
            throw ValidationException::withMessages([
                'periode' => 'Le bulletin annuel de la classe est validé et repose sur ces notes : rouvrez-le d\'abord.',
            ]);
        }

        DB::transaction(function () use ($classe, $anneeScolaire, $periode, $bulletins, $motif, $auteurId) {
            $maintenant = now();

            foreach ($bulletins as $bulletin) {
                $bulletin->update([
                    'remplace_at' => $maintenant,
                    'remplace_par' => $auteurId,
                    'motif_reouverture' => $motif,
                ]);
            }

            self::synchroniserVerrous($classe, $anneeScolaire);

            Audit::enregistrer('conseil.rouvert', $classe, [], [
                'annee_scolaire' => $anneeScolaire,
                'periode' => $periode,
                'bulletins' => $bulletins->count(),
                'motif' => $motif,
            ]);
        });

        return $bulletins->count();
    }

    /**
     * Pose ou retire les verrous des notes d'une classe pour une année,
     * d'après les bulletins validés en vigueur.
     *
     * Recalculé en entier plutôt que posé et retiré au coup par coup : une
     * note du deuxième trimestre est tenue à la fois par le bulletin du
     * trimestre et par l'annuel, et rouvrir l'un ne doit pas libérer ce que
     * l'autre retient.
     */
    public static function synchroniserVerrous(Classe $classe, string $anneeScolaire): void
    {
        $periodes = Bulletin::query()
            ->where('classe_id', $classe->id)
            ->where('annee_scolaire', $anneeScolaire)
            ->courants()
            ->where('statut', '!=', Bulletin::STATUT_BROUILLON)
            ->distinct()
            ->pluck('periode');

        $trimestres = $periodes
            ->flatMap(fn (string $periode) => CalculBulletin::trimestresDe($periode))
            ->unique()
            ->values()
            ->all();

        $notes = NoteModule::query()
            ->whereIn('classe_module_id', $classe->maquette()->select('id'))
            ->whereIn('inscription_id', Inscription::where('annee_scolaire', $anneeScolaire)->select('id'));

        (clone $notes)->whereIn('trimestre', $trimestres)->whereNull('verrouillee_at')->update(['verrouillee_at' => now()]);
        (clone $notes)->whereNotIn('trimestre', $trimestres)->whereNotNull('verrouillee_at')->update(['verrouillee_at' => null]);
    }

    /**
     * Ce qui empêche de valider, en phrases.
     *
     * @param  Collection<int, Bulletin>  $bulletins
     * @return array<int, string>
     */
    private static function obstaclesValidation(Classe $classe, string $anneeScolaire, string $periode, Collection $bulletins, ?string $empreinte): array
    {
        if ($bulletins->isEmpty()) {
            return ['Les bulletins n\'ont pas encore été calculés.'];
        }

        $obstacles = [];

        if ($bulletins->contains(fn (Bulletin $b) => $b->empreinte !== $empreinte)) {
            $obstacles[] = 'Des notes, la maquette, la liste de classe ou le barème ont changé depuis le calcul : recalculez les bulletins.';
        }

        $incomplets = $bulletins->filter(fn (Bulletin $b) => ! $b->estComplet());
        if ($incomplets->isNotEmpty()) {
            $noms = $incomplets->take(5)->map(fn (Bulletin $b) => $b->inscription?->eleve?->nom_complet ?? "#{$b->inscription_id}");
            $obstacles[] = $incomplets->count().' bulletin(s) incomplet(s) — '.$noms->implode(', ')
                .($incomplets->count() > 5 ? '…' : '').'. Saisissez les notes manquantes (0 pour une absence).';
        }

        $sansBulletin = SaisieNotes::inscriptions($classe, $anneeScolaire)->pluck('id')
            ->diff($bulletins->pluck('inscription_id'));
        if ($sansBulletin->isNotEmpty()) {
            $obstacles[] = $sansBulletin->count().' élève(s) sans bulletin : recalculez les bulletins.';
        }

        return $obstacles;
    }

    /** @return Collection<int, Bulletin> */
    private static function courants(Classe $classe, string $anneeScolaire, string $periode): Collection
    {
        return Bulletin::duConseil($classe->id, $anneeScolaire, $periode)
            ->courants()
            ->with('inscription.eleve')
            ->get();
    }

    private static function annuelValide(Classe $classe, string $anneeScolaire): bool
    {
        return Bulletin::duConseil($classe->id, $anneeScolaire, Bulletin::PERIODE_ANNUELLE)
            ->courants()
            ->where('statut', '!=', Bulletin::STATUT_BROUILLON)
            ->exists();
    }

    /** @return array<string, mixed> */
    public static function resume(Bulletin $b): array
    {
        return [
            'id' => $b->id,
            'version' => $b->version,
            'statut' => $b->statut,
            'moyenne_generale' => $b->moyenne_generale,
            'mention' => $b->mention,
            'total_credits' => $b->total_credits,
            'total_volume_horaire' => $b->total_volume_horaire,
            'competences_non_validees' => $b->competences_non_validees,
            'notes_manquantes' => $b->notes_manquantes,
            'validation_competences' => $b->validation_competences,
            'rang' => $b->rang,
            'ex_aequo' => $b->ex_aequo,
            'position_groupe' => $b->position_groupe,
            'position_libelle' => BaremeApc::libellePosition($b->position_groupe),
            'decision_proposee' => $b->decision_proposee,
            'decision_conseil' => $b->decision_conseil,
            'decision' => $b->decisionRetenue(),
            'decision_libelle' => BaremeApc::libelleDecision($b->decisionRetenue()),
            'decision_conseil_at' => $b->decision_conseil_at?->toIso8601String(),
            'observation' => $b->observation,
        ];
    }
}
