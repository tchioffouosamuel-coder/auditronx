<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Un établissement abonné = un locataire = une base de données.
 *
 * Le `code` est l'identifiant public de l'établissement : c'est lui que le
 * portail, l'app mobile et les bornes envoient dans l'en-tête `X-Tenant`, et
 * lui qui détermine le nom de la base (`auditron_<code>`), le préfixe de cache
 * et le dossier de stockage. Il est immuable une fois l'établissement
 * provisionné — le renommer impliquerait de renommer la base et de déplacer
 * les fichiers.
 */
class Etablissement extends ModeleCentral
{
    public const STATUT_EN_ATTENTE = 'en_attente';

    public const STATUT_ACTIF = 'actif';

    public const STATUT_SUSPENDU = 'suspendu';

    public const STATUT_ARCHIVE = 'archive';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_ACTIF,
        self::STATUT_SUSPENDU,
        self::STATUT_ARCHIVE,
    ];

    protected $table = 'etablissements';

    protected $fillable = [
        'code',
        'nom',
        'nom_court',
        'ville',
        'pays',
        'fuseau',
        'logo_path',
        'couleur_primaire',
        'couleur_secondaire',
        'db_name',
        'db_username',
        'db_password',
        'statut',
        'contact_nom',
        'contact_email',
        'contact_tel',
        'notes',
        'provisionne_le',
        'suspendu_le',
    ];

    protected $casts = [
        'provisionne_le' => 'datetime',
        'suspendu_le' => 'datetime',
        // Chiffré avec APP_KEY : la base centrale contient les accès aux bases
        // de tous les clients, c'est l'information la plus sensible de la
        // plateforme. Une sauvegarde SQL dérobée ne doit pas les livrer.
        'db_password' => 'encrypted',
    ];

    /**
     * Jamais sérialisé : le portail éditeur affiche et modifie les accès à une
     * base, mais ne les relit pas. Les sortir par défaut d'une réponse JSON
     * serait un accident en attente.
     */
    protected $hidden = ['db_password'];

    /**
     * Surcharges de connexion propres à cet établissement, appliquées par
     * App\Tenancy\TenantManager. Les clés absentes gardent la valeur du
     * `.env` — on ne remplace que ce qui est explicitement renseigné.
     *
     * @return array<string, string>
     */
    public function surchargesConnexion(): array
    {
        return array_filter([
            'username' => $this->db_username,
            'password' => $this->db_password,
        ], fn ($valeur) => $valeur !== null && $valeur !== '');
    }

    /**
     * Normalise un code saisi (en-tête HTTP, formulaire, ligne de commande)
     * vers sa forme canonique : majuscules, sans espace, lettres/chiffres/tiret
     * uniquement. Le code finit dans un nom de base de données et dans un
     * chemin de fichier : tout caractère hors de cet alphabet est refusé ici
     * plutôt que de poser un problème trois couches plus bas.
     */
    public static function normaliseCode(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        return (string) preg_replace('/[^A-Z0-9_-]/', '', $code);
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }

    /** Dernier abonnement en cours de validité, s'il y en a un. */
    public function abonnementActif(): HasOne
    {
        return $this->hasOne(Abonnement::class)
            ->where('statut', Abonnement::STATUT_ACTIF)
            ->whereDate('debut_le', '<=', now())
            ->where(fn ($q) => $q->whereNull('fin_le')->orWhereDate('fin_le', '>=', now()))
            ->latestOfMany();
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Facture::class);
    }

    public function bornes(): HasMany
    {
        return $this->hasMany(Borne::class);
    }

    public function estActif(): bool
    {
        return $this->statut === self::STATUT_ACTIF;
    }

    public function estSuspendu(): bool
    {
        return $this->statut === self::STATUT_SUSPENDU;
    }

    /** L'établissement a-t-il une base utilisable (provisioning terminé) ? */
    public function estProvisionne(): bool
    {
        return $this->provisionne_le !== null;
    }

    /**
     * Branding servi au portail et à l'app mobile : une seule app, mais les
     * couleurs, le nom et le logo de l'établissement courant.
     *
     * @return array<string, string|null>
     */
    public function branding(): array
    {
        return [
            'code' => $this->code,
            'nom' => $this->nom,
            'nom_court' => $this->nom_court ?: $this->code,
            'ville' => $this->ville,
            'logo_url' => $this->logo_path
                ? rtrim((string) config('app.url'), '/') . '/' . ltrim($this->logo_path, '/')
                : null,
            'couleur_primaire' => $this->couleur_primaire,
            'couleur_secondaire' => $this->couleur_secondaire,
            'fuseau' => $this->fuseau,
        ];
    }
}
