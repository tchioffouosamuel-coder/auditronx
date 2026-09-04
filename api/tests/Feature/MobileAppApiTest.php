<?php

namespace Tests\Feature;

use App\Models\AccessPoint;
use App\Models\Device;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Models\QrPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileAppApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_enseignant_consulte_son_propre_historique_de_presences(): void
    {
        $enseignant = Enseignant::factory()->create();
        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => now()->toDateString(),
            'heure_arrivee' => now()->toDateString().' 08:00:00',
        ]);

        Sanctum::actingAs($enseignant);
        $response = $this->getJson('/api/mes-presences');

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame(now()->toDateString(), $response->json('0.date'));
    }

    public function test_un_scan_par_procuration_cree_une_notification_pour_lenseignant_concerne(): void
    {
        $acteur = Enseignant::factory()->create();
        $cible = Enseignant::factory()->create();
        Device::factory()->for($acteur, 'teacher')->create();

        QrPoint::factory()->create(['code' => 'QR-1']);
        AccessPoint::factory()->create(['bssid' => 'AA:BB:CC:DD:EE:FF']);

        Sanctum::actingAs($acteur);
        $this->postJson('/api/attendance/admin-proxy', [
            'enseignant_id' => $cible->id,
            'qr_code' => 'QR-1',
            'bssid' => 'AA:BB:CC:DD:EE:FF',
            'motif' => 'Téléphone oublié',
        ])->assertCreated();

        Sanctum::actingAs($cible);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('id', $cible->id);

        $notifs = $this->getJson('/api/notifications')->assertOk();
        $this->assertCount(1, $notifs->json());
        $this->assertNull($notifs->json('0.read_at'));

        $notificationId = $notifs->json('0.id');
        $this->postJson("/api/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('read_at', fn ($v) => $v !== null);
    }
}
