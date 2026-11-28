<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\ClasseModule;
use App\Models\Module;
use Illuminate\Support\Collection;

/**
 * Maquette d'une classe : le passage du référentiel d'une spécialité à ce que
 * *cette* classe suit cette année.
 *
 * Le référentiel décrit une formation ; la maquette décrit une classe. On
 * recopie donc, on ne référence pas : les crédits et les heures sont figés sur
 * la ligne de maquette, pour qu'une correction du référentiel en janvier ne
 * réécrive pas les bulletins du premier trimestre.
 */
class MaquetteClasse
{
    /**
     * Ajoute à la maquette les modules du référentiel qui n'y sont pas
     * encore.
     *
     * Jamais destructif : un module retiré du référentiel reste dans les
     * maquettes qui l'utilisent — la classe l'a bel et bien suivi — et un
     * module ajouté à la main n'est pas emporté.
     *
     * Les crédits et heures vides sont repris du référentiel à chaque
     * passage : le classeur d'organisation pédagogique ne porte pas les
     * crédits, qui se saisissent dans le référentiel après coup, souvent
     * après que les maquettes ont été initialisées. Sans ce rattrapage il
     * faudrait les ressaisir classe par classe.
     *
     * @param  bool  $rafraichir  écrase aussi les valeurs déjà saisies sur la maquette
     * @return array{ajoutes: int, completes: int, ignores: int, modules_referentiel: int}
     */
    public static function initialiser(Classe $classe, bool $rafraichir = false): array
    {
        $modules = self::modulesDuReferentiel($classe);
        $existantes = $classe->maquette()->get()->keyBy('module_id');

        $bilan = [
            'ajoutes' => 0,
            'completes' => 0,
            'ignores' => 0,
            'modules_referentiel' => $modules->count(),
        ];

        foreach ($modules as $module) {
            $ligne = $existantes->get($module->id);

            if ($ligne === null) {
                ClasseModule::create([
                    'classe_id' => $classe->id,
                    'module_id' => $module->id,
                    'credit' => $module->credit,
                    'volume_horaire' => $module->volume_horaire,
                    'trimestres' => ClasseModule::tousLesTrimestres(),
                    'ordre' => $module->ordre,
                ]);
                $bilan['ajoutes']++;

                continue;
            }

            $valeurs = [];

            if ($rafraichir || $ligne->credit === null) {
                $valeurs['credit'] = $module->credit;
            }

            if ($rafraichir || $ligne->volume_horaire === null) {
                $valeurs['volume_horaire'] = $module->volume_horaire;
            }

            // `array_filter` serait faux ici : un crédit repris à null doit
            // pouvoir effacer une valeur, et 0 est une valeur.
            $valeurs = array_filter($valeurs, fn ($valeur, $cle) => $ligne->{$cle} !== $valeur, ARRAY_FILTER_USE_BOTH);

            if ($valeurs !== []) {
                $ligne->update($valeurs);
                $bilan['completes']++;
            } else {
                $bilan['ignores']++;
            }
        }

        return $bilan;
    }

    /**
     * La maquette telle que l'affichent l'écran et, plus tard, le bulletin :
     * dans l'ordre des blocs, module par module.
     *
     * @return Collection<int, ClasseModule>
     */
    public static function lignes(Classe $classe): Collection
    {
        return $classe->maquette()
            ->with(['module', 'enseignant:id,nom'])
            ->get()
            // Une clé composée plutôt qu'un tri à plusieurs critères : passé
            // un tableau de fermetures, `sortBy` ne les enchaîne pas comme on
            // l'attend et rend un ordre arbitraire. Le gabarit à largeur fixe
            // garde l'ordre numérique sur une comparaison de chaînes.
            ->sortBy(fn (ClasseModule $ligne) => sprintf(
                '%02d-%04d-%s',
                Module::rangBloc($ligne->module?->bloc),
                $ligne->ordre,
                $ligne->module?->code ?? '',
            ))
            ->values();
    }

    /**
     * Totaux du bas de maquette.
     *
     * Les modules non programmés en sont exclus, exactement comme ils le
     * seront des totaux du bulletin : c'est le même décompte, et le voir dès
     * la maquette évite de découvrir au moment de l'impression qu'il manque
     * des crédits.
     *
     * @param  Collection<int, ClasseModule>  $lignes
     * @return array{modules: int, modules_programmes: int, credits: int, volume_horaire: int, credits_manquants: int}
     */
    public static function totaux(Collection $lignes): array
    {
        $programmees = $lignes->filter(fn (ClasseModule $ligne) => $ligne->compteDansLesTotaux());

        return [
            'modules' => $lignes->count(),
            'modules_programmes' => $programmees->count(),
            'credits' => (int) $programmees->sum('credit'),
            'volume_horaire' => (int) $programmees->sum('volume_horaire'),
            'credits_manquants' => $programmees->whereNull('credit')->count(),
        ];
    }

    /**
     * Modules du référentiel qui correspondent à la classe.
     *
     * @return Collection<int, Module>
     */
    public static function modulesDuReferentiel(Classe $classe): Collection
    {
        if ($classe->specialite_id === null || trim((string) $classe->niveau) === '') {
            return collect();
        }

        return Module::where('specialite_id', $classe->specialite_id)
            ->where('niveau', $classe->niveau)
            ->ordreBulletin()
            ->get();
    }

    /**
     * Ce qui empêche la maquette d'être initialisée, en clair.
     *
     * Rendu plutôt que levé : l'écran doit pouvoir afficher une classe sans
     * spécialité, et dire pourquoi elle n'a pas de maquette.
     */
    public static function obstacle(Classe $classe): ?string
    {
        if ($classe->specialite_id === null) {
            return 'la classe n\'est rattachée à aucune spécialité';
        }

        if (trim((string) $classe->niveau) === '') {
            return 'la classe n\'a pas de niveau : c\'est lui qui désigne les modules du référentiel';
        }

        if (self::modulesDuReferentiel($classe)->isEmpty()) {
            $specialite = $classe->specialiteApc?->code ?? $classe->specialite_id;

            return "aucun module au référentiel pour la spécialité « {$specialite} » au niveau « {$classe->niveau} » "
                .'— vérifiez que le niveau de la classe est écrit comme dans le référentiel';
        }

        return null;
    }
}
