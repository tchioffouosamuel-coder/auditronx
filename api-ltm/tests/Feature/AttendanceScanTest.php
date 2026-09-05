<?php

namespace Tests\Feature;

use App\Models\AccessPoint;
use App\Models\Device;
use App\Models\Enseignant;
use App\Models\Otp;
use App\Models\Presence;
use App\Models\QrPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendanceScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_enseignant_peut_sactiver_puis_scanner_sa_presence(): void
    {
        $enseignant = Enseignant::factory()->create();
        $otp = Otp::create([
            'teacher_id' => $enseignant->id,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $activation = $this->postJson('/api/devices/activate', [
            'code' => '123456',
            'device_uuid' => 'device-uuid-1',
        ]);

        $activation->assertCreated();
        $token = $activation->json('token');

        $this->assertDatabaseHas('devices', [
            'teacher_id' => $enseignant->id,
            'device_uuid' => 'device-uuid-1',
        ]);
        $this->assertNotNull($otp->fresh()->used_at);

        $qrPoint = QrPoint::factory()->create(['code' => 'QR-PORTAIL-1']);
        $accessPoint = AccessPoint::factory()->create(['bssid' => 'AA:BB:CC:DD:EE:FF']);

        $scan = $this->withToken($token)->postJson('/api/attendance/scan', [
            'qr_code' => $qrPoint->code,
            'bssid' => $accessPoint->bssid,
        ]);

        $scan->assertCreated();

        // Contrat presences (§6.1) : une ligne par enseignant/jour avec heure_arrivee horodatée serveur.
        $this->assertDatabaseHas('presences', [
            'enseignant_id' => $enseignant->id,
            'source' => 'app_mobile',
            'access_point_id' => $accessPoint->id,
        ]);

        $presence = Presence::first();
        $this->assertSame(now()->toDateString(), $presence->date->toDateString());
        $this->assertNotNull($presence->heure_arrivee);
        $this->assertNull($presence->heure_depart);
    }

    public function test_le_scan_est_rejete_si_le_bssid_est_inconnu(): void
    {
        $enseignant = Enseignant::factory()->create();
        $device = Device::factory()->for($enseignant, 'teacher')->create(['device_uuid' => 'device-uuid-2']);
        $token = $enseignant->createToken($device->device_uuid)->plainTextToken;
        $qrPoint = QrPoint::factory()->create();

        $scan = $this->withToken($token)->postJson('/api/attendance/scan', [
            'qr_code' => $qrPoint->code,
            'bssid' => 'FF:FF:FF:FF:FF:FF',
        ]);

        $scan->assertUnprocessable();
        $this->assertDatabaseCount('presences', 0);
    }

    public function test_un_scan_par_procuration_journalise_lauteur_et_le_motif(): void
    {
        $acteur = Enseignant::factory()->create();
        $device = Device::factory()->for($acteur, 'teacher')->create(['device_uuid' => 'device-uuid-3']);
        $token = $acteur->createToken($device->device_uuid)->plainTextToken;

        $cible = Enseignant::factory()->create();
        $qrPoint = QrPoint::factory()->create();
        $accessPoint = AccessPoint::factory()->create();

        $scan = $this->withToken($token)->postJson('/api/attendance/admin-proxy', [
            'enseignant_id' => $cible->id,
            'qr_code' => $qrPoint->code,
            'bssid' => $accessPoint->bssid,
            'motif' => 'Téléphone en panne',
        ]);

        $scan->assertCreated();

        $this->assertDatabaseHas('presences', [
            'enseignant_id' => $cible->id,
            'source' => 'admin_proxy',
            'device_id' => $device->id,
            'reason' => 'Téléphone en panne',
        ]);
    }

    public function test_le_scan_facial_produit_une_ligne_presence_compatible(): void
    {
        $enseignant = Enseignant::factory()->create();
        $kiosk = Device::factory()->create([
            'teacher_id' => null,
            'device_type' => 'kiosk_facial',
            'device_uuid' => 'kiosk-1',
        ]);
        $token = $kiosk->createToken($kiosk->device_uuid)->plainTextToken;

        $scan = $this->withToken($token)->postJson('/api/attendance/facial-scan', [
            'enseignant_id' => $enseignant->id,
            'score_confiance' => 0.92,
        ]);

        $scan->assertCreated();

        $this->assertDatabaseHas('presences', [
            'enseignant_id' => $enseignant->id,
            'source' => 'reconnaissance_faciale',
            'device_id' => $kiosk->id,
        ]);
    }
}
