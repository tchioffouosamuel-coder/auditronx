<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Compte éditeur (Auditron), distinct des comptes d'établissement.
 *
 * Vit dans la base centrale et n'a accès qu'aux routes `/api/central/*` :
 * aucun compte éditeur n'est authentifiable sur les routes métier d'un
 * établissement, et réciproquement. Un membre du support qui a besoin de voir
 * les données d'un client doit y être invité comme utilisateur de cet
 * établissement — la frontière est volontairement nette pour que le journal
 * d'audit du client reste lisible.
 */
class PlatformUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_SUPPORT = 'support';

    public const ROLE_COMMERCIAL = 'commercial';

    public const ROLES = [self::ROLE_SUPER_ADMIN, self::ROLE_SUPPORT, self::ROLE_COMMERCIAL];

    protected $connection = 'central';

    protected $table = 'platform_users';

    protected $fillable = ['name', 'email', 'password', 'role', 'actif'];

    /**
     * Valeurs par défaut portées par le modèle, et pas seulement par le schéma :
     * une instance tout juste créée doit déjà se comporter comme un compte
     * actif, sans relecture en base. Un `actif` nul serait interprété comme
     * « compte désactivé » par EnsurePlatformUser.
     */
    protected $attributes = [
        'role' => self::ROLE_SUPPORT,
        'actif' => true,
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'actif' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    public function estSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /** Seul le super-admin crée, provisionne, suspend ou supprime un client. */
    public function peutAdministrer(): bool
    {
        return $this->actif && $this->estSuperAdmin();
    }
}
