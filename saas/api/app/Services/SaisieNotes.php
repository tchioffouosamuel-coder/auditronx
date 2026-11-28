<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\ClasseModule;
use App\Models\Inscription;
use App\Models\Module;
use App\Models\NoteModule;
use Illuminate\Support\Collection;

/**
 * Saisie des notes : ce qu'il y a à remplir, et où on en est.
 *
 * La grille est organisée **par module**, pas par élève : un conseil de classe
 * se prépare module par module, et un tableau de 40 élèves × 12 modules ne se
 * saisit ni à l'écran ni au clavier. C'est aussi ce que produit le classeur
 * hors ligne, un onglet par module.
 */
class SaisieNotes
{
    /**
     * Les lignes de maquette ouvertes à la saisie pour ce trimestre.
     *
     * Un module non programmé au trimestre en est exclu : y saisir une note
     * produirait une valeur que le bulletin annuel ignorerait sans rien dire,
     * puisque ce trimestre ne compte pas dans sa moyenne.
     *
     * @return Collection<int, ClasseModule>
     */
    public static function modulesSaisissables(Classe $classe, int $trimestre): Collection
    {
        return MaquetteClasse::lignes($classe)
            ->filter(fn (ClasseModule $ligne) => $ligne->estProgrammeAu($trimestre))
            ->values();
    }

    /**
     * Les inscriptions à remplir : actives, pour l'année, triées comme la
     * liste d'appel.
     *
     * @return Collection<int, Inscription>
     */
    public static function inscriptions(Classe $classe, string $anneeScolaire): Collection
    {
        return Inscription::activesDe($classe->id, $anneeScolaire)
            ->with('eleve')
            ->get()
            ->sortBy(fn (Inscription $inscription) => mb_strtolower(
                trim(($inscription->eleve?->nom ?? '').' '.($inscription->eleve?->prenoms ?? '')),
                'UTF-8',
            ))
            ->values();
    }

    /**
     * La grille d'un module : un élève par ligne, les notes déjà saisies en
     * regard.
     *
     * Les élèves sans note apparaissent avec des cases vides plutôt que d'être
     * absents de la liste — c'est une grille à remplir, pas un relevé de ce
     * qui existe.
     *
     * @return array<string, mixed>
     */
    public static function grille(Classe $classe, ClasseModule $ligne, int $trimestre, string $anneeScolaire): array
    {
        $inscriptions = self::inscriptions($classe, $anneeScolaire);

        $notes = NoteModule::where('classe_module_id', $ligne->id)
            ->where('trimestre', $trimestre)
            ->whereIn('inscription_id', $inscriptions->pluck('id'))
            ->get()
            ->keyBy('inscription_id');

        $repartition = BaremeApc::repartition();

        return [
            'module' => self::resumeModule($ligne),
            'trimestre' => $trimestre,
            'annee_scolaire' => $anneeScolaire,
            'bareme' => [
                'uea' => $repartition['uea'],
                'integratrice' => $repartition['integratrice'],
                'seuil_reussite' => BaremeApc::seuilReussite(),
                // Les bornes voyagent avec la grille : l'écran affiche la
                // mention en direct pendant la frappe, et sans elles il
                // laisserait la mention du serveur — périmée dès la première
                // correction — à côté d'une note qui a changé.
                'mentions' => BaremeApc::mentions(),
                'appreciations' => BaremeApc::APPRECIATIONS,
                'appreciation_non_acquise' => BaremeApc::APPRECIATION_NON_ACQUISE,
            ],
            'eleves' => $inscriptions->map(function (Inscription $inscription) use ($notes) {
                $note = $notes->get($inscription->id);

                return [
                    'inscription_id' => $inscription->id,
                    'matricule' => $inscription->eleve?->matricule,
                    'nom_complet' => $inscription->eleve?->nom_complet,
                    'moyenne_uea' => $note?->moyenne_uea,
                    'note_integratrice' => $note?->note_integratrice,
                    'note_finale' => $note?->note_finale,
                    'mention' => BaremeApc::mention($note?->note_finale),
                    'sanction' => BaremeApc::sanction($note?->note_finale),
                    'appreciation' => $note?->appreciation ?? BaremeApc::appreciation($note?->note_finale),
                    'verrouillee' => (bool) $note?->estVerrouillee(),
                ];
            })->all(),
        ];
    }

    /**
     * Où en est la saisie d'une classe, module par module.
     *
     * Ce n'est pas un confort : sans lui, personne ne découvre qu'il manque
     * une colonne avant le conseil de classe, c'est-à-dire trop tard.
     *
     * @return array<string, mixed>
     */
    public static function avancement(Classe $classe, int $trimestre, string $anneeScolaire): array
    {
        $lignes = self::modulesSaisissables($classe, $trimestre);
        $attendus = self::inscriptions($classe, $anneeScolaire)->count();

        // Un seul passage pour toute la classe : une requête par module
        // rendrait l'écran lent dès la deuxième filière.
        $completes = NoteModule::query()
            ->whereIn('classe_module_id', $lignes->pluck('id'))
            ->where('trimestre', $trimestre)
            ->whereNotNull('note_finale')
            ->selectRaw('classe_module_id, count(*) as total')
            ->groupBy('classe_module_id')
            ->pluck('total', 'classe_module_id');

        $modules = $lignes->map(function (ClasseModule $ligne) use ($completes, $attendus) {
            $saisies = (int) ($completes[$ligne->id] ?? 0);

            return [
                ...self::resumeModule($ligne),
                'saisies' => $saisies,
                'attendus' => $attendus,
                'complet' => $attendus > 0 && $saisies >= $attendus,
            ];
        })->all();

        return [
            'classe' => $classe->only(['id', 'code', 'nom', 'niveau']),
            'trimestre' => $trimestre,
            'annee_scolaire' => $anneeScolaire,
            'effectif' => $attendus,
            'modules' => $modules,
            'modules_complets' => count(array_filter($modules, fn (array $m) => $m['complet'])),
            'prete' => $modules !== [] && $attendus > 0
                && count(array_filter($modules, fn (array $m) => $m['complet'])) === count($modules),
        ];
    }

    /** @return array<string, mixed> */
    private static function resumeModule(ClasseModule $ligne): array
    {
        return [
            'classe_module_id' => $ligne->id,
            'module_id' => $ligne->module_id,
            'code' => $ligne->module?->code,
            'intitule' => $ligne->module?->intitule,
            'bloc' => $ligne->module?->bloc,
            'bloc_libelle' => Module::libelleBloc($ligne->module?->bloc),
            'credit' => $ligne->credit,
            'enseignant' => $ligne->enseignant?->nom,
        ];
    }
}
