<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Http\Controllers\Traits\AccessiblesDisciplines;
use App\Models\Discipline;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmploiDuTempsController extends Controller
{
    use AccessibleEnseignants, AccessiblesDisciplines;

    private const NOMS_JOURS = [
        1 => 'lundi',
        2 => 'mardi',
        3 => 'mercredi',
        4 => 'jeudi',
        5 => 'vendredi',
        6 => 'samedi',
        7 => 'dimanche',
    ];

    public function index(Request $request)
    {
        $query = EmploiDuTemps::with(['enseignant', 'classe', 'discipline'])
            ->when($request->query('enseignant_id'), fn ($q, $v) => $q->where('enseignant_id', $v))
            ->when($request->query('classe_id'), fn ($q, $v) => $q->where('classe_id', $v));

        // Un compte backoffice ne voit que les créneaux de son périmètre. Le
        // token `Enseignant` de l'app mobile n'a pas d'accréditation et lit son
        // propre emploi du temps, filtré en amont par `enseignant_id`.
        $auteur = $request->user();

        if ($auteur instanceof User) {
            $query->whereIn('enseignant_id', $this->enseignantsAccessibles($auteur)->select('id'))
                ->whereIn('discipline_id', $this->disciplinesAccessibles($auteur)->select('id'));
        }

        $perPage = min(max((int) $request->query('per_page', 50), 1), 500);

        return response()->json($query->orderBy('jour')->orderBy('heure_debut')->paginate($perPage));
    }

    /**
     * GET /api/emplois/conflits
     *
     * Créneaux qui se chevauchent deux à deux, avec les deux séances en
     * présence. `assertPasDeConflit` barre la route aux nouveaux conflits mais
     * reste muet sur les anciens : un import Excel, une modification des
     * horaires ou des données antérieures à ce contrôle laissent des
     * chevauchements en base, qu'aucun écran ne montrait. L'administrateur a
     * besoin de savoir quel cours, de quel enseignant et dans quelle classe
     * entre en collision pour trancher.
     */
    public function conflits(Request $request)
    {
        $query = EmploiDuTemps::with(['enseignant', 'classe', 'discipline']);

        $auteur = $request->user();

        if ($auteur instanceof User) {
            $query->whereIn('enseignant_id', $this->enseignantsAccessibles($auteur)->select('id'))
                ->whereIn('discipline_id', $this->disciplinesAccessibles($auteur)->select('id'));
        }

        $creneaux = $query->orderBy('jour')->orderBy('heure_debut')->get();
        $conflits = [];

        // Comparaison deux à deux à l'intérieur d'un même jour. L'effectif
        // d'un établissement se compte en milliers de créneaux au plus : une
        // requête SQL d'auto-jointure ne gagnerait rien et rendrait le
        // regroupement par motif (enseignant / classe) illisible.
        foreach ($creneaux->groupBy('jour') as $jour => $duJour) {
            $liste = $duJour->values();

            for ($i = 0; $i < $liste->count(); $i++) {
                for ($j = $i + 1; $j < $liste->count(); $j++) {
                    $a = $liste[$i];
                    $b = $liste[$j];

                    if ($a->heure_debut >= $b->heure_fin || $a->heure_fin <= $b->heure_debut) {
                        continue;
                    }

                    $motifs = [];

                    if ((int) $a->enseignant_id === (int) $b->enseignant_id) {
                        $motifs[] = 'enseignant';
                    }

                    if ((int) $a->classe_id === (int) $b->classe_id) {
                        $motifs[] = 'classe';
                    }

                    if ($motifs === []) {
                        continue;
                    }

                    $conflits[] = [
                        'jour' => (int) $jour,
                        'motifs' => $motifs,
                        'heure_debut' => max((string) $a->heure_debut, (string) $b->heure_debut),
                        'heure_fin' => min((string) $a->heure_fin, (string) $b->heure_fin),
                        'a' => $a,
                        'b' => $b,
                    ];
                }
            }
        }

        return response()->json(['data' => $conflits]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->assertPerimetre($request, $data);
        $this->assertPasDeConflit($data);

        return response()->json(EmploiDuTemps::create($data), 201);
    }

    public function show(EmploiDuTemps $emploiDuTemp)
    {
        return response()->json($emploiDuTemp->load('enseignant', 'classe', 'discipline'));
    }

    public function update(Request $request, EmploiDuTemps $emploiDuTemp)
    {
        $data = $this->validated($request, $emploiDuTemp->id);
        $this->assertPerimetre($request, $data + [
            'enseignant_id' => $emploiDuTemp->enseignant_id,
            'discipline_id' => $emploiDuTemp->discipline_id,
        ]);
        $this->assertPasDeConflit($data, $emploiDuTemp->id);

        $emploiDuTemp->update($data);

        return response()->json($emploiDuTemp);
    }

    public function destroy(Request $request, EmploiDuTemps $emploiDuTemp)
    {
        $this->assertPerimetre($request, [
            'enseignant_id' => $emploiDuTemp->enseignant_id,
            'discipline_id' => $emploiDuTemp->discipline_id,
        ]);

        $emploiDuTemp->delete();

        return response()->json(status: 204);
    }

    /**
     * Un créneau rattache une matière à un enseignant : l'accréditation doit
     * couvrir les deux. Sans ce contrôle, un censeur d'une section pouvait
     * attribuer n'importe quelle matière — y compris celles d'une autre
     * section — à n'importe quel enseignant, et ainsi fausser l'assiduité
     * attendue de personnes hors de son périmètre.
     */
    private function assertPerimetre(Request $request, array $data): void
    {
        $auteur = $request->user();

        if (! $auteur instanceof User) {
            abort(403, 'Réservé aux administrateurs.');
        }

        if (isset($data['enseignant_id'])) {
            $enseignant = Enseignant::find($data['enseignant_id']);

            abort_unless(
                $enseignant && $this->peutAccederA($auteur, $enseignant),
                403,
                'Votre accréditation ne couvre pas cet enseignant.',
            );
        }

        if (isset($data['discipline_id'])) {
            $discipline = Discipline::find($data['discipline_id']);

            abort_unless(
                $discipline && $this->peutUtiliserDiscipline($auteur, $discipline),
                403,
                'Votre accréditation ne couvre pas le département de cette matière.',
            );
        }
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $rule = $ignoreId ? 'sometimes' : 'required';

        return $request->validate([
            'enseignant_id' => [$rule, 'exists:enseignants,id'],
            'classe_id' => [$rule, 'exists:classes,id'],
            'discipline_id' => [$rule, 'exists:disciplines,id'],
            'jour' => [$rule, 'integer', 'between:1,7'],
            'heure_debut' => [$rule, 'date_format:H:i'],
            'heure_fin' => [$rule, 'date_format:H:i', 'after:heure_debut'],
            'salle' => ['nullable', 'string', 'max:100'],
            'type_cours' => ['nullable', 'string', 'max:100'],
        ]);
    }

    /** Détecte les conflits de créneaux pour un même enseignant ou une même classe (§4.2). */
    private function assertPasDeConflit(array $data, ?int $ignoreId = null): void
    {
        if (! isset($data['enseignant_id'], $data['jour'], $data['heure_debut'], $data['heure_fin'])) {
            return;
        }

        $conflit = EmploiDuTemps::with(['enseignant', 'classe', 'discipline'])
            ->where('jour', $data['jour'])
            ->where(function ($q) use ($data) {
                $q->where('enseignant_id', $data['enseignant_id'])
                    ->orWhere('classe_id', $data['classe_id'] ?? null);
            })
            ->where('heure_debut', '<', $data['heure_fin'])
            ->where('heure_fin', '>', $data['heure_debut'])
            ->when($ignoreId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->first();

        if ($conflit) {
            // Le message nomme la séance en cause : « déjà un cours sur ce
            // créneau » obligeait à parcourir la grille à la main pour savoir
            // quoi déplacer.
            $quoi = trim(sprintf(
                '%s — %s (%s)',
                $conflit->classe?->nom ?? 'classe #'.$conflit->classe_id,
                $conflit->discipline?->nom ?? 'matière #'.$conflit->discipline_id,
                $conflit->enseignant?->nom ?? 'enseignant #'.$conflit->enseignant_id,
            ));

            throw ValidationException::withMessages([
                'heure_debut' => [sprintf(
                    'Conflit de créneau avec %s, %s de %s à %s.',
                    $quoi,
                    self::NOMS_JOURS[(int) $conflit->jour] ?? 'ce jour',
                    substr((string) $conflit->heure_debut, 0, 5),
                    substr((string) $conflit->heure_fin, 0, 5),
                )],
            ]);
        }
    }
}
