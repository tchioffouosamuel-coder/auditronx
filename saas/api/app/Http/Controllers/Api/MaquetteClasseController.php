<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\ClasseModule;
use App\Models\Module;
use App\Services\BaremeApc;
use App\Services\MaquetteClasse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Maquette d'une classe (§bulletins APC) : les modules qu'elle suit, leurs
 * crédits, leurs heures, l'enseignant qui signe et les trimestres où chacun
 * est programmé.
 */
class MaquetteClasseController extends Controller
{
    /** GET /api/classes/{classe}/maquette */
    public function show(Classe $classe)
    {
        return response()->json($this->charge($classe));
    }

    /**
     * POST /api/classes/{classe}/maquette/initialiser
     *
     * Reprend le référentiel de la spécialité de la classe. Rejouable : les
     * modules déjà présents ne sont pas dupliqués, et seuls les crédits et
     * heures restés vides sont complétés — sauf `rafraichir`, qui réaligne
     * tout sur le référentiel.
     */
    public function initialiser(Request $request, Classe $classe)
    {
        $request->validate(['rafraichir' => ['nullable', 'boolean']]);

        $obstacle = MaquetteClasse::obstacle($classe);

        if ($obstacle !== null) {
            return response()->json([
                'message' => 'Maquette impossible à initialiser : '.$obstacle.'.',
                'errors' => ['classe' => [$obstacle]],
            ], 422);
        }

        $bilan = DB::transaction(
            fn () => MaquetteClasse::initialiser($classe, $request->boolean('rafraichir'))
        );

        return response()->json([...$this->charge($classe), 'bilan' => $bilan]);
    }

    /**
     * PUT /api/classes/{classe}/maquette
     *
     * Enregistre en un envoi les lignes modifiées à l'écran.
     *
     * Ne touche **que** les modules cités : une maquette ne se remplace pas
     * par ce que l'écran avait en mémoire. Un retrait passe par la route
     * DELETE, un ajout par `initialiser` ou par une ligne nouvelle ici.
     */
    public function update(Request $request, Classe $classe)
    {
        $maxTrimestre = BaremeApc::nombreTrimestres();

        $data = $request->validate([
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.module_id' => ['required', 'integer', 'distinct', 'exists:modules,id'],
            'lignes.*.credit' => ['nullable', 'integer', 'min:0', 'max:999'],
            'lignes.*.volume_horaire' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'lignes.*.enseignant_id' => ['nullable', 'integer', 'exists:enseignants,id'],
            'lignes.*.trimestres' => ['nullable', 'array'],
            // Pas de `distinct` ici : sous deux jokers, la règle compare
            // toutes les valeurs de toutes les lignes entre elles, et deux
            // modules programmés au même trimestre — le cas normal — se
            // refuseraient l'un l'autre. Les doublons d'une même ligne sont
            // éliminés à l'écriture.
            'lignes.*.trimestres.*' => ['integer', 'between:1,'.$maxTrimestre],
            'lignes.*.ordre' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        // Un module d'une autre spécialité — ou d'un autre niveau — dans la
        // maquette produirait un bulletin dont personne ne saurait d'où il
        // sort. Le contrôle est ici et non dans la règle `exists`, qui ne sait
        // pas de quelle classe il s'agit.
        $autorises = MaquetteClasse::modulesDuReferentiel($classe)->pluck('id')->all();
        $intrus = array_values(array_diff(array_column($data['lignes'], 'module_id'), $autorises));

        if ($intrus !== []) {
            $codes = Module::whereIn('id', $intrus)->pluck('code')->implode(', ');

            return response()->json([
                'message' => "Modules hors du référentiel de la classe : {$codes}.",
                'errors' => ['lignes' => ["Modules hors du référentiel de la classe : {$codes}."]],
            ], 422);
        }

        DB::transaction(function () use ($classe, $data): void {
            foreach ($data['lignes'] as $ligne) {
                ClasseModule::updateOrCreate(
                    ['classe_id' => $classe->id, 'module_id' => $ligne['module_id']],
                    [
                        'credit' => $ligne['credit'] ?? null,
                        'volume_horaire' => $ligne['volume_horaire'] ?? null,
                        'enseignant_id' => $ligne['enseignant_id'] ?? null,
                        'trimestres' => isset($ligne['trimestres'])
                            ? self::trimestresPropres($ligne['trimestres'])
                            : ClasseModule::tousLesTrimestres(),
                        'ordre' => $ligne['ordre'] ?? 0,
                    ]
                );
            }
        });

        return response()->json($this->charge($classe));
    }

    /**
     * DELETE /api/classes/{classe}/maquette/{module}
     *
     * Retire un module de la maquette. À ne pas confondre avec « non
     * programmé » : un module retiré disparaît du bulletin, alors qu'un module
     * sans trimestre coché y reste, avec son crédit entre parenthèses.
     */
    public function destroy(Classe $classe, Module $module)
    {
        $classe->maquette()->where('module_id', $module->id)->delete();

        return response()->json(status: 204);
    }

    /**
     * Trimestres dédoublonnés et remis en ordre avant enregistrement : une
     * case cochée deux fois par l'écran ne doit pas se lire dans la base.
     *
     * @param  array<int, mixed>  $trimestres
     * @return array<int, int>
     */
    private static function trimestresPropres(array $trimestres): array
    {
        $propres = array_values(array_unique(array_map('intval', $trimestres)));
        sort($propres);

        return $propres;
    }

    /** @return array<string, mixed> */
    private function charge(Classe $classe): array
    {
        $lignes = MaquetteClasse::lignes($classe);
        $classe->load('specialiteApc:id,code,nom');

        return [
            'classe' => [
                'id' => $classe->id,
                'code' => $classe->code,
                'nom' => $classe->nom,
                'niveau' => $classe->niveau,
                'effectif' => $classe->effectif,
                'specialite' => $classe->specialiteApc,
            ],
            'nombre_trimestres' => BaremeApc::nombreTrimestres(),
            'obstacle' => MaquetteClasse::obstacle($classe),
            'lignes' => $lignes->map(fn (ClasseModule $ligne) => [
                'module_id' => $ligne->module_id,
                'code' => $ligne->module?->code,
                'intitule' => $ligne->module?->intitule,
                'bloc' => $ligne->module?->bloc,
                'bloc_libelle' => Module::libelleBloc($ligne->module?->bloc),
                'credit' => $ligne->credit,
                'volume_horaire' => $ligne->volume_horaire,
                'enseignant_id' => $ligne->enseignant_id,
                'enseignant' => $ligne->enseignant?->nom,
                'trimestres' => $ligne->trimestresProgrammes(),
                'ordre' => $ligne->ordre,
            ])->all(),
            'totaux' => MaquetteClasse::totaux($lignes),
        ];
    }
}
