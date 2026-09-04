<?php

namespace Tests\Feature;

use App\Models\DeviceActivationRequest;
use App\Models\Enseignant;
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

    public function test_ladministration_genere_lotp_et_le_flux_dactivation_se_termine(): void
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

        $pending = $this->getJson('/api/devices/activation-requests')->assertOk();
        $this->assertCount(1, $pending->json('data'));
        $requestId = $pending->json('data.0.id');

        $otpResponse = $this->postJson("/api/devices/activation-requests/{$requestId}/generate-otp")
            ->assertCreated();
        $code = $otpResponse->json('code');
        $this->assertNotEmpty($code);

        $this->assertDatabaseHas('device_activation_requests', [
            'id' => $requestId,
        ]);
        $this->assertNotNull(DeviceActivationRequest::find($requestId)->fulfilled_at);

        // La liste "en attente" ne doit plus contenir cette demande.
        $this->getJson('/api/devices/activation-requests')->assertOk()->assertJsonCount(0, 'data');

        // L'enseignant termine l'activation avec le code remis en personne.
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
}
