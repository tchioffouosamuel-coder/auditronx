<?php

namespace App\Http\Controllers\Api;

use App\Exports\ArrayExport;
use App\Http\Controllers\Controller;
use App\Imports\ArrayImport;
use App\Models\Classe;
use App\Models\Discipline;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
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
        return match ($entity) {
            'personnel' => [
                'headings' => ['nom', 'matricule', 'email', 'fonction', 'section', 'grade', 'tel', 'poste'],
                'export' => fn () => Enseignant::orderBy('nom')->get()->map(fn (Enseignant $e) => [
                    $e->nom, $e->matricule, $e->email, $e->fonction, $e->section, $e->grade, $e->tel, $e->poste,
                ])->all(),
                'import' => function (array $row) {
                    $nom = trim((string) ($row['nom'] ?? ''));
                    $matricule = trim((string) ($row['matricule'] ?? ''));
                    if ($nom === '' || $matricule === '') {
                        return "nom et matricule requis";
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
                'export' => fn () => Classe::orderBy('nom')->get()->map(fn (Classe $c) => [
                    $c->nom, $c->code, $c->niveau, $c->specialite, $c->effectif,
                ])->all(),
                'import' => function (array $row) {
                    $nom = trim((string) ($row['nom'] ?? ''));
                    $code = trim((string) ($row['code'] ?? ''));
                    if ($nom === '' || $code === '') {
                        return "nom et code requis";
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
                'export' => fn () => Discipline::orderBy('nom')->get()->map(fn (Discipline $d) => [
                    $d->nom, $d->code, $d->coefficient, $d->departement,
                ])->all(),
                'import' => function (array $row) {
                    $nom = trim((string) ($row['nom'] ?? ''));
                    $code = trim((string) ($row['code'] ?? ''));
                    if ($nom === '' || $code === '') {
                        return "nom et code requis";
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
                'headings' => ['matricule_enseignant', 'code_classe', 'code_discipline', 'jour', 'heure_debut', 'heure_fin', 'salle', 'type_cours'],
                'export' => fn () => EmploiDuTemps::with(['enseignant', 'classe', 'discipline'])->orderBy('jour')->get()
                    ->map(fn (EmploiDuTemps $e) => [
                        $e->enseignant?->matricule, $e->classe?->code, $e->discipline?->code,
                        $e->jour, $e->heure_debut, $e->heure_fin, $e->salle, $e->type_cours,
                    ])->all(),
                'import' => function (array $row) {
                    $enseignant = Enseignant::where('matricule', trim((string) ($row['matricule_enseignant'] ?? '')))->first();
                    $classe = Classe::where('code', trim((string) ($row['code_classe'] ?? '')))->first();
                    $discipline = Discipline::where('code', trim((string) ($row['code_discipline'] ?? '')))->first();
                    $jour = $row['jour'] ?? null;
                    $heureDebut = $this->blankToNull($row['heure_debut'] ?? null);
                    $heureFin = $this->blankToNull($row['heure_fin'] ?? null);

                    if (! $enseignant || ! $classe || ! $discipline || ! $jour || ! $heureDebut || ! $heureFin) {
                        return "enseignant/classe/discipline introuvable ou jour/horaires manquants";
                    }

                    EmploiDuTemps::updateOrCreate(
                        [
                            'enseignant_id' => $enseignant->id,
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
            default => abort(404, "Entité inconnue : {$entity}"),
        };
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === '' || $value === null ? null : (string) $value;
    }

    /** GET /api/{entity}/template — fichier XLSX vierge avec les bons en-têtes. */
    public function template(string $entity): BinaryFileResponse
    {
        $profile = $this->profile($entity);

        return Excel::download(new ArrayExport($profile['headings']), "{$entity}-modele.xlsx");
    }

    /** GET /api/{entity}/export — export XLSX des données actuelles. */
    public function export(string $entity): BinaryFileResponse
    {
        $profile = $this->profile($entity);

        return Excel::download(new ArrayExport($profile['headings'], ($profile['export'])()), "{$entity}-export.xlsx");
    }

    /** POST /api/{entity}/import — import XLSX (créé ou met à jour par clé naturelle). */
    public function import(Request $request, string $entity): JsonResponse
    {
        $profile = $this->profile($entity);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $sheets = Excel::toArray(new ArrayImport, $request->file('file'));
        $rows = $sheets[0] ?? [];

        $importes = 0;
        $erreurs = [];

        foreach ($rows as $index => $row) {
            // Ligne entièrement vide (fin de feuille) : on l'ignore silencieusement.
            if (count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            $erreur = ($profile['import'])($row);
            if ($erreur) {
                $erreurs[] = ['ligne' => $index + 2, 'erreur' => $erreur];
            } else {
                $importes++;
            }
        }

        return response()->json(['importes' => $importes, 'erreurs' => $erreurs]);
    }
}
