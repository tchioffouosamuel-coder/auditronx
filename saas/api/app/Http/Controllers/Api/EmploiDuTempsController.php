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

        $conflit = EmploiDuTemps::where('jour', $data['jour'])
            ->where(function ($q) use ($data) {
                $q->where('enseignant_id', $data['enseignant_id'])
                    ->orWhere('classe_id', $data['classe_id'] ?? null);
            })
            ->where('heure_debut', '<', $data['heure_fin'])
            ->where('heure_fin', '>', $data['heure_debut'])
            ->when($ignoreId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->exists();

        if ($conflit) {
            throw ValidationException::withMessages([
                'heure_debut' => ['Conflit de créneau : cet enseignant ou cette classe a déjà un cours sur ce créneau.'],
            ]);
        }
    }
}
