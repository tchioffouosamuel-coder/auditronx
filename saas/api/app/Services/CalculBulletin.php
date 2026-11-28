<?php

namespace App\Services;

use App\Models\Bulletin;
use App\Models\BulletinLigne;
use App\Models\Classe;
use App\Models\ClasseModule;
use App\Models\Inscription;
use App\Models\Module;
use App\Models\NoteModule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Calcul des bulletins d'une classe (§bulletins APC, lot 5).
 *
 * **Une passe pour toute la classe.** Le rang, la position dans le groupe et
 * les moyennes de classe dépendent de tous les élèves : générer élève par
 * élève obligerait à recalculer la classe à chaque fois, et laisserait des
 * bulletins de la même classe calculés sur des états différents.
 *
 * Ce service ne décide d'aucun seuil : il rassemble les notes selon la
 * maquette et les confie à [BaremeApc]. Ce qu'il décide, c'est **ce qui
 * entre** dans le calcul :
 *
 * - un module non programmé à la période est imprimé avec un tiret, hors
 *   totaux ;
 * - un module programmé dont la note manque est marqué `note_manquante`. Le
 *   bulletin reste calculable pour que le conseil voie où il en est, mais il
 *   n'est **ni classé, ni assorti d'une décision, ni validable** : sa moyenne
 *   serait celle des seuls modules notés, plus flatteuse que la vraie. Une
 *   absence à l'épreuve se traduit par un zéro saisi, pas par une case vide —
 *   le zéro est un geste, la case vide un oubli.
 */
class CalculBulletin
{
    /**
     * Calcule et enregistre les bulletins courants d'une classe pour une
     * période.
     *
     * Un brouillon est réécrit ; un bulletin validé bloque la génération — il
     * faut le rouvrir (voir ConseilClasse::rouvrir), ce qui en garde la trace
     * et fait de la prochaine génération une nouvelle version.
     *
     * @return Collection<int, Bulletin> les bulletins générés, par rang
     */
    public static function generer(Classe $classe, string $anneeScolaire, string $periode, ?int $auteurId = null): Collection
    {
        $donnees = self::donnees($classe, $anneeScolaire, $periode);

        self::verifierGenerable($classe, $anneeScolaire, $periode, $donnees);

        $calculs = self::calculer($donnees, $periode);
        $empreinte = self::empreinteDe($donnees);
        ['type' => $type, 'trimestre' => $trimestre] = Bulletin::decoderPeriode($periode);

        return DB::transaction(function () use ($classe, $anneeScolaire, $periode, $type, $trimestre, $donnees, $calculs, $empreinte, $auteurId) {
            $existants = Bulletin::duConseil($classe->id, $anneeScolaire, $periode)
                ->whereIn('inscription_id', $donnees['inscriptions']->pluck('id'))
                ->get()
                ->groupBy('inscription_id');

            // Un brouillon d'un élève sorti de la liste (parti, changé de
            // classe) n'a été remis à personne : il disparaît avec lui. Un
            // bulletin validé, lui, reste — il a été signé.
            Bulletin::duConseil($classe->id, $anneeScolaire, $periode)
                ->courants()
                ->where('statut', Bulletin::STATUT_BROUILLON)
                ->whereNotIn('inscription_id', $donnees['inscriptions']->pluck('id'))
                ->delete();

            $maintenant = now();
            $bulletins = collect();

            foreach ($donnees['inscriptions'] as $inscription) {
                $calcul = $calculs[$inscription->id];
                $versions = $existants->get($inscription->id, collect())->sortByDesc('version');
                $courant = $versions->firstWhere('remplace_at', null);
                $precedent = $versions->first();

                $valeurs = [
                    ...$calcul['bulletin'],
                    'classe_code' => $classe->code,
                    'classe_nom' => $classe->nom,
                    'statut_inscription' => $inscription->statut,
                    'statut' => Bulletin::STATUT_BROUILLON,
                    'empreinte' => $empreinte,
                    // Recopié pour l'impression : la légende des mentions
                    // d'une réimpression doit être celle du calcul.
                    'bareme' => BaremeApc::valeurs(),
                    'genere_at' => $maintenant,
                    'genere_par' => $auteurId,
                ];

                if ($courant !== null) {
                    // Brouillon réécrit : la décision du conseil et
                    // l'observation déjà saisies ne bougent pas, elles ne
                    // sont pas dans $valeurs.
                    $courant->update($valeurs);
                    $courant->lignes()->delete();
                    $bulletin = $courant;
                } else {
                    // Nouvelle version après réouverture : on reprend ce que
                    // le conseil avait écrit, il n'a pas à tout ressaisir.
                    $bulletin = Bulletin::create([
                        ...$valeurs,
                        'inscription_id' => $inscription->id,
                        'classe_id' => $classe->id,
                        'annee_scolaire' => $anneeScolaire,
                        'periode' => $periode,
                        'type' => $type,
                        'trimestre' => $trimestre,
                        'version' => ($precedent?->version ?? 0) + 1,
                        'decision_conseil' => $precedent?->decision_conseil,
                        'decision_conseil_par' => $precedent?->decision_conseil_par,
                        'decision_conseil_at' => $precedent?->decision_conseil_at,
                        'observation' => $precedent?->observation,
                    ]);
                }

                $bulletin->lignes()->createMany($calcul['lignes']);

                $bulletins->push($bulletin);
            }

            return $bulletins->sortBy(fn (Bulletin $b) => sprintf('%05d', $b->rang ?? 99999))->values();
        });
    }

    /**
     * Empreinte de ce dont dépendrait un calcul fait maintenant.
     *
     * Comparée à celle d'un bulletin, elle dit s'il est encore à jour : une
     * note corrigée, un crédit changé, un élève arrivé ou un seuil déplacé
     * depuis la génération la font changer.
     */
    public static function empreinte(Classe $classe, string $anneeScolaire, string $periode): string
    {
        return self::empreinteDe(self::donnees($classe, $anneeScolaire, $periode));
    }

    /**
     * Les trimestres dont une période dépend.
     *
     * @return array<int, int>
     */
    public static function trimestresDe(string $periode): array
    {
        return $periode === Bulletin::PERIODE_ANNUELLE
            ? range(1, BaremeApc::nombreTrimestres())
            : [(int) $periode];
    }

    /* ---------------------------------------------------------------- */
    /* Lecture                                                           */
    /* ---------------------------------------------------------------- */

    /**
     * Tout ce dont le calcul dépend, lu en une fois.
     *
     * @return array{lignes: Collection<int, ClasseModule>, inscriptions: Collection<int, Inscription>, notes: array<string, NoteModule>}
     */
    private static function donnees(Classe $classe, string $anneeScolaire, string $periode): array
    {
        $lignes = MaquetteClasse::lignes($classe);
        $inscriptions = SaisieNotes::inscriptions($classe, $anneeScolaire);

        $notes = NoteModule::query()
            ->whereIn('classe_module_id', $lignes->pluck('id'))
            ->whereIn('inscription_id', $inscriptions->pluck('id'))
            ->whereIn('trimestre', self::trimestresDe($periode))
            ->get()
            ->keyBy(fn (NoteModule $note) => self::cle($note->inscription_id, $note->classe_module_id, $note->trimestre))
            ->all();

        return ['lignes' => $lignes, 'inscriptions' => $inscriptions, 'notes' => $notes];
    }

    /**
     * Ce qui empêche toute génération, pour la classe entière.
     *
     * Un crédit manquant n'est pas un défaut d'un élève mais de la maquette :
     * BaremeApc écarterait le module des totaux sans le dire, et tous les
     * bulletins de la classe auraient une moyenne fausse.
     */
    private static function verifierGenerable(Classe $classe, string $anneeScolaire, string $periode, array $donnees): void
    {
        $valides = Bulletin::duConseil($classe->id, $anneeScolaire, $periode)
            ->courants()
            ->where('statut', '!=', Bulletin::STATUT_BROUILLON)
            ->exists();

        if ($valides) {
            throw ValidationException::withMessages([
                'periode' => 'Les bulletins de cette période sont validés. Rouvrez-les avant de les recalculer : '
                    .'la version signée sera conservée.',
            ]);
        }

        if ($donnees['lignes']->isEmpty()) {
            throw ValidationException::withMessages([
                'classe_id' => "La maquette de la classe {$classe->code} est vide : il n'y a rien à mettre au bulletin.",
            ]);
        }

        if ($donnees['inscriptions']->isEmpty()) {
            throw ValidationException::withMessages([
                'classe_id' => "Aucun élève inscrit en {$classe->code} pour {$anneeScolaire}.",
            ]);
        }

        $trimestres = self::trimestresDe($periode);
        $sansCredit = $donnees['lignes']
            ->filter(fn (ClasseModule $ligne) => $ligne->credit === null
                && array_intersect($ligne->trimestresProgrammes(), $trimestres) !== [])
            ->map(fn (ClasseModule $ligne) => $ligne->module?->code ?? (string) $ligne->module_id)
            ->values()
            ->all();

        if ($sansCredit !== []) {
            throw ValidationException::withMessages([
                'classe_id' => 'Crédit à saisir sur la maquette pour : '.implode(', ', $sansCredit)
                    .'. Sans crédit, ces modules sortiraient de la moyenne sans que le bulletin le dise.',
            ]);
        }
    }

    /* ---------------------------------------------------------------- */
    /* Calcul                                                            */
    /* ---------------------------------------------------------------- */

    /**
     * Les bulletins de la classe, sans rien écrire.
     *
     * @return array<int, array{bulletin: array<string, mixed>, lignes: array<int, array<string, mixed>>}>
     */
    private static function calculer(array $donnees, string $periode): array
    {
        $annuel = $periode === Bulletin::PERIODE_ANNUELLE;
        $resultats = [];

        foreach ($donnees['inscriptions'] as $inscription) {
            $lignes = [];

            foreach ($donnees['lignes'] as $rang => $ligne) {
                $lignes[] = $annuel
                    ? self::ligneAnnuelle($ligne, $inscription, $donnees['notes'], $rang)
                    : self::ligneTrimestre($ligne, $inscription, $donnees['notes'], (int) $periode, $rang);
            }

            $synthese = BaremeApc::synthese(array_map(fn (array $l) => [
                'note' => $l['hors_totaux'] ? null : $l['note_finale'],
                'credit' => $l['credit'],
                'volume_horaire' => $l['volume_horaire'],
            ], $lignes));

            $manquantes = count(array_filter($lignes, fn (array $l) => $l['note_manquante']));
            $complet = $manquantes === 0;

            $resultats[$inscription->id] = [
                'bulletin' => [
                    'moyenne_generale' => $synthese['moyenne_generale'],
                    'total_credits' => $synthese['total_credits'],
                    'total_volume_horaire' => $synthese['total_volume_horaire'],
                    'modules_notes' => $synthese['modules_notes'],
                    'competences_non_validees' => $synthese['competences_non_validees'],
                    'notes_manquantes' => $manquantes,
                    'mention' => $synthese['mention'],
                    // Une compétence dont la note manque n'est pas validée :
                    // « OUI » ne s'imprime que sur un bulletin complet.
                    'validation_competences' => $complet ? $synthese['validation_competences'] : BaremeApc::VALIDATION_NON_PARTIEL,
                    'decision_proposee' => $complet
                        ? ($annuel
                            ? BaremeApc::decisionAnnuelle($synthese['moyenne_generale'], $synthese['competences_non_validees'])
                            : BaremeApc::decisionTrimestre($synthese['moyenne_generale'], $synthese['competences_non_validees']))
                        : null,
                ],
                'lignes' => $lignes,
            ];
        }

        return self::classer($resultats, $donnees['inscriptions']->count());
    }

    /**
     * Rangs, position dans le groupe et moyennes de classe.
     *
     * Ne sont classés que les bulletins complets : un élève à qui il manque
     * une note n'a pas de moyenne comparable. Les ex aequo partagent leur rang
     * et le suivant le saute (1, 2, 2, 4) — la comparaison porte sur la
     * moyenne **arrondie**, celle que lit le parent.
     *
     * La position dans le groupe se calcule sur les élèves classés : un
     * bulletin incomplet ne doit pas pousser les autres vers le bas.
     */
    private static function classer(array $resultats, int $effectif): array
    {
        $moyennes = [];
        foreach ($resultats as $inscriptionId => $resultat) {
            $b = $resultat['bulletin'];
            if ($b['notes_manquantes'] === 0 && $b['moyenne_generale'] !== null) {
                $moyennes[$inscriptionId] = $b['moyenne_generale'];
            }
        }

        $classes = count($moyennes);
        $occurrences = array_count_values(array_map(fn (float $m) => (string) $m, $moyennes));

        $statistiques = [
            'moyenne_classe' => $classes === 0 ? null : BaremeApc::arrondir(array_sum($moyennes) / $classes),
            'moyenne_max' => $classes === 0 ? null : max($moyennes),
            'moyenne_min' => $classes === 0 ? null : min($moyennes),
        ];

        foreach ($resultats as $inscriptionId => &$resultat) {
            $moyenne = $moyennes[$inscriptionId] ?? null;
            $rang = $moyenne === null
                ? null
                : 1 + count(array_filter($moyennes, fn (float $autre) => $autre > $moyenne));

            $resultat['bulletin'] += [
                'rang' => $rang,
                'ex_aequo' => $moyenne !== null && $occurrences[(string) $moyenne] > 1,
                'effectif' => $effectif,
                'effectif_classe' => $classes,
                'position_groupe' => BaremeApc::positionGroupe($rang, $classes),
                ...$statistiques,
            ];
        }

        return $resultats;
    }

    /** @param array<string, NoteModule> $notes */
    private static function ligneTrimestre(ClasseModule $ligne, Inscription $inscription, array $notes, int $trimestre, int $rang): array
    {
        $programme = $ligne->estProgrammeAu($trimestre);
        $note = $programme ? ($notes[self::cle($inscription->id, $ligne->id, $trimestre)] ?? null) : null;
        $finale = $note?->note_finale;

        return [
            ...self::identiteLigne($ligne, $rang),
            'hors_totaux' => ! $programme,
            'moyenne_uea' => $note?->moyenne_uea,
            'note_integratrice' => $note?->note_integratrice,
            'notes_trimestres' => [$trimestre => $finale],
            'note_finale' => $finale,
            'note_manquante' => $programme && $finale === null,
            'mention' => BaremeApc::mention($finale),
            'sanction' => BaremeApc::sanction($finale),
            // L'appréciation saisie par l'enseignant prime sur la légende du
            // barème ; elle n'est stockée que si elle en diffère.
            'appreciation' => $note?->appreciation ?? BaremeApc::appreciation($finale),
        ];
    }

    /**
     * Ligne du bulletin annuel : la moyenne des trimestres où le module est
     * programmé. Un trimestre programmé sans note rend la ligne incomplète —
     * on n'imprime pas la moyenne de deux trimestres sur trois comme si
     * c'était l'année.
     *
     * @param  array<string, NoteModule>  $notes
     */
    private static function ligneAnnuelle(ClasseModule $ligne, Inscription $inscription, array $notes, int $rang): array
    {
        $programmes = array_values(array_intersect($ligne->trimestresProgrammes(), self::trimestresDe(Bulletin::PERIODE_ANNUELLE)));

        $parTrimestre = [];
        foreach (self::trimestresDe(Bulletin::PERIODE_ANNUELLE) as $t) {
            $parTrimestre[$t] = in_array($t, $programmes, true)
                ? ($notes[self::cle($inscription->id, $ligne->id, $t)] ?? null)?->note_finale
                : null;
        }

        $programmees = array_intersect_key($parTrimestre, array_flip($programmes));
        $manquante = in_array(null, $programmees, true);
        $finale = $manquante ? null : BaremeApc::noteFinaleAnnuelle($programmees);

        return [
            ...self::identiteLigne($ligne, $rang),
            'hors_totaux' => $programmes === [],
            'moyenne_uea' => null,
            'note_integratrice' => null,
            'notes_trimestres' => $parTrimestre,
            'note_finale' => $finale,
            'note_manquante' => $manquante,
            'mention' => BaremeApc::mention($finale),
            'sanction' => BaremeApc::sanction($finale),
            'appreciation' => BaremeApc::appreciation($finale),
        ];
    }

    /** Ce que la ligne recopie de la maquette et du référentiel. */
    private static function identiteLigne(ClasseModule $ligne, int $rang): array
    {
        return [
            'classe_module_id' => $ligne->id,
            'module_id' => $ligne->module_id,
            'ordre' => $rang,
            'code' => $ligne->module?->code ?? '',
            'intitule' => $ligne->module?->intitule ?? '',
            'bloc' => $ligne->module?->bloc ?? Module::BLOC_COMPETENCES_PARTICULIERES,
            'credit' => $ligne->credit,
            'volume_horaire' => $ligne->volume_horaire,
            'trimestres' => $ligne->trimestresProgrammes(),
            'signataire' => $ligne->enseignant?->nom,
        ];
    }

    /* ---------------------------------------------------------------- */
    /* Empreinte                                                         */
    /* ---------------------------------------------------------------- */

    private static function empreinteDe(array $donnees): string
    {
        $etat = [
            'bareme' => BaremeApc::valeurs(),
            'maquette' => $donnees['lignes']->map(fn (ClasseModule $l) => [
                $l->id, $l->credit, $l->volume_horaire, $l->trimestresProgrammes(), $l->enseignant_id, $l->ordre,
                $l->module?->code, $l->module?->intitule, $l->module?->bloc,
            ])->all(),
            'eleves' => $donnees['inscriptions']->map(fn (Inscription $i) => [$i->id, $i->statut])->sortBy(0)->values()->all(),
            'notes' => collect($donnees['notes'])
                ->map(fn (NoteModule $n) => [$n->moyenne_uea, $n->note_integratrice, $n->appreciation])
                ->sortKeys()
                ->all(),
        ];

        return hash('sha256', (string) json_encode($etat));
    }

    private static function cle(int $inscriptionId, int $classeModuleId, int $trimestre): string
    {
        return "{$inscriptionId}:{$classeModuleId}:{$trimestre}";
    }
}
