<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Activation immédiate par téléphone et mot de passe pour tous les enseignants.
 */
class DeviceActivationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_enseignant_admin_est_active_immediatement(): void
    {
        $admin = Enseignant::factory()->create([
            'tel' => '699000001',
            'password' => Hash::make('secret123'),
            'est_admin' => true,
        ]);

        $response = $this->postJson('/api/devices/request-activation', [
            'tel' => '699000001',
            'password' => 'secret123',
            'device_uuid' => 'device-admin-1',
        ]);

        $response->assertCreated();
        $this->assertTrue($response->json('activated'));
        $this->assertNotEmpty($response->json('token'));

        $this->assertDatabaseHas('devices', [
            'teacher_id' => $admin->id,
            'device_uuid' => 'device-admin-1',
        ]);
        $this->assertDatabaseCount('device_activation_requests', 0);
    }

    public function test_un_admin_peut_renouveler_le_token_d_une_borne_relais(): void
    {
        $admin = User::factory()->create();
        $relay = Device::factory()->create([
            'device_type' => 'relay_gateway',
            'device_uuid' => 'borne-relais-1',
            'revoked_at' => now(),
        ]);
        $ancienToken = $relay->createToken($relay->device_uuid)->plainTextToken;

        $response = $this->actingAs($admin)->postJson("/api/devices/{$relay->id}/rotate-token");

        $response->assertOk()->assertJsonStructure(['token', 'device']);
        $this->assertNotSame($ancienToken, $response->json('token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => Device::class,
            'tokenable_id' => $relay->id,
            'name' => $relay->device_uuid,
        ]);
        $this->assertNotNull($relay->fresh()->activated_at);
        $this->assertNull($relay->fresh()->revoked_at);
    }

    public function test_un_enseignant_non_admin_est_active_immediatement_sans_otp(): void
    {
        $enseignant = Enseignant::factory()->create([
            'tel' => '699000002',
            'password' => Hash::make('secret123'),
            'est_admin' => false,
        ]);

        $response = $this->postJson('/api/devices/request-activation', [
            'tel' => '699000002',
            'password' => 'secret123',
            'device_uuid' => 'device-teacher-1',
        ]);

        $response->assertCreated()->assertJson(['activated' => true]);
        $this->assertNotEmpty($response->json('token'));

        $this->assertDatabaseHas('devices', [
            'teacher_id' => $enseignant->id,
            'device_uuid' => 'device-teacher-1',
            'otp_id' => null,
        ]);
        $this->assertDatabaseCount('device_activation_requests', 0);
        $this->assertDatabaseCount('otps', 0);
    }

    public function test_identifiants_invalides_sont_rejetes(): void
    {
        Enseignant::factory()->create([
            'tel' => '699000003',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/devices/request-activation', [
            'tel' => '699000003',
            'password' => 'mauvais-mot-de-passe',
            'device_uuid' => 'device-x',
        ])->assertUnprocessable();
    }

    public function test_un_enseignant_sans_mot_de_passe_defini_est_rejete(): void
    {
        Enseignant::factory()->create(['tel' => '699000004', 'password' => null]);

        $this->postJson('/api/devices/request-activation', [
            'tel' => '699000004',
            'password' => 'peu-importe',
            'device_uuid' => 'device-y',
        ])->assertUnprocessable();
    }

    public function test_un_enseignant_ne_peut_pas_demander_un_second_telephone(): void
    {
        $enseignant = Enseignant::factory()->create([
            'tel' => '699000007',
            'password' => Hash::make('secret123'),
        ]);
        Device::factory()->create([
            'teacher_id' => $enseignant->id,
            'device_uuid' => 'device-first',
        ]);

        $this->postJson('/api/devices/request-activation', [
            'tel' => '699000007',
            'password' => 'secret123',
            'device_uuid' => 'device-second',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['device_uuid']);

        $this->assertDatabaseCount('device_activation_requests', 0);
    }

    /**
     * Régression : un device révoqué (device_uuid conservé sur le téléphone,
     * §4.1) qui se ré-active avec les mêmes identifiants ne doit pas planter sur la
     * contrainte d'unicité de device_uuid — il doit être réactivé en place.
     */
    public function test_reactivation_dun_device_revoke_avec_le_meme_uuid_ne_plante_pas(): void
    {
        $enseignant = Enseignant::factory()->create([
            'tel' => '699000006',
            'password' => Hash::make('secret123'),
        ]);

        $device = Device::factory()->create([
            'teacher_id' => $enseignant->id,
            'device_uuid' => 'device-recycled',
            'revoked_at' => now(),
        ]);

        $response = $this->postJson('/api/devices/request-activation', [
            'tel' => '699000006',
            'password' => 'secret123',
            'device_uuid' => 'device-recycled',
        ]);

        $response->assertCreated();
        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'device_uuid' => 'device-recycled',
            'otp_id' => null,
            'revoked_at' => null,
        ]);
    }
}
