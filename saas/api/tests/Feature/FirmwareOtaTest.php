<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Firmware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FirmwareOtaTest extends TestCase
{
    use RefreshDatabase;

    private function relay(): Device
    {
        return Device::factory()->create(['device_type' => 'relay_gateway', 'teacher_id' => null]);
    }

    /** En-tête d'image ESP32 (0xE9) + contenu arbitraire. */
    private function binFile(string $content = 'firmware'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('firmware.bin', "\xE9" . $content);
    }

    public function test_upload_puis_activation_publie_le_manifest_de_la_borne(): void
    {
        Storage::fake('local');
        $relay = $this->relay();
        $this->actingAs(User::factory()->create());

        $id = $this->post('/api/firmwares', [
            'device_id' => $relay->id,
            'version' => '1.1.0',
            'firmware' => $this->binFile(),
        ], ['Accept' => 'application/json'])->assertCreated()->json('id');

        $firmware = Firmware::findOrFail($id);
        $this->assertSame(hash('sha256', "\xE9firmware"), $firmware->sha256);
        $this->assertFalse($firmware->is_active);
        Storage::disk('local')->assertExists($firmware->path);

        // Pas encore activé : la borne ne voit rien à installer.
        $token = $relay->createToken('borne')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/relay/firmware/manifest', ['X-Firmware-Version' => '1.0.0'])->assertNoContent();
        $this->assertSame('1.0.0', $relay->fresh()->firmware_version);

        $this->app['auth']->forgetGuards();
        $this->actingAs(User::factory()->create())->postJson("/api/firmwares/{$id}/activate")->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/relay/firmware/manifest')
            ->assertOk()
            ->assertJsonPath('version', '1.1.0')
            ->assertJsonPath('sha256', $firmware->sha256)
            ->assertJsonPath('size_bytes', strlen("\xE9firmware"));

        $this->withToken($token)->get("/api/relay/firmware/{$id}/download")->assertOk();
    }

    public function test_une_borne_ne_peut_pas_telecharger_le_firmware_dune_autre(): void
    {
        Storage::fake('local');
        $firmware = Firmware::create([
            'device_id' => $this->relay()->id,
            'version' => '1.0.0',
            'path' => 'firmwares/x.bin',
            'sha256' => str_repeat('a', 64),
            'size_bytes' => 10,
            'is_active' => true,
        ]);
        $autre = $this->relay();

        $this->withToken($autre->createToken('borne')->plainTextToken)
            ->get("/api/relay/firmware/{$firmware->id}/download")
            ->assertNotFound();
    }

    public function test_un_fichier_qui_nest_pas_une_image_esp32_est_refuse(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create());

        $this->post('/api/firmwares', [
            'device_id' => $this->relay()->id,
            'version' => '1.1.0',
            'firmware' => UploadedFile::fake()->createWithContent('firmware.bin', 'ELF...'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('firmware');
    }

    public function test_la_version_active_ne_peut_pas_etre_supprimee(): void
    {
        Storage::fake('local');
        $firmware = Firmware::create([
            'device_id' => $this->relay()->id,
            'version' => '1.0.0',
            'path' => 'firmwares/x.bin',
            'sha256' => str_repeat('a', 64),
            'size_bytes' => 10,
            'is_active' => true,
        ]);
        $this->actingAs(User::factory()->create());

        $this->deleteJson("/api/firmwares/{$firmware->id}")->assertUnprocessable();
        $this->postJson("/api/firmwares/{$firmware->id}/deactivate")->assertOk();
        $this->deleteJson("/api/firmwares/{$firmware->id}")->assertOk();
        $this->assertSame(0, Firmware::count());
    }
}
