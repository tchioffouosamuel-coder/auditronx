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
    /**
     * Régression : un paquet vide (ligne "null" écrite par la borne quand son
     * document JSON n'a pas pu être alloué) faisait échouer TOUT le lot en
     * 422, et la borne le renvoyait en boucle, bloquant les paquets suivants.
     */
    public function test_un_paquet_invalide_est_rejete_sans_bloquer_les_autres(): void
    {
        $this->actingAsRelay();
        $enseignant = Enseignant::factory()->create();
        $token = $enseignant->createToken('mobile')->plainTextToken;
        $qrPoint = QrPoint::factory()->create();
        $accessPoint = AccessPoint::factory()->create();

        $this->postJson('/api/relay/sync', [
            'packets' => [
                null,
                ['local_id' => 'borne-incomplet', 'type' => 'scan'],
                [
                    'local_id' => 'borne-valide',
                    'type' => 'scan',
                    'captured_at' => now()->toIso8601String(),
                    'teacher_token' => $token,
                    'payload' => ['qr_code' => $qrPoint->code, 'bssid' => $accessPoint->bssid],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('results.0.status', 'rejected')
            ->assertJsonPath('results.0.local_id', null)
            ->assertJsonPath('results.1.status', 'rejected')
            ->assertJsonPath('results.1.local_id', 'borne-incomplet')
            ->assertJsonPath('results.2.status', 'ok');
    }

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
     * Régression : un départ par procuration relayé par la borne était
     * `rejected` (donc purgé de sa file, sans trace) dès que l'enseignant
     * ciblé avait déjà son arrivée du jour — aucun départ par procuration
     * n'atteignait jamais le serveur.
     */
    public function test_un_depart_par_procuration_relaye_est_enregistre(): void
    {
        $this->actingAsRelay();
        $acteur = Enseignant::factory()->create(['est_admin' => true]);
        $token = $acteur->createToken('mobile')->plainTextToken;
        $cible = Enseignant::factory()->create();
        $qrPoint = QrPoint::factory()->create();
        $accessPoint = AccessPoint::factory()->create();

        $paquet = fn (string $localId, string $capturedAt) => [
            'local_id' => $localId,
            'type' => 'admin_proxy',
            'captured_at' => $capturedAt,
            'teacher_token' => $token,
            'payload' => [
                'qr_code' => $qrPoint->code,
                'bssid' => $accessPoint->bssid,
                'enseignant_id' => $cible->id,
                'motif' => 'Téléphone en panne',
            ],
        ];

        $this->postJson('/api/relay/sync', [
            'packets' => [
                $paquet('borne-arrivee', '2026-07-02T06:30:00Z'),
                $paquet('borne-depart', '2026-07-02T14:30:00Z'),
                $paquet('borne-en-trop', '2026-07-02T16:00:00Z'),
            ],
        ])->assertOk()
            ->assertJsonPath('results.0.status', 'ok')
            ->assertJsonPath('results.1.status', 'ok')
            ->assertJsonPath('results.2.status', 'rejected');

        $presence = Presence::where('enseignant_id', $cible->id)->sole();
        $this->assertSame('07:30', $presence->heure_arrivee->format('H:i'));
        $this->assertSame('15:30', $presence->heure_depart->format('H:i'));
        $this->assertSame('admin_proxy', $presence->source);
    }

    /**
     * Régression : la borne envoie `captured_at` en UTC ("...Z") ; l'heure doit
     * être stockée en GMT+1 (fuseau de l'application), sinon le journal des
     * présences affiche une heure de retard.
     */
    public function test_lheure_utc_de_la_borne_est_stockee_en_gmt_plus_1(): void
    {
        $this->actingAsRelay();
        $enseignant = Enseignant::factory()->create();
        $token = $enseignant->createToken('mobile')->plainTextToken;
        $qrPoint = QrPoint::factory()->create();
        $accessPoint = AccessPoint::factory()->create();

        $this->postJson('/api/relay/sync', [
            'packets' => [[
                'local_id' => 'borne-test-tz',
                'type' => 'scan',
                'captured_at' => '2026-09-29T23:30:00Z',
                'teacher_token' => $token,
                'payload' => ['qr_code' => $qrPoint->code, 'bssid' => $accessPoint->bssid],
            ]],
        ])->assertOk()->assertJsonPath('results.0.status', 'ok');

        $presence = Presence::where('enseignant_id', $enseignant->id)->firstOrFail();
        $this->assertSame('2026-09-30', $presence->date->toDateString());
        $this->assertSame('00:30', $presence->heure_arrivee->format('H:i'));
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
