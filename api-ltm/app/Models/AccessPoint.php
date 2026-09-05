<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessPoint extends Model
{
    use HasFactory;

    protected $fillable = ['bssid', 'ssid', 'password', 'label', 'etablissement_id'];

    // Le mot de passe WiFi n'a rien à faire dans les réponses du CRUD admin
    // (§4.2) — seul l'endpoint dédié à la connexion auto du mobile (§4.1) le
    // rend visible explicitement via makeVisible().
    protected $hidden = ['password'];
}
