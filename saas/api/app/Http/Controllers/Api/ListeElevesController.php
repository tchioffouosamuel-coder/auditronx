<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Services\AnneeScolaire;
use App\Support\Classeur;
use App\Support\ListeEleves;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Modèle / export / import des élèves, au format de la liste de classe que
 * diffuse l'administration (voir ListeEleves) : un onglet par classe, la
 * classe et l'année dans le titre, une ligne par élève.
 *
 * Un contrôleur à part et non une entrée de SpreadsheetController : la classe
 * et l'année ne sont pas des colonnes, elles se lisent une fois par onglet
 * dans le titre — ce que le format « une ligne, des colonnes nommées » ne
 * sait pas porter.
 */
class ListeElevesController extends Controller
{
    /** Statut imprimé => statut d'inscription. « N » : nouveau, soit régulier. */
    private const STATUTS = [
        'N' => Inscription::STATUT_REGULIER,
        'NOUVEAU' => Inscription::STATUT_REGULIER,
        'NOUVELLE' => Inscription::STATUT_REGULIER,
        'REGULIER' => Inscription::STATUT_REGULIER,
        'R' => Inscription::STATUT_REDOUBLANT,
        'REDOUBLANT' => Inscription::STATUT_REDOUBLANT,
        'REDOUBLANTE' => Inscription::STATUT_REDOUBLANT,
    ];

    /**
     * GET /api/spreadsheet/eleves/template — un onglet vierge par classe, le
     * titre déjà rempli : il ne reste qu'à saisir les élèves.
     */
    public function template(): BinaryFileResponse
    {
        $annee = AnneeScolaire::affichage(AnneeScolaire::courante());
        $classes = Classe::orderBy('code')->get(['id', 'code', 'nom']);

        $feuilles = $classes->isEmpty()
            ? [['onglet' => 'Eleves', 'classe' => 'CODE DE LA CLASSE', 'annee' => $annee, 'lignes' => []]]
            : $classes->map(fn (Classe $classe) => [
                'onglet' => $classe->code,
                'classe' => $classe->code,
                'annee' => $annee,
                'lignes' => [],
            ])->all();

        return $this->telecharger($feuilles, 'eleves-modele.xlsx');
    }

    /**
     * GET /api/spreadsheet/eleves/export?annee= — les élèves d'une année (en
     * cours par défaut), un onglet par classe ; ceux qui n'y sont pas inscrits
     * dans un dernier onglet « SANS CLASSE ». Le fichier se réimporte tel quel.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $annee = AnneeScolaire::normalise($request->query('annee')) ?? AnneeScolaire::courante();
        $affichage = AnneeScolaire::affichage($annee);

        $inscriptions = Inscription::with(['eleve', 'classe:id,code,nom'])
            ->where('annee_scolaire', $annee)
            ->get()
            ->filter(fn (Inscription $inscription) => $inscription->eleve && $inscription->classe);

        $feuilles = $inscriptions
            ->groupBy('classe_id')
            ->map(fn (Collection $groupe) => [
                'onglet' => $groupe->first()->classe->code,
                'classe' => $groupe->first()->classe->code,
                'annee' => $affichage,
                'lignes' => $this->trier($groupe->map(fn (Inscription $i) => $this->ligne($i->eleve, $i)))->all(),
            ])
            ->sortBy('classe')
            ->values()
            ->all();

        $sansClasse = Eleve::whereNotIn('id', $inscriptions->pluck('eleve_id'))->get();
        if ($sansClasse->isNotEmpty()) {
            $feuilles[] = [
                'onglet' => ListeEleves::SANS_CLASSE,
                'classe' => ListeEleves::SANS_CLASSE,
                'annee' => $affichage,
                'lignes' => $this->trier($sansClasse->map(fn (Eleve $eleve) => $this->ligne($eleve)))->all(),
            ];
        }

        if ($feuilles === []) {
            return $this->template();
        }

        return $this->telecharger($feuilles, 'eleves-'.$affichage.'.xlsx');
    }

    /**
     * POST /api/spreadsheet/eleves/import — crée ou met à jour chaque élève
     * (clé : le matricule interne) et son inscription dans la classe et
     * l'année du titre de l'onglet.
     *
     * Un titre « SANS CLASSE » enregistre l'identité seule : les listes
     * arrivent parfois avant les affectations. Une classe inconnue, elle,
     * refuse l'onglet entier — inscrire quarante élèves nulle part sur une
     * faute de frappe du titre ne se verrait pas.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls']]);

        $feuilles = ListeEleves::lire($request->file('file')->getRealPath());

        if ($feuilles === []) {
            abort(422, 'Aucun onglet au format de la liste des élèves : en ligne 3, les en-têtes '
                .implode(', ', ListeEleves::COLONNES).'. Téléchargez le modèle pour partir de la bonne disposition.');
        }

        $importes = 0;
        $erreurs = [];

        foreach ($feuilles as $feuille) {
            $situer = fn (int $ligne) => [
                'ligne' => $ligne,
                'feuille' => $feuille['index'] + 1,
                'feuille_nom' => $feuille['onglet'],
            ];

            $libelle = $feuille['classe'] ?? $feuille['onglet'];
            $sansClasse = $this->cle($libelle) === $this->cle(ListeEleves::SANS_CLASSE);
            $classe = $sansClasse ? null : $this->classe($libelle);

            if (! $sansClasse && ! $classe) {
                $erreurs[] = [
                    ...$situer($feuille['ligne_titre']),
                    'erreur' => "classe « {$libelle} » introuvable : le titre doit nommer la classe par son code ou son nom"
                        .' (« '.ListeEleves::titre('2nde F4-BA', '2026-2027').' »)',
                    'valeurs' => [],
                ];

                continue;
            }

            $annee = AnneeScolaire::normalise($feuille['annee']) ?? AnneeScolaire::courante();

            foreach ($feuille['lignes'] as $numero => $ligne) {
                $erreur = $this->importer($ligne, $classe, $annee);

                if ($erreur === null) {
                    $importes++;

                    continue;
                }

                $erreurs[] = [
                    ...$situer($numero),
                    'erreur' => $erreur,
                    'valeurs' => array_filter($ligne, fn ($v) => $v !== null && $v !== ''),
                ];
            }
        }

        return response()->json(['importes' => $importes, 'erreurs' => $erreurs]);
    }

    /** @param array<string, mixed> $ligne */
    private function importer(array $ligne, ?Classe $classe, string $annee): ?string
    {
        $matricule = $this->texte($ligne['matricule'] ?? null);
        $nom = $this->texte($ligne['nom'] ?? null);

        if ($matricule === null || $nom === null) {
            return 'matricule interne et noms requis';
        }

        $sexe = strtoupper((string) $this->texte($ligne['sexe'] ?? null));
        if ($sexe !== '' && ! in_array($sexe, Eleve::SEXES, true)) {
            return 'sexe doit valoir M ou F (valeur reçue : « '.$this->affiche($ligne['sexe']).' »)';
        }

        $dateNaissance = Classeur::date($ligne['date_naissance'] ?? null);
        if ($dateNaissance === false) {
            return 'date de naissance illisible (attendu : JJ/MM/AAAA) (valeur reçue : « '
                .$this->affiche($ligne['date_naissance']).' »)';
        }

        $statutLu = $this->cle($ligne['statut'] ?? '');
        $statut = $statutLu === '' ? Inscription::STATUT_REGULIER : (self::STATUTS[$statutLu] ?? null);
        if ($statut === null) {
            return 'statut doit valoir N (nouveau) ou R (redoublant) (valeur reçue : « '
                .$this->affiche($ligne['statut']).' »)';
        }

        // La liste porte le nom complet dans une seule colonne : il va dans
        // `nom`, et les prénoms d'une saisie antérieure sont effacés pour ne
        // pas s'imprimer deux fois.
        $eleve = Eleve::updateOrCreate(['matricule' => $matricule], [
            'nom' => $nom,
            'prenoms' => null,
            'matricule_national' => $this->texte($ligne['matricule_national'] ?? null),
            'sexe' => $sexe === '' ? null : $sexe,
            'date_naissance' => $dateNaissance,
            'lieu_naissance' => $this->texte($ligne['lieu_naissance'] ?? null),
            'telephone' => $this->texte($ligne['telephone'] ?? null),
        ]);

        if ($classe) {
            Inscription::updateOrCreate(
                ['eleve_id' => $eleve->id, 'annee_scolaire' => $annee],
                ['classe_id' => $classe->id, 'statut' => $statut, 'actif' => true]
            );
        }

        return null;
    }

    /** La classe nommée par le titre : son code d'abord, son nom à défaut. */
    private function classe(string $libelle): ?Classe
    {
        $cle = $this->cle($libelle);
        $classes = Classe::all(['id', 'code', 'nom']);

        return $classes->first(fn (Classe $c) => $this->cle($c->code) === $cle)
            ?? $classes->first(fn (Classe $c) => $this->cle((string) $c->nom) === $cle);
    }

    /** @return array<string, mixed> */
    private function ligne(Eleve $eleve, ?Inscription $inscription = null): array
    {
        return [
            'nom' => $eleve->nom_complet,
            'matricule_national' => $eleve->matricule_national,
            'matricule' => $eleve->matricule,
            'date_naissance' => $eleve->date_naissance?->format('d/m/Y'),
            'lieu_naissance' => $eleve->lieu_naissance,
            'telephone' => $eleve->telephone,
            'sexe' => $eleve->sexe,
            'statut' => $inscription
                ? ($inscription->statut === Inscription::STATUT_REDOUBLANT ? 'R' : 'N')
                : null,
        ];
    }

    private function trier(Collection $lignes): Collection
    {
        return $lignes->sortBy(fn (array $l) => mb_strtolower((string) $l['nom'], 'UTF-8'))->values();
    }

    /** @param array<int, array<string, mixed>> $feuilles */
    private function telecharger(array $feuilles, string $nom): BinaryFileResponse
    {
        return response()->download(ListeEleves::ecrire($feuilles), $nom)->deleteFileAfterSend();
    }

    /**
     * Valeur de cellule en texte, espaces réduits ; null si vide. Un nombre
     * entier (matricule ou téléphone saisi sans format texte) perd la
     * notation « 6.54E+8 » qu'Excel lui donnerait.
     */
    private function texte(mixed $valeur): ?string
    {
        if (is_float($valeur) && floor($valeur) === $valeur) {
            $valeur = sprintf('%.0f', $valeur);
        }

        $texte = trim(preg_replace('/\s+/u', ' ', (string) $valeur) ?? '');

        return $texte === '' ? null : $texte;
    }

    /** Comparaison sans casse, accents ni espaces superflus. */
    private function cle(mixed $valeur): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', \Illuminate\Support\Str::ascii((string) $valeur)) ?? ''));
    }

    private function affiche(mixed $valeur): string
    {
        return is_scalar($valeur) ? (string) $valeur : json_encode($valeur);
    }
}
