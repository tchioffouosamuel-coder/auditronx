<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\ClasseModule;
use App\Models\Inscription;
use App\Models\NoteModule;
use App\Models\User;
use App\Services\AnneeScolaire;
use App\Services\BaremeApc;
use App\Services\SaisieNotes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saisie des notes (§bulletins APC).
 *
 * Une grille = une classe, un trimestre, un module. Les 40 lignes d'une classe
 * s'enregistrent en un envoi : une requête par case saisie rendrait l'écran
 * inutilisable sur les connexions qu'on trouve dans les établissements.
 */
class NoteModuleController extends Controller
{
    /**
     * GET /api/notes?classe_id=&trimestre=&classe_module_id=&annee=
     *
     * Sans `classe_module_id`, rend l'avancement de la classe : c'est l'écran
     * d'entrée, celui qui dit ce qu'il reste à remplir.
     */
    public function index(Request $request)
    {
        [$classe, $trimestre, $annee] = $this->contexte($request);

        if (! $request->filled('classe_module_id')) {
            return response()->json(SaisieNotes::avancement($classe, $trimestre, $annee));
        }

        $ligne = $this->ligneSaisissable($classe, $trimestre, $request->integer('classe_module_id'));

        return response()->json(SaisieNotes::grille($classe, $ligne, $trimestre, $annee));
    }

    /**
     * PUT /api/notes/bulk
     *
     * Enregistre la grille d'un module.
     *
     * Une note absente du corps n'est pas touchée ; une note dont les deux
     * champs sont vides est **supprimée** plutôt que laissée à null —
     * autrement une saisie effacée par erreur resterait indistinguable d'une
     * case jamais remplie dans les décomptes d'avancement.
     */
    public function bulk(Request $request)
    {
        [$classe, $trimestre, $annee] = $this->contexte($request);

        $repartition = BaremeApc::repartition();

        $data = $request->validate([
            'classe_module_id' => ['required', 'integer'],
            'notes' => ['required', 'array'],
            'notes.*.inscription_id' => ['required', 'integer', 'distinct'],
            'notes.*.moyenne_uea' => ['nullable', 'numeric', 'min:0', 'max:'.$repartition['uea']],
            'notes.*.note_integratrice' => ['nullable', 'numeric', 'min:0', 'max:'.$repartition['integratrice']],
            'notes.*.appreciation' => ['nullable', 'string', 'max:255'],
        ]);

        $ligne = $this->ligneSaisissable($classe, $trimestre, $data['classe_module_id']);

        // Les inscriptions recevables sont celles de la classe, pour l'année,
        // et actives : une note posée sur un élève d'une autre classe
        // n'apparaîtrait sur aucun bulletin et ne se retrouverait jamais.
        $recevables = Inscription::activesDe($classe->id, $annee)->pluck('id')->all();
        $intrus = array_values(array_diff(array_column($data['notes'], 'inscription_id'), $recevables));

        if ($intrus !== []) {
            throw ValidationException::withMessages([
                'notes' => 'Élèves hors de la classe pour cette année : '.implode(', ', $intrus).'.',
            ]);
        }

        $verrouillees = NoteModule::where('classe_module_id', $ligne->id)
            ->where('trimestre', $trimestre)
            ->whereNotNull('verrouillee_at')
            ->whereIn('inscription_id', array_column($data['notes'], 'inscription_id'))
            ->pluck('inscription_id')
            ->all();

        if ($verrouillees !== []) {
            throw ValidationException::withMessages([
                'notes' => count($verrouillees).' note(s) ont servi à un bulletin validé et sont verrouillées. '
                    .'Déverrouillez le bulletin avant de les modifier.',
            ]);
        }

        $utilisateur = $request->user();
        $saisiePar = $utilisateur instanceof User ? $utilisateur->id : null;

        $bilan = DB::transaction(function () use ($data, $ligne, $trimestre, $saisiePar) {
            $enregistrees = 0;
            $effacees = 0;

            foreach ($data['notes'] as $saisie) {
                $cle = [
                    'inscription_id' => $saisie['inscription_id'],
                    'classe_module_id' => $ligne->id,
                    'trimestre' => $trimestre,
                ];

                $vide = ($saisie['moyenne_uea'] ?? null) === null
                    && ($saisie['note_integratrice'] ?? null) === null
                    && trim((string) ($saisie['appreciation'] ?? '')) === '';

                if ($vide) {
                    $effacees += NoteModule::where($cle)->delete();

                    continue;
                }

                NoteModule::updateOrCreate($cle, [
                    'moyenne_uea' => $saisie['moyenne_uea'] ?? null,
                    'note_integratrice' => $saisie['note_integratrice'] ?? null,
                    'appreciation' => $this->appreciationUtile($saisie['appreciation'] ?? null),
                    'saisie_par' => $saisiePar,
                ]);
                $enregistrees++;
            }

            return ['enregistrees' => $enregistrees, 'effacees' => $effacees];
        });

        return response()->json([
            ...SaisieNotes::grille($classe, $ligne, $trimestre, $annee),
            'bilan' => $bilan,
        ]);
    }

    /** GET /api/notes/avancement?classe_id=&trimestre=&annee= */
    public function avancement(Request $request)
    {
        [$classe, $trimestre, $annee] = $this->contexte($request);

        return response()->json(SaisieNotes::avancement($classe, $trimestre, $annee));
    }

    /**
     * Une appréciation identique à celle que le barème produirait n'est pas
     * stockée : la garder figerait le texte d'un module dont la note change
     * ensuite, et ferait diverger le bulletin de sa propre mention.
     */
    private function appreciationUtile(?string $appreciation): ?string
    {
        $appreciation = trim((string) $appreciation);

        return $appreciation === '' ? null : $appreciation;
    }

    /**
     * Classe, trimestre et année de la requête.
     *
     * @return array{0: Classe, 1: int, 2: string}
     */
    private function contexte(Request $request): array
    {
        $request->validate([
            'classe_id' => ['required', 'integer', 'exists:classes,id'],
            'trimestre' => ['required', 'integer', 'between:1,'.BaremeApc::nombreTrimestres()],
            'annee' => ['nullable', 'string', 'max:'.AnneeScolaire::LONGUEUR_MAX],
        ]);

        return [
            Classe::findOrFail($request->integer('classe_id')),
            $request->integer('trimestre'),
            AnneeScolaire::normalise($request->string('annee')->toString()) ?? AnneeScolaire::courante(),
        ];
    }

    /**
     * La ligne de maquette visée, si elle est bien de cette classe **et**
     * programmée à ce trimestre.
     *
     * Le second contrôle n'est pas une formalité : une note saisie sur un
     * module non programmé n'entrerait dans aucune moyenne annuelle, et
     * personne ne verrait qu'elle a été perdue.
     */
    private function ligneSaisissable(Classe $classe, int $trimestre, int $classeModuleId): ClasseModule
    {
        $ligne = ClasseModule::with(['module', 'enseignant:id,nom'])
            ->where('classe_id', $classe->id)
            ->find($classeModuleId);

        if ($ligne === null) {
            throw ValidationException::withMessages([
                'classe_module_id' => "Ce module n'est pas dans la maquette de la classe {$classe->code}.",
            ]);
        }

        if (! $ligne->estProgrammeAu($trimestre)) {
            $code = $ligne->module?->code ?? $ligne->module_id;
            throw ValidationException::withMessages([
                'classe_module_id' => "Le module « {$code} » n'est pas programmé au trimestre {$trimestre} : "
                    .'cochez ce trimestre sur la maquette de la classe avant d\'y saisir des notes.',
            ]);
        }

        return $ligne;
    }
}
