<?php

namespace Tests\Feature;

use App\Models\AccessPoint;
use App\Models\Enseignant;
use App\Models\Presence;
use App\Models\QrPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RelayImportTest extends TestCase
{
    use RefreshDatabase;

    private function queueFile(array $lines): UploadedFile
    {
        $content = implode("\n", array_map(
            fn($line) => is_string($line) ? $line : json_encode($line),
            $lines,
        )) . "\n";

        return UploadedFile::fake()->createWithContent('queue.jsonl', $content);
    }

    /** @return array{0: Enseignant, 1: array} enseignant + paquet de base valide */
    private function scanPacket(): array
    {
        $enseignant = Enseignant::factory()->create();
        $qrPoint = QrPoint::factory()->create();
        $accessPoint = AccessPoint::factory()->create();

        return [$enseignant, [
            'local_id' => 'borne-1',
            'type' => 'scan',
            'captured_at' => '2026-09-30T07:00:00Z',
            'teacher_token' => $enseignant->createToken('mobile')->plainTextToken,
            'payload' => ['qr_code' => $qrPoint->code, 'bssid' => $accessPoint->bssid],
        ]];
    }

    public function test_lanalyse_nenregistre_rien_et_signale_les_lignes_invalides(): void
    {
        [$enseignant, $packet] = $this->scanPacket();
        $this->actingAs(User::factory()->create());

        $response = $this->post('/api/relay/import', [
            'file' => $this->queueFile([$packet, '{pas du json', ['local_id' => 'x']]),
            'dry_run' => 1,
        ])->assertOk();

        $response->assertJsonPath('total', 3);
        $response->assertJsonPath('lines.0.status', 'pending');
        $response->assertJsonPath('lines.0.enseignant', $enseignant->nom);
        $response->assertJsonPath('lines.1.status', 'invalid');
        $response->assertJsonPath('lines.2.status', 'invalid');
        $this->assertSame(0, Presence::count());
    }

    public function test_limport_enregistre_puis_ignore_les_doublons_au_second_passage(): void
    {
        [$enseignant, $packet] = $this->scanPacket();
        $depart = [...$packet, 'local_id' => 'borne-2', 'captured_at' => '2026-09-30T11:00:00Z'];
        $this->actingAs(User::factory()->create());

        $this->post('/api/relay/import', ['file' => $this->queueFile([$packet, $depart, $packet])])
            ->assertOk()
            ->assertJsonPath('lines.0.status', 'ok')
            ->assertJsonPath('lines.1.status', 'ok')
            ->assertJsonPath('lines.2.status', 'duplicate');

        $presence = Presence::where('enseignant_id', $enseignant->id)->firstOrFail();
        $this->assertSame('08:00', $presence->heure_arrivee->format('H:i'));
        $this->assertSame('12:00', $presence->heure_depart->format('H:i'));

        // Même fichier réimporté : rien ne bouge.
        $this->post('/api/relay/import', ['file' => $this->queueFile([$packet, $depart])])
            ->assertOk()
            ->assertJsonPath('summary.duplicate', 2);
        $this->assertSame('12:00', $presence->fresh()->heure_depart->format('H:i'));
    }

    public function test_un_token_disparu_peut_etre_attribue_manuellement_a_un_enseignant(): void
    {
        [$ancien, $packet] = $this->scanPacket();
        $tokenId = (int) strstr($packet['teacher_token'], '|', true);
        // Reconnexion de l'enseignant entre le scan et la synchro : l'ancien token disparaît.
        $ancien->tokens()->delete();
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $this->post('/api/relay/import', ['file' => $this->queueFile([$packet]), 'dry_run' => 1])
            ->assertJsonPath('lines.0.status', 'rejected')
            ->assertJsonPath('lines.0.unknown_token', true)
            ->assertJsonPath('lines.0.token_id', $tokenId);

        $this->post('/api/relay/import', [
            'file' => $this->queueFile([$packet]),
            'assignments' => json_encode([$tokenId => $ancien->id]),
        ])->assertOk()->assertJsonPath('lines.0.status', 'ok')->assertJsonPath('lines.0.assigned', true);

        $presence = Presence::where('enseignant_id', $ancien->id)->firstOrFail();
        $this->assertSame('manuel', $presence->source);
        $this->assertSame($admin->id, $presence->recorded_by);
        $this->assertStringContainsString("token n°{$tokenId}", $presence->reason);
    }

    public function test_une_attribution_ne_remplace_jamais_un_token_valide(): void
    {
        [$enseignant, $packet] = $this->scanPacket();
        $autre = Enseignant::factory()->create();
        $tokenId = (int) strstr($packet['teacher_token'], '|', true);
        $this->actingAs(User::factory()->create());

        $this->post('/api/relay/import', [
            'file' => $this->queueFile([$packet]),
            'assignments' => json_encode([$tokenId => $autre->id]),
        ])->assertOk()->assertJsonPath('lines.0.status', 'ok')->assertJsonPath('lines.0.assigned', false);

        $this->assertTrue(Presence::where('enseignant_id', $enseignant->id)->exists());
        $this->assertFalse(Presence::where('enseignant_id', $autre->id)->exists());
    }

    public function test_un_enseignant_ne_peut_pas_importer(): void
    {
        $enseignant = Enseignant::factory()->create();
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $this->post('/api/relay/import', ['file' => $this->queueFile(['{}'])], ['Accept' => 'application/json'])
            ->assertForbidden();
    }
}
