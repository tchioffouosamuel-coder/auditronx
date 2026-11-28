<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Référentiel APC : les modules d'une spécialité, par niveau et par bloc.
 *
 * Le gros de la saisie se fait par import du classeur de l'organisation
 * pédagogique (ReferentielApcController) ; ces routes servent à corriger ce
 * que le classeur ne porte pas — les crédits, surtout, absents du document —
 * et à renommer les codes fabriqués à l'import.
 */
class ModuleController extends Controller
{
    public function index(Request $request)
    {
        $modules = Module::with('specialite:id,code,nom')
            ->when($request->filled('specialite_id'), fn ($q) => $q->where('specialite_id', $request->integer('specialite_id')))
            ->when($request->filled('niveau'), fn ($q) => $q->where('niveau', $request->string('niveau')->toString()))
            ->when($request->filled('bloc'), fn ($q) => $q->where('bloc', $request->string('bloc')->toString()))
            ->ordreBulletin()
            ->paginate(200);

        return response()->json($modules);
    }

    /** Blocs du bulletin, dans l'ordre d'impression — alimente les listes déroulantes. */
    public function blocs()
    {
        return response()->json(
            collect(Module::BLOCS)->map(fn (string $libelle, string $cle) => [
                'valeur' => $cle,
                'libelle' => $libelle,
            ])->values()
        );
    }

    public function store(Request $request)
    {
        return response()->json(Module::create($this->valide($request)), 201);
    }

    public function show(Module $module)
    {
        return response()->json($module->load('specialite:id,code,nom'));
    }

    public function update(Request $request, Module $module)
    {
        $module->update($this->valide($request, $module));

        return response()->json($module->load('specialite:id,code,nom'));
    }

    public function destroy(Module $module)
    {
        $module->delete();

        return response()->json(status: 204);
    }

    /** @return array<string, mixed> */
    private function valide(Request $request, ?Module $module = null): array
    {
        $data = $request->validate([
            'specialite_id' => ['required', 'integer', 'exists:specialites,id'],
            'niveau' => ['required', 'string', 'max:50'],
            'code' => [
                'required',
                'string',
                'max:50',
                // Un même code vit légitimement dans plusieurs spécialités et
                // sur plusieurs niveaux : l'unicité ne vaut qu'à l'intérieur
                // d'un couple spécialité/niveau.
                Rule::unique('modules', 'code')
                    ->where('specialite_id', $request->integer('specialite_id'))
                    ->where('niveau', $request->string('niveau')->toString())
                    ->ignore($module?->id),
            ],
            'intitule' => ['required', 'string', 'max:255'],
            'bloc' => ['required', 'string', Rule::in(array_keys(Module::BLOCS))],
            'volume_horaire' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'credit' => ['nullable', 'integer', 'min:0', 'max:999'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:999'],
            'discipline_id' => ['nullable', 'integer', 'exists:disciplines,id'],
        ]);

        $data['ordre'] = $data['ordre'] ?? 0;

        return $data;
    }
}
