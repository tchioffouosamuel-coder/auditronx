<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plage de présence attendue, un jour donné, d'un membre du personnel dont
 * l'activité à l'école est générale (surveillant, intendant, secrétariat,
 * animateur...). Son assiduité se mesure sur cette plage et non sur des
 * cours : il n'a donc aucune période à rattraper (voir `BilanIndividuel`).
 */
class EmploiDuTempsSpecial extends Model
{
    use HasFactory;

    protected $table = 'emploi_du_temps_special';

    protected $fillable = ['enseignant_id', 'jour', 'heure_debut', 'heure_fin'];

    public function getHeureDebutAttribute($valeur): string
    {
        return PeriodeCours::versHeureMinute($valeur);
    }

    public function getHeureFinAttribute($valeur): string
    {
        return PeriodeCours::versHeureMinute($valeur);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }
}
