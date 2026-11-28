<?php

namespace App\Services;

use App\Models\Bulletin;
use App\Models\BulletinLigne;
use App\Models\Classe;
use App\Models\Inscription;
use App\Models\Module;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentPdf;
use Illuminate\Database\Eloquent\Collection as CollectionEloquent;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Impression des bulletins et du PV de conseil (§bulletins APC, lot 6).
 *
 * **Rien n'est recalculé ici.** Tout ce qu'imprime un bulletin a été figé
 * dans `bulletins` et `bulletin_lignes` au moment du calcul. Ce service ne
 * fait que mettre en forme : virgule décimale, regroupement par bloc,
 * tiret, crédit entre parenthèses, initiales du signataire. Une réimpression
 * en juin d'un bulletin validé en décembre doit sortir à l'identique, même si
 * le barème, la maquette ou les notes ont bougé depuis.
 *
 * Seules l'identité de l'élève et la spécialité de la classe sont lues en
 * direct : une faute d'orthographe corrigée sur un nom doit l'être aussi sur
 * la réimpression.
 */
class ImpressionBulletin
{
    /** Ce qu'imprime une case de note pour un module non programmé à la période. */
    public const TIRET = BaremeApc::TIRET;

    /**
     * Un PDF de bulletins : un élève par page, dans l'ordre de la liste
     * d'appel — c'est dans cet ordre qu'on les distribue.
     *
     * @param  Collection<int, Bulletin>  $bulletins  tous de la même période
     */
    public static function pdfBulletins(Collection $bulletins): DocumentPdf
    {
        $bulletins = CollectionEloquent::make($bulletins->all())->loadMissing(['lignes', 'inscription.eleve', 'classe.specialiteApc']);

        $pages = $bulletins
            ->sortBy(fn (Bulletin $b) => mb_strtolower($b->inscription?->eleve?->nom_complet ?? '', 'UTF-8'))
            ->map(fn (Bulletin $b) => self::page($b))
            ->values()
            ->all();

        return Pdf::loadView('pdf.bulletins', ['pages' => $pages])->setPaper('a4', 'portrait');
    }

    /**
     * PV du conseil de classe : moyennes, rangs, décisions, et pour chaque
     * module le taux de compétences validées.
     *
     * @param  Collection<int, Bulletin>  $bulletins  les bulletins courants du conseil
     */
    public static function pdfPv(Classe $classe, string $anneeScolaire, string $periode, Collection $bulletins): DocumentPdf
    {
        return Pdf::loadView('pdf.pv-conseil', self::pv($classe, $anneeScolaire, $periode, $bulletins))
            ->setPaper('a4', 'landscape');
    }

    /**
     * Ce qu'imprime une page de bulletin, dans la disposition du canevas
     * officiel (formation des censeurs industriels, cas pratiques NGONO et
     * TCHAMBA).
     *
     * Le bulletin trimestriel n'imprime que les modules programmés au
     * trimestre : c'est ce que fait le modèle, dont les crédits totalisent 60
     * au T3 alors que l'année en compte 97. L'annuel imprime tout, tiret
     * compris, et le module non programmé de l'année avec son crédit entre
     * parenthèses.
     *
     * Les quatre blocs sont toujours imprimés ; un bloc sans module porte la
     * mention « sans objet », comme dans le modèle.
     *
     * @return array<string, mixed>
     */
    public static function page(Bulletin $bulletin): array
    {
        $eleve = $bulletin->inscription?->eleve;
        $annuel = $bulletin->estAnnuel();
        $bareme = $bulletin->bareme ?: BaremeApc::valeurs();
        $trimestres = $annuel ? range(1, (int) ($bareme['nombre_trimestres'] ?? BaremeApc::nombreTrimestres())) : [];
        $specialite = $bulletin->classe?->specialiteApc;

        $parBloc = $bulletin->lignes
            ->filter(fn (BulletinLigne $l) => $annuel || ! $l->hors_totaux)
            ->groupBy('bloc');

        $blocs = [];
        foreach (array_unique([...array_keys(Module::BLOCS), ...$parBloc->keys()->all()]) as $bloc) {
            $blocs[] = [
                'libelle' => mb_strtoupper(Module::libelleBloc($bloc) ?? $bloc, 'UTF-8'),
                'lignes' => $parBloc->get($bloc, collect())->map(fn (BulletinLigne $l) => self::ligne($l, $trimestres))->values()->all(),
            ];
        }

        $decision = $bulletin->decisionRetenue();
        $nonValidees = $bulletin->competences_non_validees;
        $seuil = self::note((float) ($bareme['seuil_reussite'] ?? BaremeApc::SEUIL_REUSSITE));
        $uea = (int) ($bareme['bareme_uea'] ?? BaremeApc::BAREME_UEA);
        $integratrice = (int) ($bareme['bareme_integratrice'] ?? BaremeApc::NOTE_MAXIMALE - $uea);

        return [
            'type' => $bulletin->type,
            'annuel' => $annuel,
            'titre' => $annuel ? 'BULLETIN DE COMPÉTENCES : ANNUEL' : 'BULLETIN DE COMPÉTENCES : Trimestre '.$bulletin->trimestre,
            'annee' => AnneeScolaire::affichage($bulletin->annee_scolaire),
            'filigrane' => self::filigrane($bulletin),
            'eleve' => [
                'nom_complet' => $eleve?->nom_complet ?? '',
                'matricule' => $eleve?->matricule ?? '',
                'sexe' => $eleve?->sexe ?? '',
                'date_naissance' => $eleve?->date_naissance?->format('d/m/Y') ?? '',
                'lieu_naissance' => $eleve?->lieu_naissance ?? '',
            ],
            // « Statut : Régulier / Redoublant », le statut de l'élève souligné.
            'statuts' => Inscription::LIBELLES_STATUTS,
            'statut' => $bulletin->statut_inscription,
            'classe' => [
                'code' => $bulletin->classe_code,
                'nom' => $bulletin->classe_nom,
                'specialite' => $specialite ? trim($specialite->nom.($specialite->code && $specialite->code !== $specialite->nom ? ' ('.$specialite->code.')' : '')) : '',
                'specialite_code' => $specialite?->code ?? $bulletin->classe_code,
            ],
            'effectif' => $bulletin->effectif,
            'bareme_uea' => $uea,
            'bareme_integratrice' => $integratrice,
            'trimestres' => $trimestres,
            'blocs' => $blocs,
            'pied' => [
                'moyenne' => self::note($bulletin->moyenne_generale),
                'credits' => (string) $bulletin->total_credits,
                'volume_horaire' => $bulletin->total_volume_horaire === null ? '' : (string) $bulletin->total_volume_horaire,
                'mention' => $bulletin->mention ?? self::TIRET,
                'decision' => mb_strtoupper((string) BaremeApc::libelleDecision($decision), 'UTF-8'),
                'defavorable' => in_array($decision, [BaremeApc::DECISION_ECHEC, BaremeApc::DECISION_REDOUBLEMENT], true),
                'competences' => $bulletin->notes_manquantes > 0
                    ? $bulletin->notes_manquantes.' note(s) manquante(s)'
                    : ($nonValidees === 0 ? 'Toutes validées' : $nonValidees.' compétence(s) non validée(s)'),
            ],
            'seuil' => $annuel
                ? "{$seuil} / 100 pour chaque compétence (note finale = moyenne des trimestres où le module est programmé ; — : non programmé ce trimestre)"
                : "{$seuil} / 100 pour chaque compétence (note finale = moyenne UEA /{$uea} + épreuve intégratrice /{$integratrice}) programmées au trimestre {$bulletin->trimestre}.",
            'synthese' => [
                'moyenne' => self::note($bulletin->moyenne_generale),
                'moyenne_classe' => self::note($bulletin->moyenne_classe),
                'moyenne_max' => self::note($bulletin->moyenne_max),
                'moyenne_min' => self::note($bulletin->moyenne_min),
                'position' => $bulletin->position_groupe,
                'positions' => BaremeApc::LIBELLES_POSITIONS,
                'validation' => $bulletin->validation_competences,
                'decision' => $decision,
                'decisions' => self::decisionsImprimees($bulletin),
                'observation' => $bulletin->observation,
            ],
            'legende' => self::legende($bareme),
            'valide_le' => $bulletin->valide_at?->format('d/m/Y'),
        ];
    }

    /**
     * Les décisions dans l'ordre du modèle : « Succès · Échec · Remédiation »
     * au trimestre, et non dans l'ordre de gravité du barème.
     *
     * @return array<string, string>
     */
    private static function decisionsImprimees(Bulletin $bulletin): array
    {
        $ordre = $bulletin->estAnnuel()
            ? [BaremeApc::DECISION_ADMIS, BaremeApc::DECISION_SOUS_RESERVE, BaremeApc::DECISION_REDOUBLEMENT]
            : [BaremeApc::DECISION_SUCCES, BaremeApc::DECISION_ECHEC, BaremeApc::DECISION_REMEDIATION];

        return array_combine($ordre, array_map(fn (string $d) => (string) BaremeApc::libelleDecision($d), $ordre));
    }

    /**
     * Données du PV de conseil.
     *
     * Les taux par module sont des décomptes sur l'instantané (combien de
     * « S » dans la colonne), pas un nouveau calcul de notes.
     *
     * @param  Collection<int, Bulletin>  $bulletins
     * @return array<string, mixed>
     */
    public static function pv(Classe $classe, string $anneeScolaire, string $periode, Collection $bulletins): array
    {
        $bulletins = CollectionEloquent::make($bulletins->all())->loadMissing(['lignes', 'inscription.eleve']);
        $premier = $bulletins->first();
        $annuel = $periode === Bulletin::PERIODE_ANNUELLE;

        $eleves = $bulletins
            ->sortBy(fn (Bulletin $b) => sprintf('%05d-%s', $b->rang ?? 99999, mb_strtolower($b->inscription?->eleve?->nom_complet ?? '', 'UTF-8')))
            ->map(fn (Bulletin $b) => [
                'rang' => self::rang($b->rang, $b->ex_aequo),
                'matricule' => $b->inscription?->eleve?->matricule,
                'nom_complet' => $b->inscription?->eleve?->nom_complet,
                'statut' => Inscription::LIBELLES_STATUTS[$b->statut_inscription] ?? '',
                'moyenne' => self::note($b->moyenne_generale),
                'mention' => $b->mention ?? self::TIRET,
                'credits' => $b->total_credits,
                'non_validees' => $b->competences_non_validees,
                'decision_proposee' => BaremeApc::libelleDecision($b->decision_proposee),
                'decision' => BaremeApc::libelleDecision($b->decisionRetenue()),
                'modifiee' => $b->decision_conseil !== null,
                'observation' => $b->observation,
            ])
            ->values()
            ->all();

        $decisions = [];
        foreach (Bulletin::decisionsDuType($annuel ? Bulletin::TYPE_ANNUEL : Bulletin::TYPE_TRIMESTRE) as $d) {
            $decisions[BaremeApc::libelleDecision($d)] = $bulletins->filter(fn (Bulletin $b) => $b->decisionRetenue() === $d)->count();
        }

        $modules = $bulletins
            ->flatMap(fn (Bulletin $b) => $b->lignes)
            ->filter(fn (BulletinLigne $l) => ! $l->hors_totaux)
            ->groupBy('code')
            ->map(function (Collection $lignes, string $code) {
                $notees = $lignes->whereNotNull('note_finale');
                $validees = $lignes->where('sanction', BaremeApc::SANCTION_VALIDEE)->count();

                return [
                    'ordre' => $lignes->first()->ordre,
                    'code' => $code,
                    'intitule' => $lignes->first()->intitule,
                    'notes' => $notees->count(),
                    'validees' => $validees,
                    'taux' => $notees->isEmpty() ? self::TIRET : number_format(100 * $validees / $notees->count(), 0, ',', ' ').' %',
                    'moyenne' => self::note($notees->isEmpty() ? null : BaremeApc::arrondir($notees->avg('note_finale'))),
                ];
            })
            ->sortBy('ordre')
            ->values()
            ->all();

        return [
            'titre' => 'PROCÈS-VERBAL DU CONSEIL DE CLASSE',
            'periode' => $annuel ? 'Bilan annuel' : self::ordinal((int) $periode, false).' trimestre',
            'annee' => AnneeScolaire::affichage($anneeScolaire),
            'classe' => ['code' => $premier?->classe_code ?? $classe->code, 'nom' => $premier?->classe_nom ?? $classe->nom],
            'filigrane' => $premier ? self::filigrane($premier) : null,
            'effectif' => $premier?->effectif ?? 0,
            'effectif_classe' => $premier?->effectif_classe ?? 0,
            'moyenne_classe' => self::note($premier?->moyenne_classe),
            'moyenne_max' => self::note($premier?->moyenne_max),
            'moyenne_min' => self::note($premier?->moyenne_min),
            'decisions' => $decisions,
            'eleves' => $eleves,
            'modules' => $modules,
            'valide_le' => $premier?->valide_at?->format('d/m/Y'),
        ];
    }

    /** `bulletins-1TPV-T3-2025-2026.pdf` — sans espace ni barre oblique. */
    public static function nomFichier(string $prefixe, string $code, string $periode, string $anneeScolaire): string
    {
        $periode = $periode === Bulletin::PERIODE_ANNUELLE ? 'annuel' : 'T'.$periode;

        return Str::slug($prefixe.'-'.$code.'-'.$periode.'-'.AnneeScolaire::affichage($anneeScolaire)).'.pdf';
    }

    /* ---------------------------------------------------------------- */
    /* Mise en forme                                                     */
    /* ---------------------------------------------------------------- */

    /** @param array<int, int> $trimestres */
    private static function ligne(BulletinLigne $l, array $trimestres): array
    {
        $programmes = array_map('intval', $l->trimestres ?? []);
        $notes = [];

        foreach ($trimestres as $t) {
            // Le tiret pour un trimestre où le module n'est pas programmé ;
            // une case vide pour une note qui manque (seul un brouillon en a).
            $notes[$t] = in_array($t, $programmes, true)
                ? self::note($l->notes_trimestres[$t] ?? $l->notes_trimestres[(string) $t] ?? null, '')
                : self::TIRET;
        }

        return [
            'code' => $l->code,
            'intitule' => $l->intitule,
            // Hors totaux : le crédit s'imprime entre parenthèses, il ne pèse
            // pas. Les heures restent lisibles — le modèle imprime « (20) »
            // mais « 300 » pour le stage — et sortent du total quand même.
            'credit' => $l->credit === null ? '' : ($l->hors_totaux ? '('.$l->credit.')' : (string) $l->credit),
            'volume_horaire' => $l->volume_horaire === null ? '' : (string) $l->volume_horaire,
            'moyenne_uea' => $l->hors_totaux ? self::TIRET : self::note($l->moyenne_uea, ''),
            'note_integratrice' => $l->hors_totaux ? self::TIRET : self::note($l->note_integratrice, ''),
            'notes' => $notes,
            'note_finale' => $l->hors_totaux ? self::TIRET : self::note($l->note_finale, ''),
            'manquante' => $l->note_manquante,
            'non_acquise' => $l->sanction === BaremeApc::SANCTION_NON_ACQUISE,
            'mention' => $l->hors_totaux ? self::TIRET : ($l->mention ?? ($l->note_finale === null ? '' : self::TIRET)),
            'sanction' => $l->hors_totaux ? self::TIRET : ($l->sanction ?? ''),
            'appreciation' => $l->hors_totaux ? 'Non programmé' : ($l->appreciation ?? ''),
            'signataire' => self::initiales($l->signataire),
        ];
    }

    /**
     * 87.0 → « 87 », 86.5 → « 86,5 », comme le modèle : la décimale n'est
     * imprimée que si elle existe. La valeur est déjà arrondie au dixième par
     * le barème ; on ne fait que l'écrire.
     */
    public static function note(float|int|null $note, string $vide = self::TIRET): string
    {
        if ($note === null) {
            return $vide;
        }

        $note = round((float) $note, BaremeApc::DECIMALES);

        return $note == floor($note)
            ? (string) (int) $note
            : number_format($note, BaremeApc::DECIMALES, ',', ' ');
    }

    /** « 1er », « 2e ex æquo ». */
    public static function rang(?int $rang, bool $exAequo = false): string
    {
        if ($rang === null) {
            return self::TIRET;
        }

        return ($rang === 1 ? '1er' : $rang.'e').($exAequo ? ' ex æquo' : '');
    }

    /** « MBARGA Jean Paul » → « M.J.P. » : la colonne SIGN du bulletin est étroite. */
    public static function initiales(?string $nom): string
    {
        $mots = preg_split('/[\s\-]+/u', trim((string) $nom), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode('', array_map(fn (string $mot) => mb_strtoupper(mb_substr($mot, 0, 1, 'UTF-8'), 'UTF-8').'.', $mots));
    }

    /** 1 → « 1er » / « PREMIER »… */
    private static function ordinal(int $n, bool $majuscules): string
    {
        $mots = [1 => 'premier', 2 => 'deuxième', 3 => 'troisième', 4 => 'quatrième', 5 => 'cinquième', 6 => 'sixième'];
        $mot = $mots[$n] ?? $n.'e';

        return $majuscules ? mb_strtoupper($mot, 'UTF-8') : Str::ucfirst($mot);
    }

    /**
     * Un document qui n'est pas la version signée le dit sur toute sa
     * surface : un brouillon imprimé pour relecture ne doit pas pouvoir
     * passer pour un bulletin remis.
     */
    private static function filigrane(Bulletin $bulletin): ?string
    {
        return match (true) {
            $bulletin->remplace_at !== null => 'VERSION REMPLACÉE',
            ! $bulletin->estValide() => 'BROUILLON',
            default => null,
        };
    }

    /**
     * Légende des mentions, d'après le barème recopié au calcul, en
     * intervalles comme le modèle : « Mention A (Excellent) : [90 - 100] ».
     * La borne haute d'une mention est la borne basse de la suivante moins
     * un, comme l'écrit le modèle.
     *
     * @return array<int, string>
     */
    private static function legende(array $bareme): array
    {
        $mentions = $bareme['mentions'] ?? BaremeApc::MENTIONS;
        arsort($mentions);

        $legende = [];
        $haut = (float) BaremeApc::NOTE_MAXIMALE;
        foreach ($mentions as $lettre => $borne) {
            $appreciation = $bareme['appreciations'][$lettre] ?? (BaremeApc::APPRECIATIONS[$lettre] ?? '');
            $legende[] = "Mention {$lettre} ({$appreciation}) : [".self::note((float) $borne).' - '.self::note($haut).']';
            $haut = (float) $borne - 1;
        }

        return $legende;
    }
}
