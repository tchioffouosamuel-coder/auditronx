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
 *
 * Chaque colonne est une **liste de lignes libres** : les cinq intitulés
 * d'origine (république, devise, ministère, délégation, établissement) n'en
 * sont que le contenu par défaut. Une administration peut en ajouter — une
 * seconde tutelle, un numéro d'arrêté de création — et les remettre dans
 * l'ordre qu'elle veut. Les lignes venues des champs nommés gardent leur clé :
 * les clients qui écrivent encore `ministere` ou `etablissement` continuent de
 * modifier la bonne ligne.
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

        // Les lignes sont la forme complète de l'en-tête : les champs nommés
        // ci-dessus n'en montrent que les cinq premières de chaque colonne,
        // pour les clients écrits avant qu'on puisse en ajouter.
        $valeurs['lignes'] = $this->lignes();

        return $valeurs;
    }

    /**
     * Lignes des deux colonnes, dans l'ordre d'impression. Chaque ligne porte
     * son texte et, pour celles issues des champs nommés, la clé qui permet de
     * continuer à les écrire par [definir].
     *
     * Tant qu'aucune liste n'a été enregistrée, elle est déduite des champs
     * nommés : un établissement déjà configuré retrouve son en-tête tel quel.
     *
     * @return array{gauche: array<int, array{cle: string|null, texte: string}>, droite: array<int, array{cle: string|null, texte: string}>}
     */
    public function lignes(): array
    {
        $brut = Parametre::get(self::PREFIXE.'lignes', '');
        $stockees = $brut === '' ? null : json_decode($brut, true);

        if (! is_array($stockees)) {
            return $this->lignesDesChampsNommes();
        }

        $lignes = [];
        foreach (['gauche', 'droite'] as $colonne) {
            $lignes[$colonne] = [];

            foreach ((array) ($stockees[$colonne] ?? []) as $ligne) {
                $texte = trim((string) (is_array($ligne) ? ($ligne['texte'] ?? '') : $ligne));
                $cle = is_array($ligne) ? ($ligne['cle'] ?? null) : null;
                $cle = is_string($cle) && array_key_exists($cle, self::CHAMPS) ? $cle : null;

                // Une ligne nommée relit sa valeur courante : elle reste le
                // même champ, qu'il ait été écrit par l'ancienne API ou ici.
                if ($cle !== null) {
                    $texte = $this->valeurChampNomme($cle);
                }

                if ($texte !== '') {
                    $lignes[$colonne][] = ['cle' => $cle, 'texte' => $texte];
                }
            }
        }

        return $lignes;
    }

    /**
     * Remplace les lignes des deux colonnes : c'est ainsi qu'on en ajoute,
     * qu'on en retire et qu'on les réordonne, en un seul envoi.
     *
     * Une ligne qui porte une clé connue reste adossée à son champ nommé —
     * son texte y est réécrit, pour que les clients qui lisent encore
     * `etablissement` voient la même chose que le document.
     *
     * @param  array{gauche?: array, droite?: array}  $lignes
     */
    public function definirLignes(array $lignes): void
    {
        $normalisees = [];

        foreach (['gauche', 'droite'] as $colonne) {
            $normalisees[$colonne] = [];
            $vues = [];

            foreach ((array) ($lignes[$colonne] ?? []) as $ligne) {
                $texte = trim((string) (is_array($ligne) ? ($ligne['texte'] ?? '') : $ligne));
                $cle = is_array($ligne) ? ($ligne['cle'] ?? null) : null;
                $cle = is_string($cle) && array_key_exists($cle, self::CHAMPS) ? $cle : null;

                // Une même clé deux fois ferait diverger le champ nommé de la
                // ligne : la seconde devient une ligne libre.
                if ($cle !== null && isset($vues[$cle])) {
                    $cle = null;
                }

                if ($texte === '') {
                    continue;
                }

                if ($cle !== null) {
                    $vues[$cle] = true;
                    Parametre::set(self::PREFIXE.$cle, $texte);
                }

                $normalisees[$colonne][] = ['cle' => $cle, 'texte' => $texte];
            }
        }

        // Un champ nommé qui ne porte plus de ligne a été supprimé de
        // l'en-tête : le vider évite qu'il réapparaisse à la prochaine
        // déduction, et dit la même chose aux anciens clients.
        $conservees = array_merge(
            array_column($normalisees['gauche'], 'cle'),
            array_column($normalisees['droite'], 'cle'),
        );

        foreach ([...self::COLONNE_FRANCAISE, ...self::COLONNE_ANGLAISE] as $champ) {
            if (! in_array($champ, $conservees, true)) {
                Parametre::set(self::PREFIXE.$champ, '');
            }
        }

        Parametre::set(self::PREFIXE.'lignes', json_encode($normalisees, JSON_UNESCAPED_UNICODE));
    }

    /** Valeur courante d'un champ nommé, défaut compris. */
    private function valeurChampNomme(string $champ): string
    {
        $valeur = Parametre::get(self::PREFIXE.$champ, self::CHAMPS[$champ] ?? '');

        if ($champ === 'etablissement' && $valeur === '') {
            return (string) ($this->tenants->courant()?->nom ?? '');
        }

        return $valeur;
    }

    /**
     * En-tête d'avant les lignes libres : les cinq champs de chaque colonne,
     * dans leur ordre historique, les vides retirés.
     *
     * @return array{gauche: array<int, array{cle: string|null, texte: string}>, droite: array<int, array{cle: string|null, texte: string}>}
     */
    private function lignesDesChampsNommes(): array
    {
        $colonne = fn (array $champs) => array_values(array_filter(
            array_map(
                fn (string $champ) => ['cle' => $champ, 'texte' => $this->valeurChampNomme($champ)],
                $champs,
            ),
            fn (array $ligne) => $ligne['texte'] !== '',
        ));

        return [
            'gauche' => $colonne(self::COLONNE_FRANCAISE),
            'droite' => $colonne(self::COLONNE_ANGLAISE),
        ];
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

        // Les lignes sont la forme complète de l'en-tête : les écrire en
        // dernier, pour qu'un envoi qui mêle les deux formes fasse foi sur
        // l'ordre voulu.
        if (isset($valeurs['lignes']) && is_array($valeurs['lignes'])) {
            $this->definirLignes($valeurs['lignes']);
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
        $lignes = $this->lignes();
        $textes = fn (array $colonne) => array_values(array_filter(
            array_column($colonne, 'texte'),
            fn (string $ligne) => trim($ligne) !== '',
        ));

        return [
            'gauche' => $textes($lignes['gauche']),
            'droite' => $textes($lignes['droite']),
            'contact' => $this->valeurChampNomme('contact'),
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
