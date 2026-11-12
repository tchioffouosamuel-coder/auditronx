<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmploiDuTemps;
use App\Models\PeriodeCours;
use App\Services\GrilleHoraire;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Périodes de cours de l'établissement (§4.2 — Configuration). C'est cette
 * grille qui alimente les listes déroulantes « Heure de début » / « Heure de
 * fin » de l'emploi du temps, côté web comme côté mobile.
 */
class PeriodeCoursController extends Controller
{
    private const NOMS_JOURS = [
        1 => 'lundi',
        2 => 'mardi',
        3 => 'mercredi',
        4 => 'jeudi',
        5 => 'vendredi',
        6 => 'samedi',
        7 => 'dimanche',
    ];

    /** Créneaux nommés dans le refus de suppression ; les suivants sont comptés. */
    private const CRENEAUX_CITES = 5;

    /**
     * GET /api/periodes-cours
     *
     * La migration peuple la table avec la grille historique : les listes
     * déroulantes d'un établissement déjà en service ne se vident pas à la
     * mise à jour.
     */
    public function index(Request $request)
    {
        $type = $request->query('type', 'cours');

        $periodes = match ($type) {
            // Les pauses (récréation, midi) sont des plages de la journée,
            // pas des périodes : elles ne se proposent pas à la saisie d'un
            // créneau et ne se comptent pas comme des périodes à rattraper.
            'pause' => PeriodeCours::pauses(),
            'tout' => PeriodeCours::orderBy('heure_debut')->get(),
            default => PeriodeCours::grille(),
        };

        return response()->json(['data' => $periodes->values()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->assertPlageLibre($data);

        $periode = PeriodeCours::create($data);
        GrilleHoraire::oublierPauses();

        return response()->json($periode, 201);
    }

    public function show(PeriodeCours $periodeCour)
    {
        return response()->json($periodeCour);
    }

    public function update(Request $request, PeriodeCours $periodeCour)
    {
        $data = $this->validated($request, $periodeCour->id);
        $this->assertPlageLibre($data, $periodeCour->id);

        $periodeCour->update($data);
        GrilleHoraire::oublierPauses();

        return response()->json($periodeCour);
    }

    /**
     * Une période utilisée par des créneaux ne disparaît pas en silence :
     * supprimée, ces créneaux ne correspondraient plus à rien dans les listes
     * déroulantes et ne pourraient plus être réenregistrés sans être
     * entièrement ressaisis.
     *
     * Le refus nomme les créneaux en cause (jour, classe, matière,
     * enseignant) : « 1 créneau(x) » laissait l'administration les chercher
     * un à un dans l'emploi du temps avant de pouvoir les déplacer.
     */
    public function destroy(PeriodeCours $periodeCour)
    {
        $creneaux = $this->creneauxDeLaPeriode($periodeCour);

        if ($creneaux->isNotEmpty()) {
            throw ValidationException::withMessages([
                'heure_debut' => [$this->messageUtilisation($creneaux)],
            ]);
        }

        $periodeCour->delete();
        GrilleHoraire::oublierPauses();

        return response()->json(status: 204);
    }

    /**
     * GET /api/periodes-cours/{id}/creneaux
     *
     * Les créneaux d'emploi du temps posés sur cette période, décrits : ce
     * sont eux qu'il faut déplacer avant de pouvoir la supprimer.
     */
    public function creneaux(PeriodeCours $periodeCour)
    {
        return response()->json([
            'data' => $this->creneauxDeLaPeriode($periodeCour)->map(fn ($creneau) => [
                'id' => $creneau->id,
                'jour' => (int) $creneau->jour,
                'jour_libelle' => self::NOMS_JOURS[(int) $creneau->jour] ?? 'jour inconnu',
                'heure_debut' => substr((string) $creneau->getRawOriginal('heure_debut'), 0, 5),
                'heure_fin' => substr((string) $creneau->getRawOriginal('heure_fin'), 0, 5),
                'classe' => $creneau->classe?->nom,
                'discipline' => $creneau->discipline?->nom,
                'enseignant' => $creneau->enseignant?->nom,
                'salle' => $creneau->salle,
            ])->values(),
        ]);
    }

    /**
     * Créneaux posés sur le couple début/fin de cette période.
     *
     * `getRawOriginal` : l'accesseur rend « 07:30 » alors que la colonne
     * porte « 07:30:00 ».
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, EmploiDuTemps>
     */
    private function creneauxDeLaPeriode(PeriodeCours $periode)
    {
        return EmploiDuTemps::with(['classe:id,nom', 'discipline:id,nom', 'enseignant:id,nom'])
            ->where('heure_debut', $periode->getRawOriginal('heure_debut'))
            ->where('heure_fin', $periode->getRawOriginal('heure_fin'))
            ->orderBy('jour')
            ->get();
    }

    /**
     * Le refus de suppression, créneaux nommés. Au-delà de quelques-uns la
     * liste cesse d'être lisible dans une alerte : on en cite cinq et on dit
     * combien restent.
     */
    private function messageUtilisation($creneaux): string
    {
        $cites = $creneaux->take(self::CRENEAUX_CITES)
            ->map(fn ($creneau) => sprintf(
                '%s %s–%s : %s — %s (%s)',
                self::NOMS_JOURS[(int) $creneau->jour] ?? 'jour inconnu',
                substr((string) $creneau->getRawOriginal('heure_debut'), 0, 5),
                substr((string) $creneau->getRawOriginal('heure_fin'), 0, 5),
                $creneau->classe?->nom ?? 'classe #'.$creneau->classe_id,
                $creneau->discipline?->nom ?? 'matière #'.$creneau->discipline_id,
                $creneau->enseignant?->nom ?? 'enseignant #'.$creneau->enseignant_id,
            ))
            ->all();

        $reste = $creneaux->count() - count($cites);
        if ($reste > 0) {
            $cites[] = "… et {$reste} autre(s).";
        }

        $nombre = $creneaux->count();
        $accord = $nombre > 1 ? 'créneaux' : 'créneau';

        return "Cette période est utilisée par {$nombre} {$accord} d'emploi du temps : "
            .implode(' ; ', $cites)
            .'. Déplacez-les avant de la supprimer.';
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $rule = $ignoreId ? 'sometimes' : 'required';

        $data = $request->validate([
            'ordre' => ['nullable', 'integer', 'between:1,99'],
            'libelle' => ['nullable', 'string', 'max:60'],
            'est_pause' => ['nullable', 'boolean'],
            'heure_debut' => [$rule, 'date_format:H:i'],
            'heure_fin' => [$rule, 'date_format:H:i', 'after:heure_debut'],
        ]);

        // L'ordre d'une pause n'a pas de sens pour qui la saisit : elle se
        // range à l'heure. On le renseigne donc plutôt que de l'exiger.
        if (! $ignoreId && ($data['ordre'] ?? null) === null) {
            $data['ordre'] = min(99, (int) PeriodeCours::max('ordre') + 1);
        }

        if (array_key_exists('ordre', $data) && $data['ordre'] === null) {
            unset($data['ordre']);
        }

        return $data;
    }

    /**
     * Deux périodes ne peuvent pas porter le même couple début/fin : la base
     * l'interdit par un index unique, qui remonterait sinon en erreur 500 au
     * lieu d'un message de saisie.
     */
    private function assertPlageLibre(array $data, ?int $ignoreId = null): void
    {
        if (! isset($data['heure_debut'], $data['heure_fin'])) {
            return;
        }

        $existe = PeriodeCours::where('heure_debut', $data['heure_debut'])
            ->where('heure_fin', $data['heure_fin'])
            ->when($ignoreId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'heure_debut' => ['Une période couvre déjà cette plage horaire.'],
            ]);
        }
    }
}
