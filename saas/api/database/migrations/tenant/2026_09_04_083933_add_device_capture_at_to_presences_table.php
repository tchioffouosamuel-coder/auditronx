<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Passerelle offline (ESP1 borne + ESP2 relais, §hardware) : quand le scan
     * transite par le relais, l'horodatage serveur (heure_arrivee/depart) ne
     * peut plus être "now()" au moment de la requête — le paquet peut avoir
     * attendu en file plusieurs heures faute de réseau. On fait alors
     * exceptionnellement confiance à l'horodatage de capture remonté par le
     * matériel de la borne (device relay authentifié, pas le téléphone de
     * l'enseignant), tout en conservant ce champ pour audit/traçabilité (§7).
     */
    public function up(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->dateTime('device_capture_at')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->dropColumn('device_capture_at');
        });
    }
};
