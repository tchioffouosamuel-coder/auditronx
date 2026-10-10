<?php

namespace Tests\Feature;

use App\Models\Enseignant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NormalizePresencesTimezoneMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        // Migration métier : elle vit dans `migrations/tenant` depuis la
        // séparation entre base centrale et bases d'établissement.
        return require database_path('migrations/tenant/2026_09_30_000000_normalize_presences_timezone.php');
    }

    public function test_les_presences_de_la_borne_sont_decalees_en_gmt_plus_1(): void
    {
        $borne = Enseignant::factory()->create();
        $direct = Enseignant::factory()->create();
        $minuit = Enseignant::factory()->create();

        $idBorne = DB::table('presences')->insertGetId([
            'enseignant_id' => $borne->id, 'date' => '2026-09-29', 'source' => 'app_mobile',
            'heure_arrivee' => '2026-09-29 07:30:00', 'heure_depart' => '2026-09-29 15:00:00',
            'device_capture_at' => '2026-09-29 15:00:00',
        ]);
        $idDirect = DB::table('presences')->insertGetId([
            'enseignant_id' => $direct->id, 'date' => '2026-09-29', 'source' => 'app_mobile',
            'heure_arrivee' => '2026-09-29 08:00:00', 'device_capture_at' => null,
        ]);
        $idMinuit = DB::table('presences')->insertGetId([
            'enseignant_id' => $minuit->id, 'date' => '2026-09-29', 'source' => 'app_mobile',
            'heure_arrivee' => '2026-09-29 23:30:00', 'device_capture_at' => '2026-09-29 23:30:00',
        ]);

        $this->migration()->up();

        $presenceBorne = DB::table('presences')->find($idBorne);
        $this->assertSame('2026-09-29 08:30:00', $presenceBorne->heure_arrivee);
        $this->assertSame('2026-09-29 16:00:00', $presenceBorne->heure_depart);
        $this->assertSame('2026-09-29 16:00:00', $presenceBorne->device_capture_at);

        $this->assertSame('2026-09-29 08:00:00', DB::table('presences')->find($idDirect)->heure_arrivee);

        $presenceMinuit = DB::table('presences')->find($idMinuit);
        $this->assertSame('2026-09-30 00:30:00', $presenceMinuit->heure_arrivee);
        $this->assertStringStartsWith('2026-09-30', (string) $presenceMinuit->date);

        $this->migration()->down();

        $this->assertSame('2026-09-29 07:30:00', DB::table('presences')->find($idBorne)->heure_arrivee);
        $this->assertStringStartsWith('2026-09-29', (string) DB::table('presences')->find($idMinuit)->date);
    }
}
