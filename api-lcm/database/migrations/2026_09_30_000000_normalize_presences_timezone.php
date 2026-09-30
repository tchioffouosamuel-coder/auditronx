<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalise en GMT+1 les présences enregistrées via la borne avant le
 * correctif de RelaySyncController : `captured_at` arrivait en UTC ("...Z")
 * et était stocké tel quel, soit une heure de retard (journal, retards...).
 *
 * Toute présence relayée par la borne porte un `device_capture_at` (les scans
 * directs /attendance/* le laissent à null et étaient déjà à l'heure locale) :
 * ce sont exactement ces lignes qui sont décalées.
 */
return new class extends Migration
{
    private const DECALAGE_HEURES = 1;

    public function up(): void
    {
        $this->decaler(self::DECALAGE_HEURES);
    }

    public function down(): void
    {
        $this->decaler(-self::DECALAGE_HEURES);
    }

    private function decaler(int $heures): void
    {
        DB::table('presences')
            ->whereNotNull('device_capture_at')
            ->orderBy('id')
            ->each(function (object $presence) use ($heures) {
                $maj = [];
                foreach (['heure_arrivee', 'heure_depart', 'device_capture_at'] as $colonne) {
                    if ($presence->{$colonne} !== null) {
                        $maj[$colonne] = Carbon::parse($presence->{$colonne})->addHours($heures)->format('Y-m-d H:i:s');
                    }
                }

                // Un scan entre 23h et minuit UTC appartient au jour suivant en
                // GMT+1 : on recale `date`, sauf si une ligne existe déjà pour
                // ce jour (contrainte unique enseignant_id + date).
                $reference = $maj['heure_arrivee'] ?? $maj['heure_depart'] ?? null;
                $dateActuelle = (string) $presence->date;
                if ($reference !== null && substr($reference, 0, 10) !== substr($dateActuelle, 0, 10)) {
                    $nouvelleDate = substr($reference, 0, 10);
                    $conflit = DB::table('presences')
                        ->where('enseignant_id', $presence->enseignant_id)
                        ->whereDate('date', $nouvelleDate)
                        ->where('id', '!=', $presence->id)
                        ->exists();
                    if (! $conflit) {
                        // Conserve le format de stockage existant ("Y-m-d" ou "Y-m-d H:i:s").
                        $maj['date'] = $nouvelleDate.substr($dateActuelle, 10);
                    }
                }

                DB::table('presences')->where('id', $presence->id)->update($maj);
            });
    }
};
