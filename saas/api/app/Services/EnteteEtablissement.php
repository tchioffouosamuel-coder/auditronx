<?php

namespace App\Services;

use App\Models\Parametre;
use App\Tenancy\TenantManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
     * Disque et dossier du logo. `public_direct` écrit dans `public/` sans
     * lien symbolique — `symlink()` est désactivé sur l'hébergement de
     * production — et il est cloisonné par établissement (voir TenantManager).
     */
    public const LOGO_DISQUE = 'public_direct';

    private const LOGO_DOSSIER = 'entete';

    /** Formats acceptés pour le logo, et extension stockée pour chacun. */
    public const LOGO_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

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

        // Le logo n'est pas un champ texte : il s'envoie à part (multipart) et
        // ne se lit ici que pour l'afficher dans les écrans de configuration.
        $valeurs['logo_url'] = $this->logoUrl();

        return $valeurs;
    }

    /** Chemin relatif du logo sur le disque, ou null s'il n'y en a pas. */
    public function logoChemin(): ?string
    {
        return Parametre::get(self::PREFIXE.'logo', '') ?: null;
    }

    /**
     * Chemin absolu du logo, pour les documents générés (dompdf et Word
     * lisent le fichier, pas une URL). Null si le fichier a disparu du disque.
     */
    public function logoFichier(): ?string
    {
        $chemin = $this->logoChemin();

        if ($chemin === null || ! Storage::disk(self::LOGO_DISQUE)->exists($chemin)) {
            return null;
        }

        return Storage::disk(self::LOGO_DISQUE)->path($chemin);
    }

    /** URL publique du logo, pour les écrans de configuration. */
    public function logoUrl(): ?string
    {
        $chemin = $this->logoChemin();

        if ($chemin === null || ! Storage::disk(self::LOGO_DISQUE)->exists($chemin)) {
            return null;
        }

        // L'horodatage force le navigateur à relire le fichier après un
        // remplacement : le nom, lui, ne change pas.
        $version = Storage::disk(self::LOGO_DISQUE)->lastModified($chemin);

        return Storage::disk(self::LOGO_DISQUE)->url($chemin).'?v='.$version;
    }

    /**
     * Remplace le logo de l'établissement et renvoie son URL publique.
     * L'ancien fichier est supprimé : un établissement n'a qu'un logo, et
     * deux extensions différentes en laisseraient un orphelin.
     */
    public function definirLogo(UploadedFile $fichier): ?string
    {
        $this->supprimerLogo();

        $extension = mb_strtolower($fichier->getClientOriginalExtension() ?: $fichier->extension());
        $extension = in_array($extension, self::LOGO_EXTENSIONS, true) ? $extension : 'png';
        $chemin = self::LOGO_DOSSIER.'/logo.'.$extension;

        Storage::disk(self::LOGO_DISQUE)->put($chemin, (string) file_get_contents($fichier->getRealPath()));
        Parametre::set(self::PREFIXE.'logo', $chemin);

        return $this->logoUrl();
    }

    public function supprimerLogo(): void
    {
        $chemin = $this->logoChemin();

        if ($chemin !== null) {
            Storage::disk(self::LOGO_DISQUE)->delete($chemin);
        }

        Parametre::set(self::PREFIXE.'logo', '');
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
     * @return array{gauche: string[], droite: string[], contact: string, logo: string|null}
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
            // Chemin sur disque : dompdf comme le générateur Word lisent le
            // fichier, pas une URL.
            'logo' => $this->logoFichier(),
        ];
    }

    /** L'en-tête est-il renseigné au point de valoir la peine d'être imprimé ? */
    public function estRenseigne(): bool
    {
        $colonnes = $this->colonnes();

        return $colonnes['gauche'] !== [] || $colonnes['droite'] !== [] || $colonnes['logo'] !== null;
    }
}
