<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessPoint extends Model
{
    use HasFactory;

    protected $fillable = ['bssid', 'ssid', 'label', 'etablissement_id'];

    // `password` (colonne encore présente en base) n'est plus utilisé depuis
    // le passage au BLE (§hardware) : la connexion téléphone<->borne ne
    // nécessite plus d'identifiants WiFi. Caché par prudence sur d'éventuelles
    // lignes historiques qui en contiendraient encore.
    protected $hidden = ['password'];
}
