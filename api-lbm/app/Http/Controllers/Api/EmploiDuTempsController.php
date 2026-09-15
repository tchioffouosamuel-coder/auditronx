<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmploiDuTemps;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmploiDuTempsController extends Controller
{
    public function index(Request $request)
    {
        $query = EmploiDuTemps::with(['enseignant', 'classe', 'discipline'])
            ->when($request->query('enseignant_id'), fn ($q, $v) => $q->where('enseignant_id', $v))
            ->when($request->query('classe_id'), fn ($q, $v) => $q->where('classe_id', $v));

        return response()->json($query->orderBy('jour')->orderBy('heure_debut')->paginate(50));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
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
        $this->assertPasDeConflit($data, $emploiDuTemp->id);

        $emploiDuTemp->update($data);

        return response()->json($emploiDuTemp);
    }

    public function destroy(EmploiDuTemps $emploiDuTemp)
    {
        $emploiDuTemp->delete();

        return response()->json(status: 204);
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
