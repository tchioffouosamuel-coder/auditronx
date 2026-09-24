<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\AccessibleEnseignants;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Gestion du personnel (§4.2 — équivalent JSON de PersonnelController). */
class EnseignantController extends Controller
{
    use AccessibleEnseignants;

    public function index(Request $request)
    {
        $query = $this->enseignantsAccessibles($request->user())
            ->when($request->query('section'), fn($q, $v) => $q->where('section', $v))
            ->when($request->query('q'), fn($q, $v) => $q->where(function ($q) use ($v) {
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

        $enseignant = DB::transaction(function () use ($data) {
            $enseignant = Enseignant::create($data);
            $this->synchroniserCompteAdmin($enseignant);

            return $enseignant;
        });

        return response()->json($enseignant, 201);
    }

    public function show(Request $request, Enseignant $enseignant)
    {
        abort_unless($this->peutAccederA($request->user(), $enseignant), 403);

        return response()->json($enseignant->load('emploiDuTemps.classe', 'emploiDuTemps.discipline'));
    }

    public function assiduite(Request $request, Enseignant $enseignant)
    {
        abort_unless($this->peutAccederA($request->user(), $enseignant), 403);
        $debut = Carbon::now()->startOfMonth();
        $fin = Carbon::now()->endOfMonth();
        $emplois = $enseignant->emploiDuTemps()->get();
        $datesAttendues = collect();
        for ($date = $debut->copy(); $date->lte($fin); $date->addDay()) {
            if ($emplois->contains('jour', $date->isoWeekday())) $datesAttendues->push($date->toDateString());
        }
        $datesPresents = $enseignant->presences()->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])->whereNotNull('heure_arrivee')->pluck('date')->map(fn($date) => Carbon::parse($date)->toDateString());
        $joursAttendus = $datesAttendues->unique()->count();
        $joursPresents = $datesPresents->intersect($datesAttendues)->unique()->count();

        return response()->json(['jours_presents' => $joursPresents, 'jours_attendus' => $joursAttendus, 'taux_assiduite' => $joursAttendus > 0 ? round($joursPresents / $joursAttendus * 100, 1) : 0.0]);
    }

    public function update(Request $request, Enseignant $enseignant)
    {
        abort_unless($this->peutAccederA($request->user(), $enseignant), 403);

        $data = $request->validate([
            'nom' => ['sometimes', 'string', 'max:255'],
            'matricule' => ['sometimes', 'string', 'max:50', 'unique:enseignants,matricule,' . $enseignant->id],
            'email' => ['nullable', 'email', 'unique:enseignants,email,' . $enseignant->id],
            'fonction' => ['nullable', 'string', 'max:255'],
            'section' => ['nullable', 'string', 'max:255'],
            'grade' => ['nullable', 'string', 'max:255'],
            'tel' => ['nullable', 'string', 'max:50', 'unique:enseignants,tel,' . $enseignant->id],
            'poste' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:6'],
            'est_admin' => ['sometimes', 'boolean'],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        DB::transaction(function () use ($enseignant, $data) {
            $enseignant->update($data);
            $this->synchroniserCompteAdmin($enseignant);
        });

        return response()->json($enseignant);
    }

    /**
     * Un enseignant `est_admin` reçoit un compte administrateur (table users,
     * lié par `enseignant_id`, §admin-mobile) pour se connecter au backoffice
     * et à l'espace admin mobile — seuls ces comptes voient les OTP. Même
     * email et même mot de passe que ses identifiants app mobile : le hash
     * est recopié tel quel (le cast `hashed` ne re-hashe pas une valeur déjà
     * hashée). Décocher `est_admin` ne supprime pas le compte existant.
     */
    private function synchroniserCompteAdmin(Enseignant $enseignant): void
    {
        if (! $enseignant->est_admin) {
            return;
        }

        $user = User::where('enseignant_id', $enseignant->id)->first();

        $erreurs = [];
        if (! $enseignant->email) {
            $erreurs['email'] = ["L'email est obligatoire pour un compte administrateur."];
        } elseif (User::where('email', $enseignant->email)->when($user, fn($q) => $q->whereKeyNot($user->id))->exists()) {
            $erreurs['email'] = ['Un compte administrateur utilise déjà cet email.'];
        }
        if (! $enseignant->password) {
            $erreurs['password'] = ['Le mot de passe est obligatoire pour un compte administrateur.'];
        }
        if ($erreurs) {
            throw ValidationException::withMessages($erreurs);
        }

        $attributs = [
            'name' => $enseignant->nom,
            'email' => $enseignant->email,
            'password' => $enseignant->password,
        ];

        $user
            ? $user->update($attributs)
            : User::create([...$attributs, 'enseignant_id' => $enseignant->id]);
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
