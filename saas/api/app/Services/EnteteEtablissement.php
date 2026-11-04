<?php

namespace App\Services;

use App\Models\Parametre;
use App\Tenancy\TenantManager;

/**
 * En-tête officiel de l'établissement (§4.2) — le bandeau bilingue qui coiffe
 * les documents produits par l'application (journaux, bilans, fiches).
 *
 * Ce bandeau était écrit en dur dans les vues PDF (« LYCÉE TECHNIQUE DE
 * MEIGANGA ») : l'application étant désormais multi-établissements, chaque
 * administration doit pouvoir y mettre sa propre tutelle et son propre nom
 * sans passer par un déploiement. Les valeurs vivent dans la table
 * `parametres` de la base du client, une clé par champ, préfixée pour ne pas
 * se mêler aux autres réglages.
 *
 * Les colonnes sont celles d'un en-tête administratif camerounais : la
 * tutelle française à gauche, son équivalent anglais à droite. Un champ laissé
 * vide ne produit aucune ligne — un établissement qui ne dépend pas d'une
 * délégation régionale n'a pas à inventer la mention.
 */
class EnteteEtablissement
{
    private const PREFIXE = 'entete.';

    /**
     * Champs de l'en-tête et leur valeur par défaut, dans l'ordre d'affichage.
     *
     * Seuls les intitulés de la République et la devise ont un défaut : ils
     * sont identiques pour tous les établissements du pays. Le nom de
     * l'établissement, lui, est repris de la fiche centrale (cf. [valeurs]).
     *
     * @var array<string, string>
     */
    public const CHAMPS = [
        'republique' => 'RÉPUBLIQUE DU CAMEROUN',
        'devise' => 'Paix — Travail — Patrie',
        'ministere' => '',
        'delegation' => '',
        'etablissement' => '',
        'republique_en' => 'REPUBLIC OF CAMEROON',
        'devise_en' => 'Peace — Work — Fatherland',
        'ministere_en' => '',
        'delegation_en' => '',
        'etablissement_en' => '',
        'contact' => '',
    ];

    /** Champs de la colonne française, de haut en bas. */
    private const COLONNE_FRANCAISE = ['republique', 'devise', 'ministere', 'delegation', 'etablissement'];

    /** Champs de la colonne anglaise, de haut en bas. */
    private const COLONNE_ANGLAISE = [
        'republique_en',
        'devise_en',
        'ministere_en',
        'delegation_en',
        'etablissement_en',
    ];

    public function __construct(private readonly TenantManager $tenants) {}

    /**
     * Valeurs enregistrées, complétées par les défauts.
     *
     * @return array<string, string>
     */
    public function valeurs(): array
    {
        $enregistrees = Parametre::query()
            ->where('cle', 'like', self::PREFIXE.'%')
            ->pluck('valeur', 'cle')
            ->all();

        $valeurs = [];
        foreach (self::CHAMPS as $champ => $defaut) {
            $valeurs[$champ] = $enregistrees[self::PREFIXE.$champ] ?? $defaut;
        }

        // Tant que l'administration n'a rien saisi, le nom de la fiche centrale
        // vaut mieux qu'un en-tête amputé de l'établissement lui-même.
        if ($valeurs['etablissement'] === '') {
            $valeurs['etablissement'] = (string) ($this->tenants->courant()?->nom ?? '');
        }

        return $valeurs;
    }

    /**
     * Enregistre les champs fournis ; les autres gardent leur valeur actuelle.
     *
     * @param  array<string, string|null>  $valeurs
     * @return array<string, string>  l'en-tête complet après écriture
     */
    public function definir(array $valeurs): array
    {
        foreach (self::CHAMPS as $champ => $_) {
            if (! array_key_exists($champ, $valeurs)) {
                continue;
            }

            Parametre::set(self::PREFIXE.$champ, trim((string) ($valeurs[$champ] ?? '')));
        }

        return $this->valeurs();
    }

    /**
     * Les deux colonnes de l'en-tête, lignes vides retirées.
     *
     * @return array{gauche: string[], droite: string[], contact: string}
     */
    public function colonnes(): array
    {
        $valeurs = $this->valeurs();
        $lignes = fn (array $champs) => array_values(array_filter(
            array_map(fn (string $champ) => $valeurs[$champ], $champs),
            fn (string $ligne) => $ligne !== '',
        ));

        return [
            'gauche' => $lignes(self::COLONNE_FRANCAISE),
            'droite' => $lignes(self::COLONNE_ANGLAISE),
            'contact' => $valeurs['contact'],
        ];
    }

    /** L'en-tête est-il renseigné au point de valoir la peine d'être imprimé ? */
    public function estRenseigne(): bool
    {
        $colonnes = $this->colonnes();

        return $colonnes['gauche'] !== [] || $colonnes['droite'] !== [];
    }
}
