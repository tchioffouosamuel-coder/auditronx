<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceLog;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_borne_pousse_ses_logs_dates_a_partir_de_son_uptime(): void
    {
        $this->freezeTime();
        $relay = Device::factory()->create(['device_type' => 'relay_gateway', 'teacher_id' => null]);
        $this->withToken($relay->createToken('esp32-borne-test')->plainTextToken);

        $this->postJson('/api/relay/logs', [
            'uptime_ms' => 60000,
            'lines' => [
                ['uptime_ms' => 1000, 'message' => '[sd] carte détectée'],
                ['uptime_ms' => 59000, 'message' => '[wifi] connecté'],
            ],
        ])->assertCreated()->assertJsonPath('stored', 2);

        $logs = DeviceLog::orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame('[sd] carte détectée', $logs[0]->message);
        $this->assertSame(now()->subSeconds(59)->toDateTimeString(), $logs[0]->logged_at->toDateTimeString());
        $this->assertSame(now()->subSecond()->toDateTimeString(), $logs[1]->logged_at->toDateTimeString());
    }

    public function test_un_telephone_enseignant_ne_peut_pas_pousser_de_logs(): void
    {
        $enseignant = Enseignant::factory()->create();
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $this->postJson('/api/relay/logs', [
            'uptime_ms' => 10,
            'lines' => [['uptime_ms' => 1, 'message' => 'x']],
        ])->assertForbidden();
    }

    public function test_le_backoffice_lit_les_logs_de_facon_incrementale(): void
    {
        $relay = Device::factory()->create(['device_type' => 'relay_gateway', 'teacher_id' => null]);
        foreach (['a', 'b', 'c'] as $i => $message) {
            DeviceLog::create(['device_id' => $relay->id, 'message' => $message, 'logged_at' => now(), 'uptime_ms' => $i]);
        }
        $this->actingAs(User::factory()->create());

        $all = $this->getJson("/api/devices/{$relay->id}/logs")->assertOk();
        $this->assertSame(['a', 'b', 'c'], array_column($all->json('data'), 'message'));

        $firstId = $all->json('data.0.id');
        $newer = $this->getJson("/api/devices/{$relay->id}/logs?after_id={$firstId}")->assertOk();
        $this->assertSame(['b', 'c'], array_column($newer->json('data'), 'message'));

        $this->deleteJson("/api/devices/{$relay->id}/logs")->assertOk()->assertJsonPath('deleted', 3);
        $this->assertSame(0, DeviceLog::count());
    }

    public function test_un_enseignant_ne_peut_pas_lire_les_logs(): void
    {
        $relay = Device::factory()->create(['device_type' => 'relay_gateway', 'teacher_id' => null]);
        $enseignant = Enseignant::factory()->create();
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $this->getJson("/api/devices/{$relay->id}/logs")->assertForbidden();
    }
}
