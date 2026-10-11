<?php

namespace Tests\Feature;

use App\Models\Central\Etablissement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `auditron:diagnostic` doit nommer les causes, pas seulement les symptômes.
 *
 * Elle rapportait « injoignable : The MAC is invalid » pour chaque
 * établissement quand l'APP_KEY avait changé — message exact, mais qui
 * n'oriente vers rien. Ce test fixe l'attente inverse.
 */
class DiagnosticTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_mot_de_passe_chiffre_avec_une_autre_cle_est_nomme(): void
    {
        $etablissement = Etablissement::where('code', 'TEST')->firstOrFail();

        // Chiffré bien formé dont le MAC ne correspond plus : ce que laisse
        // en base un changement d'APP_KEY.
        $payload = json_decode(base64_decode(Crypt::encryptString('ancien')), true);
        $payload['mac'] = str_repeat('0', strlen($payload['mac']));

        DB::connection('central')->table('etablissements')
            ->where('id', $etablissement->id)
            ->update(['db_password' => base64_encode(json_encode($payload))]);

        $this->artisan('auditron:diagnostic --etablissement=TEST')
            ->expectsOutputToContain('mot de passe de base illisible')
            ->expectsOutputToContain('autre APP_KEY')
            ->expectsOutputToContain('etablissement:identifiants')
            ->assertFailed();
    }
}
