<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\Enseignant;
use Illuminate\Http\Request;

/** Gestion du personnel (§4.2 — équivalent JSON de PersonnelController). */
class EnseignantController extends Controller
{
    use AccessibleEnseignants;

    public function index(Request $request)
    {
        $query = $this->enseignantsAccessibles($request->user())
            ->when($request->query('section'), fn ($q, $v) => $q->where('section', $v))
            ->when($request->query('q'), fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('nom', 'like', "%{$v}%")->orWhere('matricule', 'like', "%{$v}%");
            }));

        return response()->json($query->orderBy('nom')->paginate(25));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'matricule' => ['required', 'string', 'max:50', 'unique:enseignants,matricule'],
            'email' => ['nullable', 'email', 'unique:enseignants,email'],
            'fonction' => ['nullable', 'string', 'max:255'],
            'section' => ['nullable', 'string', 'max:255'],
            'grade' => ['nullable', 'string', 'max:255'],
            'tel' => ['nullable', 'string', 'max:50', 'unique:enseignants,tel'],
            'poste' => ['nullable', 'string', 'max:255'],
            // Identifiants de connexion à l'app mobile (§4.1 revu).
            'password' => ['nullable', 'string', 'min:6'],
            'est_admin' => ['sometimes', 'boolean'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        return response()->json(Enseignant::create($data), 201);
    }

    public function show(Request $request, Enseignant $enseignant)
    {
        abort_unless($this->peutAccederA($request->user(), $enseignant), 403);

        return response()->json($enseignant->load('emploiDuTemps.classe', 'emploiDuTemps.discipline'));
    }

    public function update(Request $request, Enseignant $enseignant)
    {
        abort_unless($this->peutAccederA($request->user(), $enseignant), 403);

        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'matricule' => ['sometimes', 'string', 'max:50', 'unique:enseignants,matricule,'.$enseignant->id],
            'email' => ['nullable', 'email', 'unique:enseignants,email,'.$enseignant->id],
            'fonction' => ['nullable', 'string', 'max:255'],
            'section' => ['nullable', 'string', 'max:255'],
            'grade' => ['nullable', 'string', 'max:255'],
            'tel' => ['nullable', 'string', 'max:50', 'unique:enseignants,tel,'.$enseignant->id],
            'poste' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:6'],
            'est_admin' => ['sometimes', 'boolean'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $enseignant->update($data);

        return response()->json($enseignant);
    }

    public function destroy(Request $request, Enseignant $enseignant)
    {
        abort_unless($this->peutAccederA($request->user(), $enseignant), 403);

        $enseignant->delete();

        return response()->json(status: 204);
    }

    /** POST /api/personnel/import — import en masse (JSON), §4.2. */
    public function import(Request $request)
    {
        $data = $request->validate([
            'enseignants' => ['required', 'array', 'min:1'],
            'enseignants.*.nom' => ['required', 'string', 'max:255'],
            'enseignants.*.matricule' => ['required', 'string', 'max:50'],
            'enseignants.*.email' => ['nullable', 'email'],
            'enseignants.*.fonction' => ['nullable', 'string', 'max:255'],
            'enseignants.*.section' => ['nullable', 'string', 'max:255'],
            'enseignants.*.grade' => ['nullable', 'string', 'max:255'],
            'enseignants.*.tel' => ['nullable', 'string', 'max:50'],
            'enseignants.*.poste' => ['nullable', 'string', 'max:255'],
        ]);

        $crees = [];
        $erreurs = [];

        foreach ($data['enseignants'] as $index => $ligne) {
            if (Enseignant::where('matricule', $ligne['matricule'])->exists()) {
                $erreurs[] = ['index' => $index, 'matricule' => $ligne['matricule'], 'erreur' => 'Matricule déjà existant.'];

                continue;
            }

            $crees[] = Enseignant::create($ligne);
        }

        return response()->json(['crees' => count($crees), 'erreurs' => $erreurs], 201);
    }
}
