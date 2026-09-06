<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceActivationRequest;
use App\Models\Enseignant;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Flux d'activation revu : identification par tel + mot de passe, accès direct
 * pour un enseignant admin, sinon demande transmise à l'administration qui
 * génère et remet l'OTP en personne.
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

    public function test_un_enseignant_non_admin_declenche_une_demande_sans_activation_immediate(): void
    {
        Enseignant::factory()->create([
            'tel' => '699000002',
            'password' => Hash::make('secret123'),
            'est_admin' => false,
        ]);

        $response = $this->postJson('/api/devices/request-activation', [
            'tel' => '699000002',
            'password' => 'secret123',
            'device_uuid' => 'device-teacher-1',
        ]);

        $response->assertStatus(202);
        $this->assertFalse($response->json('activated'));
        $this->assertArrayNotHasKey('token', $response->json());

        $this->assertDatabaseHas('device_activation_requests', [
            'device_uuid' => 'device-teacher-1',
            'fulfilled_at' => null,
        ]);
        $this->assertDatabaseCount('devices', 0);
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

    public function test_ladministration_valide_la_demande_et_le_flux_dactivation_se_termine(): void
    {
        $enseignant = Enseignant::factory()->create([
            'tel' => '699000005',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/devices/request-activation', [
            'tel' => '699000005',
            'password' => 'secret123',
            'device_uuid' => 'device-teacher-2',
        ])->assertStatus(202);

        $admin = User::factory()->create();
        $this->withToken($admin->createToken('backoffice')->plainTextToken);

        // L'OTP est déjà généré à la demande (notification de validation
        // envoyée aux admins) : le code est visible ici en secours de la push.
        $pending = $this->getJson('/api/devices/activation-requests')->assertOk();
        $this->assertCount(1, $pending->json('data'));
        $requestId = $pending->json('data.0.id');
        $code = $pending->json('data.0.code');
        $this->assertNotEmpty($code);

        $this->postJson("/api/devices/activation-requests/{$requestId}/approve")->assertOk();

        $this->assertNotNull(DeviceActivationRequest::find($requestId)->fulfilled_at);

        // La liste "en attente" ne doit plus contenir cette demande.
        $this->getJson('/api/devices/activation-requests')->assertOk()->assertJsonCount(0, 'data');

        // L'enseignant termine l'activation avec le code poussé par notification.
        $activation = $this->postJson('/api/devices/activate', [
            'code' => $code,
            'device_uuid' => 'device-teacher-2',
        ]);

        $activation->assertCreated();
        $this->assertDatabaseHas('devices', [
            'teacher_id' => $enseignant->id,
            'device_uuid' => 'device-teacher-2',
        ]);
    }

    public function test_ladministration_peut_refuser_une_demande_dactivation(): void
    {
        Enseignant::factory()->create([
            'tel' => '699000009',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/devices/request-activation', [
            'tel' => '699000009',
            'password' => 'secret123',
            'device_uuid' => 'device-teacher-3',
        ])->assertStatus(202);

        $admin = User::factory()->create();
        $this->withToken($admin->createToken('backoffice')->plainTextToken);

        $requestId = $this->getJson('/api/devices/activation-requests')->json('data.0.id');

        $this->postJson("/api/devices/activation-requests/{$requestId}/reject")->assertOk();

        $this->assertNotNull(DeviceActivationRequest::find($requestId)->rejected_at);
        $this->getJson('/api/devices/activation-requests')->assertOk()->assertJsonCount(0, 'data');
    }

    /**
     * Régression : un device révoqué (device_uuid conservé sur le téléphone,
     * §4.1) qui se ré-active avec un nouvel OTP ne doit pas planter sur la
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

        $code = '123456';
        $otp = Otp::create([
            'teacher_id' => $enseignant->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/devices/activate', [
            'code' => $code,
            'device_uuid' => 'device-recycled',
        ]);

        $response->assertCreated();
        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'device_uuid' => 'device-recycled',
            'otp_id' => $otp->id,
            'revoked_at' => null,
        ]);
    }

    public function test_un_otp_ne_peut_pas_activer_un_second_telephone(): void
    {
        $enseignant = Enseignant::factory()->create([
            'tel' => '699000008',
            'password' => Hash::make('secret123'),
        ]);
        Device::factory()->create([
            'teacher_id' => $enseignant->id,
            'device_uuid' => 'device-first-otp',
        ]);
        $otp = Otp::create([
            'teacher_id' => $enseignant->id,
            'code_hash' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->postJson('/api/devices/activate', [
            'code' => '654321',
            'device_uuid' => 'device-second-otp',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['device_uuid']);

        $this->assertDatabaseHas('otps', ['id' => $otp->id, 'used_at' => null]);
        $this->assertDatabaseCount('devices', 1);
    }
}
