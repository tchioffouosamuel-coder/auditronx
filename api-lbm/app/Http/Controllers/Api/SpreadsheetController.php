<?php

namespace App\Http\Controllers\Api;

use App\Exports\ArrayExport;
use App\Exports\EmploisWorkbookExport;
use App\Http\Controllers\Controller;
use App\Imports\ArrayImport;
use App\Models\Classe;
use App\Models\Discipline;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Programme;
use App\Models\ProgressionLecon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Template / export / import XLSX génériques pour les entités principales
 * (§4.2) : personnel, classes, disciplines, emplois du temps. Une seule
 * définition de colonnes par entité (voir profile()) pilote les trois
 * actions, pour éviter de dupliquer le mapping colonnes<->modèle.
 */
class SpreadsheetController extends Controller
{
    /** @return array{headings: array<int, string>, export: callable, import: callable} */
    private function profile(string $entity): array
    {
        $enseignantsParNom = null;

        return match ($entity) {
            'personnel' => [
                'headings' => ['nom', 'matricule', 'email', 'fonction', 'section', 'grade', 'tel', 'poste'],
                'export' => fn() => Enseignant::orderBy('nom')->get()->map(fn(Enseignant $e) => [
                    $e->nom,
                    $e->matricule,
                    $e->email,
                    $e->fonction,
                    $e->section,
                    $e->grade,
                    $e->tel,
                    $e->poste,
                ])->all(),
                'import' => function (array $row) {
                    $nom = trim((string) ($row['nom'] ?? ''));
                    $matricule = trim((string) ($row['matricule'] ?? ''));
                    if ($nom === '' || $matricule === '') {
                        return 'nom et matricule requis';
                    }

                    Enseignant::updateOrCreate(
                        ['matricule' => $matricule],
                        [
                            'nom' => $nom,
                            'email' => $this->blankToNull($row['email'] ?? null),
                            'fonction' => $this->blankToNull($row['fonction'] ?? null),
                            'section' => $this->blankToNull($row['section'] ?? null),
                            'grade' => $this->blankToNull($row['grade'] ?? null),
                            'tel' => $this->blankToNull($row['tel'] ?? null),
                            'poste' => $this->blankToNull($row['poste'] ?? null),
                        ]
                    );

                    return null;
                },
            ],
            'classes' => [
                'headings' => ['nom', 'code', 'niveau', 'specialite', 'effectif'],
                'export' => fn() => Classe::orderBy('nom')->get()->map(fn(Classe $c) => [
                    $c->nom,
                    $c->code,
                    $c->niveau,
                    $c->specialite,
                    $c->effectif,
                ])->all(),
                'import' => function (array $row) {
                    $nom = trim((string) ($row['nom'] ?? ''));
                    $code = trim((string) ($row['code'] ?? ''));
                    if ($nom === '' || $code === '') {
                        return 'nom et code requis';
                    }

                    Classe::updateOrCreate(
                        ['code' => $code],
                        [
                            'nom' => $nom,
                            'niveau' => $this->blankToNull($row['niveau'] ?? null),
                            'specialite' => $this->blankToNull($row['specialite'] ?? null),
                            'effectif' => $row['effectif'] !== '' && $row['effectif'] !== null ? (int) $row['effectif'] : 0,
                        ]
                    );

                    return null;
                },
            ],
            'disciplines' => [
                'headings' => ['nom', 'code', 'coefficient', 'departement'],
                'export' => fn() => Discipline::orderBy('nom')->get()->map(fn(Discipline $d) => [
                    $d->nom,
                    $d->code,
                    $d->coefficient,
                    $d->departement,
                ])->all(),
                'import' => function (array $row) {
                    $nom = trim((string) ($row['nom'] ?? ''));
                    $code = trim((string) ($row['code'] ?? ''));
                    if ($nom === '' || $code === '') {
                        return 'nom et code requis';
                    }

                    Discipline::updateOrCreate(
                        ['code' => $code],
                        [
                            'nom' => $nom,
                            'coefficient' => $row['coefficient'] !== '' && $row['coefficient'] !== null ? (int) $row['coefficient'] : 1,
                            'departement' => $this->blankToNull($row['departement'] ?? null),
                        ]
                    );

                    return null;
                },
            ],
            'emplois' => [
                'headings' => ['nom_enseignant', 'code_classe', 'code_discipline', 'jour', 'heure_debut', 'heure_fin', 'salle', 'type_cours'],
                'export' => fn() => EmploiDuTemps::with(['enseignant', 'classe', 'discipline'])->orderBy('jour')->get()
                    ->map(fn(EmploiDuTemps $e) => [
                        $e->enseignant?->nom,
                        $e->classe?->code,
                        $e->discipline?->code,
                        $e->jour,
                        $e->heure_debut,
                        $e->heure_fin,
                        $e->salle,
                        $e->type_cours,
                    ])->all(),
                'import' => function (array $row) use (&$enseignantsParNom) {
                    $nomEnseignant = trim((string) ($row['nom_enseignant'] ?? ''));
                    $matricule = trim((string) ($row['matricule_enseignant'] ?? ''));
                    if ($nomEnseignant !== '') {
                        if ($enseignantsParNom === null) {
                            $enseignantsParNom = Enseignant::query()->get(['id', 'nom', 'matricule'])
                                ->groupBy(fn(Enseignant $enseignant) => $this->normalizeTeacherName((string) $enseignant->nom))
                                ->all();
                        }

                        $matchingTeachers = $enseignantsParNom[$this->normalizeTeacherName($nomEnseignant)] ?? collect();
                        if ($matchingTeachers->count() > 1) {
                            return "nom_enseignant « {$nomEnseignant} » ambigu : plusieurs enseignants portent ce nom (matricules : "
                                . $matchingTeachers->pluck('matricule')->filter()->implode(', ')
                                . ') — renommez-les ou utilisez la colonne matricule_enseignant';
                        }

                        $enseignantId = $matchingTeachers->first()?->id;
                    } else {
                        $enseignantId = $matricule === '' ? null : Enseignant::where('matricule', $matricule)->value('id');
                    }
                    $codeClasse = trim((string) ($row['code_classe'] ?? ''));
                    $codeDiscipline = trim((string) ($row['code_discipline'] ?? ''));
                    $classe = $codeClasse === '' ? null : Classe::where('code', $codeClasse)->first();
                    $discipline = $codeDiscipline === '' ? null : Discipline::where('code', $codeDiscipline)->first();
                    $jour = $row['jour'] ?? null;
                    $heureDebut = $this->normalizeTime($row['heure_debut'] ?? null);
                    $heureFin = $this->normalizeTime($row['heure_fin'] ?? null);

                    $problemes = [];
                    if (! $enseignantId) {
                        $problemes[] = match (true) {
                            $nomEnseignant !== '' => "enseignant « {$nomEnseignant} » introuvable dans le personnel",
                            $matricule !== '' => "enseignant de matricule « {$matricule} » introuvable dans le personnel",
                            default => 'nom_enseignant manquant',
                        };
                    }
                    if (! $classe) {
                        $problemes[] = $codeClasse === '' ? 'code_classe manquant' : "classe « {$codeClasse} » introuvable";
                    }
                    if (! $discipline) {
                        $problemes[] = $codeDiscipline === '' ? 'code_discipline manquant' : "discipline « {$codeDiscipline} » introuvable";
                    }
                    if ($problemes !== []) {
                        return implode(' ; ', $problemes);
                    }

                    if (! is_numeric($jour) || (float) $jour !== (float) (int) $jour || (int) $jour < 1 || (int) $jour > 7) {
                        return 'jour doit être un nombre de 1 à 7 (valeur reçue : « ' . $this->displayValue($jour) . ' »)';
                    }

                    if (! in_array($heureDebut, EmploiDuTemps::HEURES_DEBUT, true)) {
                        return 'heure_debut doit être choisie dans la liste prédéfinie (valeur reçue : « ' . $this->displayValue($row['heure_debut'] ?? null) . ' »)';
                    }

                    if (! in_array($heureFin, EmploiDuTemps::HEURES_FIN, true) || $heureFin <= $heureDebut) {
                        return 'heure_fin doit être choisie dans la liste prédéfinie et être après heure_debut (valeur reçue : « ' . $this->displayValue($row['heure_fin'] ?? null) . ' »)';
                    }

                    EmploiDuTemps::updateOrCreate(
                        [
                            'enseignant_id' => $enseignantId,
                            'classe_id' => $classe->id,
                            'jour' => (int) $jour,
                            'heure_debut' => $heureDebut,
                        ],
                        [
                            'discipline_id' => $discipline->id,
                            'heure_fin' => $heureFin,
                            'salle' => $this->blankToNull($row['salle'] ?? null),
                            'type_cours' => $this->blankToNull($row['type_cours'] ?? null),
                        ]
                    );

                    return null;
                },
            ],
            'progressions' => [
                'headings' => [
                    'annee_scolaire',
                    'code_classe',
                    'code_discipline',
                    'trimestre',
                    'semaine_numero',
                    'periode',
                    'unite_apprentissage',
                    'unite_enseignement',
                    'theorique',
                    'pratique',
                    'duree',
                    'digitalisee',
                    'ordre',
                ],
                'export' => fn() => ProgressionLecon::with('programme.classe', 'programme.discipline')
                    ->orderBy('programme_id')->orderBy('ordre')->get()
                    ->map(fn(ProgressionLecon $lecon) => [
                        $lecon->programme->annee_scolaire,
                        $lecon->programme->classe->code,
                        $lecon->programme->discipline->code,
                        $lecon->trimestre,
                        $lecon->semaine_numero,
                        $lecon->periode,
                        $lecon->unite_apprentissage,
                        $lecon->unite_enseignement,
                        $lecon->theorique ? 1 : 0,
                        $lecon->pratique ? 1 : 0,
                        $lecon->duree,
                        $lecon->digitalisee ? 1 : 0,
                        $lecon->ordre,
                    ])->all(),
                'import' => function (array $row) {
                    $classe = Classe::where('code', trim((string) ($row['code_classe'] ?? '')))->first();
                    $discipline = Discipline::where('code', trim((string) ($row['code_discipline'] ?? '')))->first();
                    $annee = trim((string) ($row['annee_scolaire'] ?? ''));
                    $uniteEnseignement = trim((string) ($row['unite_enseignement'] ?? ''));
                    $ordre = $row['ordre'] ?? null;

                    if (! $classe || ! $discipline || $annee === '' || $uniteEnseignement === '' || ! is_numeric($ordre)) {
                        return 'classe/matiere introuvable, annee, unite_enseignement ou ordre manquant';
                    }

                    $programme = Programme::updateOrCreate(
                        ['classe_id' => $classe->id, 'discipline_id' => $discipline->id, 'annee_scolaire' => $annee],
                        ['nb_seances_prevues' => 0]
                    );

                    ProgressionLecon::updateOrCreate(
                        ['programme_id' => $programme->id, 'ordre' => (int) $ordre],
                        [
                            'trimestre' => $this->integerOrNull($row['trimestre'] ?? null),
                            'semaine_numero' => $this->integerOrNull($row['semaine_numero'] ?? null),
                            'periode' => $this->blankToNull($row['periode'] ?? null),
                            'unite_apprentissage' => $this->blankToNull($row['unite_apprentissage'] ?? null),
                            'unite_enseignement' => $uniteEnseignement,
                            'theorique' => $this->toBool($row['theorique'] ?? false),
                            'pratique' => $this->toBool($row['pratique'] ?? false),
                            'duree' => $this->blankToNull($row['duree'] ?? null),
                            'digitalisee' => $this->toBool($row['digitalisee'] ?? false),
                        ]
                    );
                    $programme->update(['nb_seances_prevues' => $programme->lecons()->count()]);

                    return null;
                },
            ],
            default => abort(404, "Entité inconnue : {$entity}"),
        };
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' || $value === null ? null : (string) $value;
    }

    private function normalizeTime(mixed $value): ?string
    {
        $value = $this->blankToNull($value);
        if ($value === null || preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $matches) !== 1) {
            return null;
        }

        return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
    }

    private function displayValue(mixed $value): string
    {
        return $value === null || $value === '' ? 'vide' : (string) $value;
    }

    /**
     * Noms des feuilles du classeur, pour situer les erreurs d'import
     * multi-feuilles. Tableau vide si le format ne les expose pas.
     *
     * @return array<int, string>
     */
    private function worksheetNames(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);

            return method_exists($reader, 'listWorksheetNames') ? $reader->listWorksheetNames($path) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function normalizeTeacherName(string $name): string
    {
        $normalizedName = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);

        return mb_strtolower($normalizedName, 'UTF-8');
    }

    private function emploisWorkbook(bool $template): EmploisWorkbookExport
    {
        $classes = Classe::orderBy('nom')->get();
        $rowsByClasse = [];

        if (! $template) {
            EmploiDuTemps::with(['enseignant', 'classe', 'discipline'])
                ->orderBy('classe_id')->orderBy('jour')->orderBy('heure_debut')
                ->get()
                ->each(function (EmploiDuTemps $emploi) use (&$rowsByClasse): void {
                    $rowsByClasse[$emploi->classe_id][] = [
                        $emploi->enseignant?->nom,
                        $emploi->classe?->code,
                        $emploi->discipline?->code,
                        $emploi->jour,
                        substr((string) $emploi->heure_debut, 0, 5),
                        substr((string) $emploi->heure_fin, 0, 5),
                        $emploi->salle,
                        $emploi->type_cours,
                    ];
                });
        }

        return new EmploisWorkbookExport(
            $classes->map(fn(Classe $classe) => [
                'id' => $classe->id,
                'code' => $classe->code,
            ])->all(),
            Enseignant::orderBy('nom')->pluck('nom')->all(),
            Discipline::orderBy('nom')->pluck('code')->all(),
            $rowsByClasse,
        );
    }

    private function integerOrNull(mixed $value): ?int
    {
        return $value === '' || $value === null || ! is_numeric($value) ? null : (int) $value;
    }

    private function toBool(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'oui', 'o', 'x', 'true', 'yes'], true);
    }

    /** GET /api/{entity}/template — fichier XLSX vierge avec les bons en-têtes. */
    public function template(string $entity): BinaryFileResponse
    {
        $profile = $this->profile($entity);

        if ($entity === 'emplois') {
            return Excel::download($this->emploisWorkbook(template: true), "{$entity}-modele.xlsx");
        }

        return Excel::download(new ArrayExport($profile['headings']), "{$entity}-modele.xlsx");
    }

    /** GET /api/{entity}/export — export XLSX des données actuelles. */
    public function export(string $entity): BinaryFileResponse
    {
        $profile = $this->profile($entity);

        if ($entity === 'emplois') {
            return Excel::download($this->emploisWorkbook(template: false), "{$entity}-export.xlsx");
        }

        return Excel::download(new ArrayExport($profile['headings'], ($profile['export'])()), "{$entity}-export.xlsx");
    }

    /** GET /api/spreadsheet/personnel/export-pdf — export PDF du personnel. */
    public function exportPersonnelPdf()
    {
        $personnel = Enseignant::orderBy('nom')->get();

        return Pdf::loadView('pdf.personnel', compact('personnel'))
            ->download('personnel.pdf');
    }

    /** POST /api/{entity}/import — import XLSX (créé ou met à jour par clé naturelle). */
    public function import(Request $request, string $entity): JsonResponse
    {
        $profile = $this->profile($entity);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $sheets = Excel::toArray(new ArrayImport, $request->file('file'));
        $importSheets = $entity === 'emplois' ? $sheets : [($sheets[0] ?? [])];
        $sheetNames = $entity === 'emplois' ? $this->worksheetNames($request->file('file')->getRealPath()) : [];

        $importes = 0;
        $erreurs = [];

        foreach ($importSheets as $sheetIndex => $rows) {
            foreach ($rows as $index => $row) {
                if ($entity === 'emplois' && ! array_key_exists('nom_enseignant', $row) && ! array_key_exists('matricule_enseignant', $row)) {
                    continue;
                }

                // Ligne entièrement vide (fin de feuille) : on l'ignore silencieusement.
                if (count(array_filter($row, fn($v) => $v !== null && $v !== '')) === 0) {
                    continue;
                }

                $erreur = ($profile['import'])($row);
                if ($erreur) {
                    $erreurs[] = [
                        'ligne' => $index + 2,
                        ...($entity === 'emplois' ? [
                            'feuille' => $sheetIndex + 1,
                            'feuille_nom' => $sheetNames[$sheetIndex] ?? null,
                        ] : []),
                        'erreur' => $erreur,
                        // Contenu brut de la ligne, pour que l'utilisateur la retrouve sans ouvrir le fichier.
                        'valeurs' => array_filter($row, fn($v) => $v !== null && $v !== ''),
                    ];
                } else {
                    $importes++;
                }
            }
        }

        return response()->json(['importes' => $importes, 'erreurs' => $erreurs]);
    }
}
