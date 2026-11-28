<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BaremeApc;
use App\Services\EnteteEtablissement;
use App\Services\GrilleHoraire;
use App\Services\HoraireAttendu;
use Illuminate\Http\Request;

/**
 * Configuration (§4.2) — grille horaire, horaire fixe du personnel
 * administratif et en-tête des documents.
 */
class ParametreController extends Controller
{
    /**
     * GET /api/parametres/grille-horaire
     *
     * La durée d'une période : elle décide de l'heure de fin déduite d'un
     * nombre de périodes à la saisie d'un créneau, et du nombre de périodes
     * d'une plage dans les bilans d'assiduité. Les formulaires web et mobile
     * la lisent ici plutôt que de la tenir en dur.
     */
    public function grilleHoraire()
    {
        return response()->json([
            'duree_periode_minutes' => GrilleHoraire::dureePeriodeMinutes(),
            'duree_periode_min' => GrilleHoraire::DUREE_PERIODE_MIN,
            'duree_periode_max' => GrilleHoraire::DUREE_PERIODE_MAX,
        ]);
    }

    /**
     * PUT /api/parametres/grille-horaire
     *
     * Changer la durée ne retouche pas les créneaux déjà saisis : leurs
     * couples début/fin restent ce qu'ils sont, c'est leur décompte en
     * périodes qui suit la nouvelle valeur.
     */
    public function definirGrilleHoraire(Request $request)
    {
        $data = $request->validate([
            'duree_periode_minutes' => [
                'required',
                'integer',
                'between:'.GrilleHoraire::DUREE_PERIODE_MIN.','.GrilleHoraire::DUREE_PERIODE_MAX,
            ],
        ]);

        GrilleHoraire::definirDureePeriode((int) $data['duree_periode_minutes']);

        return $this->grilleHoraire();
    }

    /** GET /api/parametres/horaires-administratifs */
    public function horairesAdministratifs(HoraireAttendu $horaires)
    {
        return response()->json($horaires->parametresAdministratifs());
    }

    /** PUT /api/parametres/horaires-administratifs */
    public function definirHorairesAdministratifs(Request $request, HoraireAttendu $horaires)
    {
        $data = $request->validate([
            'jours' => ['required', 'array', 'min:1'],
            'jours.*' => ['integer', 'between:1,7', 'distinct'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
        ]);

        $horaires->definirParametresAdministratifs($data['jours'], $data['heure_debut'], $data['heure_fin']);

        return response()->json($horaires->parametresAdministratifs());
    }

    /** GET /api/parametres/entete-etablissement */
    public function enteteEtablissement(EnteteEtablissement $entete)
    {
        return response()->json($entete->valeurs());
    }

    /**
     * PUT /api/parametres/entete-etablissement
     *
     * Tous les champs sont facultatifs — un en-tête se remplit par étapes, et
     * une administration qui ne dépend d'aucune délégation laisse la ligne
     * vide. La chaîne vide est donc une valeur valide, pas un oubli.
     */
    public function definirEnteteEtablissement(Request $request, EnteteEtablissement $entete)
    {
        $regles = [];
        foreach (array_keys(EnteteEtablissement::CHAMPS) as $champ) {
            $regles[$champ] = ['sometimes', 'nullable', 'string', 'max:150'];
        }

        // Les lignes libres : chaque colonne est une liste ordonnée, envoyée
        // en entier. C'est ce qui permet d'en ajouter, d'en retirer et de les
        // réordonner en une seule requête — un ordre ne se décrit pas champ
        // par champ.
        $regles['lignes'] = ['sometimes', 'array'];
        foreach (['gauche', 'droite'] as $colonne) {
            $regles["lignes.{$colonne}"] = ['sometimes', 'array', 'max:20'];
            $regles["lignes.{$colonne}.*.texte"] = ['required', 'string', 'max:150'];
            $regles["lignes.{$colonne}.*.cle"] = [
                'nullable',
                'string',
                'in:'.implode(',', array_keys(EnteteEtablissement::CHAMPS)),
            ];
        }

        return response()->json($entete->definir($request->validate($regles)));
    }

    /**
     * POST /api/parametres/entete-etablissement/logo
     *
     * Le logo coiffait les documents depuis un `public/logo.png` unique :
     * l'application étant multi-établissements, chacun imprimait celui d'un
     * autre. Il s'envoie donc comme un fichier, rangé dans le stockage propre
     * à l'établissement.
     */
    public function definirLogoEtablissement(Request $request, EnteteEtablissement $entete)
    {
        $request->validate([
            'logo' => [
                'required',
                'image',
                'mimes:'.implode(',', EnteteEtablissement::LOGO_EXTENSIONS),
                // Un logo d'en-tête s'imprime sur quelques centimètres : au
                // delà, c'est le poids des PDF qu'on alourdit pour rien.
                'max:2048',
            ],
        ]);

        return response()->json([
            'logo_url' => $entete->definirLogo($request->file('logo')),
        ]);
    }

    /** DELETE /api/parametres/entete-etablissement/logo */
    public function supprimerLogoEtablissement(EnteteEtablissement $entete)
    {
        $entete->supprimerLogo();

        return response()->json(['logo_url' => null]);
    }

    /**
     * GET /api/parametres/bareme-apc
     *
     * Barème des bulletins de compétences : seuil de réussite, répartition
     * UEA / épreuve intégratrice et bornes des mentions. Lu sans restriction
     * de rôle — les écrans de saisie des notes en ont besoin pour afficher la
     * note finale au fur et à mesure de la frappe.
     */
    public function baremeApc()
    {
        return response()->json(BaremeApc::valeurs());
    }

    /**
     * PUT /api/parametres/bareme-apc
     *
     * Changer le barème ne retouche aucun bulletin déjà généré : ceux-ci
     * portent leurs propres lignes figées. Seuls les calculs à venir suivent.
     */
    public function definirBaremeApc(Request $request)
    {
        $regles = [
            'seuil_reussite' => ['sometimes', 'numeric', 'between:0,'.BaremeApc::NOTE_MAXIMALE],
            // Une part d'UEA de 0 ou de 100 viderait l'une des deux colonnes
            // du bulletin de toute signification.
            'bareme_uea' => ['sometimes', 'integer', 'between:1,'.(BaremeApc::NOTE_MAXIMALE - 1)],
            'nombre_trimestres' => ['sometimes', 'integer', 'between:1,6'],
            'mentions' => ['sometimes', 'array'],
        ];

        foreach (array_keys(BaremeApc::MENTIONS) as $lettre) {
            $regles["mentions.{$lettre}"] = ['required_with:mentions', 'numeric', 'between:0,'.BaremeApc::NOTE_MAXIMALE];
        }

        $data = $request->validate($regles);

        // Les mentions se lisent de haut en bas : des bornes qui ne décroissent
        // pas classeraient toutes les notes dans la dernière lettre.
        if (isset($data['mentions'])) {
            // Remises dans l'ordre des lettres : le corps de la requête JSON
            // n'a aucune raison d'arriver trié.
            $bornes = array_map(
                fn (string $lettre) => (float) $data['mentions'][$lettre],
                array_keys(BaremeApc::MENTIONS),
            );
            for ($i = 1; $i < count($bornes); $i++) {
                if ($bornes[$i] >= $bornes[$i - 1]) {
                    return response()->json([
                        'message' => 'Les bornes des mentions doivent décroître de A vers E.',
                        'errors' => ['mentions' => ['Les bornes des mentions doivent décroître de A vers E.']],
                    ], 422);
                }
            }
        }

        return response()->json(BaremeApc::definir($data));
    }
}
