<?php

namespace Tests\Feature;

use App\Models\AccessPoint;
use App\Models\Device;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Models\QrPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RelaySyncTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRelay(): void
    {
        $relay = Device::factory()->create(['device_type' => 'relay_gateway', 'teacher_id' => null]);
        $this->withToken($relay->createToken('esp32-borne-test')->plainTextToken);
    }

    /**
     * Régression : un QR code inconnu ne se "réparera" jamais tout seul — la
     * borne doit pouvoir le purger de sa file (statut "rejected"), pas le
     * garder en "retry" indéfiniment (voir AttendanceRecorder::resolveAccessPoint).
     */
    public function test_un_qr_code_inconnu_est_rejete_definitivement_pas_retente(): void
    {
        $this->actingAsRelay();
        $enseignant = Enseignant::factory()->create();
        $token = $enseignant->createToken('mobile')->plainTextToken;

        $response = $this->postJson('/api/relay/sync', [
            'packets' => [[
                'local_id' => 'borne-test-1',
                'type' => 'scan',
                'captured_at' => now()->toIso8601String(),
                'teacher_token' => $token,
                'payload' => ['qr_code' => 'QR-INEXISTANT', 'bssid' => 'AA:BB:CC:DD:EE:FF'],
            ]],
        ])->assertOk();

        $response->assertJsonPath('results.0.local_id', 'borne-test-1');
        $response->assertJsonPath('results.0.status', 'rejected');
    }

    /**
     * Régression : qr_code/bssid doivent atteindre AttendanceRecorder intacts
     * — un souci de validation Laravel les faisait disparaître silencieusement
     * du tableau `payload` validé (voir commentaire dans RelaySyncController),
     * ce qui aurait fait échouer TOUT scan relayé par la borne avec "QR code
     * non reconnu", même pour un QR/BSSID parfaitement valides.
     */
    public function test_un_scan_relaye_valide_est_accepte(): void
    {
        $this->actingAsRelay();
        $enseignant = Enseignant::factory()->create();
        $token = $enseignant->createToken('mobile')->plainTextToken;
        $qrPoint = QrPoint::factory()->create();
        $accessPoint = AccessPoint::factory()->create();

        $response = $this->postJson('/api/relay/sync', [
            'packets' => [[
                'local_id' => 'borne-test-2',
                'type' => 'scan',
                'captured_at' => now()->toIso8601String(),
                'teacher_token' => $token,
                'payload' => ['qr_code' => $qrPoint->code, 'bssid' => $accessPoint->bssid],
            ]],
        ])->assertOk();

        $response->assertJsonPath('results.0.status', 'ok');
        $this->assertDatabaseHas('presences', ['enseignant_id' => $enseignant->id]);
    }

    /**
     * Régression : `symlink()`/`exec()` sont désactivés sur l'hébergement de
     * production, donc `storage:link` (et le disque `public`) ne peuvent
     * jamais servir les photos — elles doivent être écrites directement dans
     * `public/` (disque `public_direct`) pour être accessibles sans lien
     * symbolique (voir Presence::getPhotoUrlArriveeAttribute).
     */
    public function test_la_photo_est_stockee_sans_lien_symbolique_et_lurl_est_accessible(): void
    {
        // Storage::fake() ne préserve pas la config `url` du disque d'origine
        // (voir Storage::buildDiskConfiguration) — sans ça, le faux disque
        // retombe sur la convention par défaut de Laravel (/storage/...),
        // masquant justement le bug qu'on veut couvrir ici.
        Storage::fake('public_direct', ['url' => rtrim(config('app.url'), '/')]);

        $this->actingAsRelay();
        $enseignant = Enseignant::factory()->create();
        $token = $enseignant->createToken('mobile')->plainTextToken;
        $qrPoint = QrPoint::factory()->create();
        $accessPoint = AccessPoint::factory()->create();

        $this->postJson('/api/relay/sync', [
            'packets' => [[
                'local_id' => 'borne-test-3',
                'type' => 'scan',
                'captured_at' => now()->toIso8601String(),
                'teacher_token' => $token,
                'payload' => [
                    'qr_code' => $qrPoint->code,
                    'bssid' => $accessPoint->bssid,
                    'photo_base64' => base64_encode('donnee-jpeg-factice'),
                ],
            ]],
        ])->assertOk()->assertJsonPath('results.0.status', 'ok');

        $presence = Presence::where('enseignant_id', $enseignant->id)->firstOrFail();

        $this->assertNotNull($presence->photo_path_arrivee);
        Storage::disk('public_direct')->assertExists($presence->photo_path_arrivee);
        // Ni /storage/ (lien symbolique) : le fichier est servi directement
        // depuis public/, sans dépendre de storage:link.
        $this->assertStringNotContainsString('/storage/', $presence->photo_url_arrivee);
    }
}
