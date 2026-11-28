<?php

namespace App\Http\Controllers\Api;

use App\Exports\NotesWorkbookExport;
use App\Http\Controllers\Controller;
use App\Imports\FeuillesBrutesImport;
use App\Models\Classe;
use App\Models\ClasseModule;
use App\Models\Inscription;
use App\Models\NoteModule;
use App\Models\User;
use App\Services\AnneeScolaire;
use App\Services\BaremeApc;
use App\Services\SaisieNotes;
use App\Support\Classeur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Classeur de saisie des notes : export pré-rempli, réimport.
 *
 * Contrôleur distinct de NoteModuleController parce que c'est un autre geste
 * — un aller-retour de fichier sur plusieurs jours, entre plusieurs
 * enseignants — et distinct de SpreadsheetController parce que le classeur ne
 * porte pas une entité par ligne : il porte une classe, un trimestre, et un
 * onglet par module.
 */
class NotesClasseurController extends Controller
{
    /**
     * GET /api/notes/classeur/export?classe_id=&trimestre=&annee=
     *
     * Un onglet par module programmé, la liste d'élèves déjà posée, les notes
     * déjà saisies en place. Télécharger puis réimporter sans rien changer ne
     * modifie rien.
     */
    public function export(Request $request): BinaryFileResponse
    {
        [$classe, $trimestre, $annee] = $this->contexte($request);

        $repartition = BaremeApc::repartition();
        $inscriptions = SaisieNotes::inscriptions($classe, $annee);
        $lignes = SaisieNotes::modulesSaisissables($classe, $trimestre);

        $notes = NoteModule::whereIn('classe_module_id', $lignes->pluck('id'))
            ->where('trimestre', $trimestre)
            ->get()
            ->groupBy('classe_module_id');

        $modules = $lignes->map(function (ClasseModule $ligne) use ($inscriptions, $notes) {
            $parInscription = ($notes[$ligne->id] ?? collect())->keyBy('inscription_id');

            return [
                'code' => (string) ($ligne->module?->code ?? $ligne->module_id),
                'intitule' => (string) $ligne->module?->intitule,
                'lignes' => $inscriptions->map(fn (Inscription $inscription) => [
                    $inscription->eleve?->matricule,
                    $inscription->eleve?->nom_complet,
                    $parInscription->get($inscription->id)?->moyenne_uea,
                    $parInscription->get($inscription->id)?->note_integratrice,
                ])->all(),
            ];
        })->all();

        $nom = Str::slug("notes-{$classe->code}-T{$trimestre}-".str_replace('/', '-', $annee)).'.xlsx';

        return Excel::download(
            new NotesWorkbookExport($modules, $repartition['uea'], $repartition['integratrice']),
            $nom
        );
    }

    /**
     * POST /api/notes/classeur/import
     *
     * Chaque onglet est rapproché d'un module par son **code**, chaque ligne
     * d'un élève par son **matricule** : ni l'ordre des onglets ni celui des
     * lignes ne comptent, et un classeur dont on a trié les élèves reste
     * importable.
     *
     * `apercu=1` lit sans rien écrire.
     */
    public function import(Request $request): JsonResponse
    {
        [$classe, $trimestre, $annee] = $this->contexte($request);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'apercu' => ['nullable', 'boolean'],
        ]);

        $repartition = BaremeApc::repartition();
        $fichier = $request->file('file');
        $onglets = Classeur::nomsOnglets($fichier->getRealPath());

        // Index de rapprochement, construits une fois : un `where` par ligne
        // rendrait l'import d'une classe de 40 élèves × 12 modules inutilement
        // bavard.
        $lignesParCode = SaisieNotes::modulesSaisissables($classe, $trimestre)
            ->keyBy(fn (ClasseModule $ligne) => $this->normalise((string) $ligne->module?->code));

        $inscriptionsParMatricule = Inscription::activesDe($classe->id, $annee)
            ->with('eleve:id,matricule')
            ->get()
            ->keyBy(fn (Inscription $inscription) => $this->normalise((string) $inscription->eleve?->matricule));

        $verrouillees = NoteModule::whereIn('classe_module_id', $lignesParCode->pluck('id'))
            ->where('trimestre', $trimestre)
            ->whereNotNull('verrouillee_at')
            ->get()
            ->keyBy(fn (NoteModule $note) => $note->classe_module_id.':'.$note->inscription_id);

        $aEcrire = [];
        $erreurs = [];
        $ignorees = 0;

        foreach (Excel::toArray(new FeuillesBrutesImport, $fichier) as $index => $lignes) {
            $nomOnglet = $onglets[$index] ?? 'Feuille '.($index + 1);
            $ligneMaquette = $lignesParCode->get($this->normalise($nomOnglet));

            if ($ligneMaquette === null) {
                $erreurs[] = [
                    'feuille' => $nomOnglet,
                    'ligne' => 1,
                    'erreur' => "aucun module de code « {$nomOnglet} » programmé au trimestre {$trimestre} "
                        ."dans la maquette de la classe {$classe->code} : onglet ignoré",
                    'valeurs' => [],
                ];

                continue;
            }

            foreach ($lignes as $numero => $cellules) {
                $numeroLigne = $numero + 1;
                $matricule = $this->normalise((string) ($cellules[0] ?? ''));

                // En-tête et lignes vides.
                if ($matricule === '' || $matricule === 'matricule') {
                    continue;
                }

                $inscription = $inscriptionsParMatricule->get($matricule);

                if ($inscription === null) {
                    $erreurs[] = [
                        'feuille' => $nomOnglet,
                        'ligne' => $numeroLigne,
                        'erreur' => "aucun élève de matricule « {$cellules[0]} » inscrit en {$classe->code} pour {$annee}",
                        'valeurs' => ['nom' => $cellules[1] ?? null],
                    ];

                    continue;
                }

                $uea = $this->note($cellules[2] ?? null, $repartition['uea']);
                $integratrice = $this->note($cellules[3] ?? null, $repartition['integratrice']);

                if ($uea === false || $integratrice === false) {
                    $erreurs[] = [
                        'feuille' => $nomOnglet,
                        'ligne' => $numeroLigne,
                        'erreur' => 'note hors barème ou illisible (attendu : 0 à '.$repartition['uea']
                            .' pour les UEA, 0 à '.$repartition['integratrice'].' pour l\'épreuve intégratrice)',
                        'valeurs' => ['uea' => $cellules[2] ?? null, 'integratrice' => $cellules[3] ?? null],
                    ];

                    continue;
                }

                if (isset($verrouillees[$ligneMaquette->id.':'.$inscription->id])) {
                    $erreurs[] = [
                        'feuille' => $nomOnglet,
                        'ligne' => $numeroLigne,
                        'erreur' => 'note verrouillée par un bulletin validé : ligne ignorée',
                        'valeurs' => ['matricule' => $cellules[0] ?? null],
                    ];

                    continue;
                }

                if ($uea === null && $integratrice === null) {
                    $ignorees++;

                    continue;
                }

                $aEcrire[] = [
                    'inscription_id' => $inscription->id,
                    'classe_module_id' => $ligneMaquette->id,
                    'moyenne_uea' => $uea,
                    'note_integratrice' => $integratrice,
                ];
            }
        }

        $apercu = [
            'classe' => $classe->only(['id', 'code', 'nom']),
            'trimestre' => $trimestre,
            'annee_scolaire' => $annee,
            'lues' => count($aEcrire),
            'lignes_vides_ignorees' => $ignorees,
            'erreurs' => $erreurs,
        ];

        if ($request->boolean('apercu')) {
            return response()->json([...$apercu, 'applique' => false]);
        }

        $utilisateur = $request->user();
        $saisiePar = $utilisateur instanceof User ? $utilisateur->id : null;

        DB::transaction(function () use ($aEcrire, $trimestre, $saisiePar): void {
            foreach ($aEcrire as $note) {
                NoteModule::updateOrCreate(
                    [
                        'inscription_id' => $note['inscription_id'],
                        'classe_module_id' => $note['classe_module_id'],
                        'trimestre' => $trimestre,
                    ],
                    [
                        'moyenne_uea' => $note['moyenne_uea'],
                        'note_integratrice' => $note['note_integratrice'],
                        'saisie_par' => $saisiePar,
                    ]
                );
            }
        });

        return response()->json([
            ...$apercu,
            'applique' => true,
            'avancement' => SaisieNotes::avancement($classe, $trimestre, $annee),
        ]);
    }

    /**
     * Une note du classeur : `null` si la case est vide, `false` si elle est
     * illisible ou hors barème.
     *
     * Les deux doivent se distinguer — une case vide est une saisie à venir,
     * une case fausse est une erreur à signaler. La virgule décimale est
     * acceptée : c'est ainsi qu'un classeur francophone l'écrit.
     */
    private function note(mixed $valeur, int $maximum): float|false|null
    {
        if ($valeur === null || trim((string) $valeur) === '') {
            return null;
        }

        $texte = str_replace(',', '.', trim((string) $valeur));

        if (! is_numeric($texte)) {
            return false;
        }

        $note = (float) $texte;

        return $note < 0 || $note > $maximum ? false : $note;
    }

    private function normalise(string $valeur): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $valeur) ?? $valeur), 'UTF-8');
    }

    /** @return array{0: Classe, 1: int, 2: string} */
    private function contexte(Request $request): array
    {
        $request->validate([
            'classe_id' => ['required', 'integer', 'exists:classes,id'],
            'trimestre' => ['required', 'integer', 'between:1,'.BaremeApc::nombreTrimestres()],
            'annee' => ['nullable', 'string', 'max:'.AnneeScolaire::LONGUEUR_MAX],
        ]);

        return [
            Classe::findOrFail($request->integer('classe_id')),
            $request->integer('trimestre'),
            AnneeScolaire::normalise($request->string('annee')->toString()) ?? AnneeScolaire::courante(),
        ];
    }
}
