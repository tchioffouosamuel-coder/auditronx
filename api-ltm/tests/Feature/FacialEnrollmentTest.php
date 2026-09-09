<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Enseignant;
use App\Models\User;
use App\Models\VisageEmbedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Enrôlement facial embarqué (§5, ESP32-S3 + ESP-WHO) : upload de la photo de
 * référence depuis la plateforme web, puis récupération par la borne via le
 * manifest kiosque (photos à enrôler + embeddings déjà partagés).
 */
class FacialEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsBackoffice(): User
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    private function actingAsKiosk(): Device
    {
        $kiosk = Device::factory()->create(['device_type' => 'kiosk_facial', 'teacher_id' => null]);
        $this->withToken($kiosk->createToken('esp32-kiosk-test')->plainTextToken);

        return $kiosk;
    }

    public function test_upload_de_la_photo_dun_enseignant_met_a_jour_photo_path_et_photo_url(): void
    {
        Storage::fake('public_direct', ['url' => rtrim(config('app.url'), '/')]);

        $this->actingAsBackoffice();
        $enseignant = Enseignant::factory()->create();

        $response = $this->postJson("/api/personnel/{$enseignant->id}/photo", [
            'photo' => UploadedFile::fake()->image('prof.jpg'),
        ]);

        $response->assertOk();
        $enseignant->refresh();

        $this->assertNotNull($enseignant->photo_path);
        $this->assertNotNull($enseignant->photo_updated_at);
        Storage::disk('public_direct')->assertExists($enseignant->photo_path);
        $this->assertStringNotContainsString('/storage/', $enseignant->photo_url);
    }

    public function test_suppression_de_la_photo_efface_le_fichier_et_les_metadonnees(): void
    {
        Storage::fake('public_direct', ['url' => rtrim(config('app.url'), '/')]);

        $this->actingAsBackoffice();
        $enseignant = Enseignant::factory()->create([
            'photo_path' => 'teacher-photos/existing.jpg',
            'photo_updated_at' => now(),
        ]);
        Storage::disk('public_direct')->put($enseignant->photo_path, 'photo');

        $this->deleteJson("/api/personnel/{$enseignant->id}/photo")
            ->assertOk()
            ->assertJsonPath('photo_url', null);

        $enseignant->refresh();
        $this->assertNull($enseignant->photo_path);
        $this->assertNull($enseignant->photo_updated_at);
        Storage::disk('public_direct')->assertMissing('teacher-photos/existing.jpg');
    }

    public function test_le_manifest_kiosque_liste_les_photos_a_enroler_et_les_embeddings_actifs(): void
    {
        Storage::fake('public_direct', ['url' => rtrim(config('app.url'), '/')]);

        $enseignant = Enseignant::factory()->create([
            'photo_path' => 'teacher-photos/x.jpg',
            'photo_updated_at' => now(),
        ]);
        $autreEnseignant = Enseignant::factory()->create();
        VisageEmbedding::create([
            'enseignant_id' => $autreEnseignant->id,
            'embedding' => [0.1, 0.2, 0.3],
            'enrolled_at' => now(),
        ]);

        $this->actingAsKiosk();

        $response = $this->getJson('/api/kiosks/manifest')->assertOk();

        $response->assertJsonPath('enseignants.0.id', $enseignant->id);
        $response->assertJsonPath('embeddings.0.enseignant_id', $autreEnseignant->id);
    }

    public function test_lenrolement_facial_par_une_borne_kiosque_attribue_le_device_authentifie(): void
    {
        $kiosk = $this->actingAsKiosk();
        $enseignant = Enseignant::factory()->create();

        $this->postJson('/api/visages/enroll', [
            'enseignant_id' => $enseignant->id,
            // Un device_id usurpé dans le body doit être ignoré : celui du device authentifié prévaut.
            'device_id' => Device::factory()->create()->id,
            'embedding' => [0.4, 0.5, 0.6],
        ])->assertCreated();

        $this->assertDatabaseHas('visages_embeddings', [
            'enseignant_id' => $enseignant->id,
            'device_id' => $kiosk->id,
        ]);
    }

    public function test_un_utilisateur_hors_perimetre_ne_peut_pas_uploader_la_photo_dun_enseignant(): void
    {
        $accreditation = \App\Models\Accreditation::create(['label' => 'Section A', 'groupe' => 'Section A']);
        $user = User::factory()->create(['accreditation_id' => $accreditation->id]);
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        $enseignant = Enseignant::factory()->create(['section' => 'Section B']);

        $this->postJson("/api/personnel/{$enseignant->id}/photo", [
            'photo' => UploadedFile::fake()->image('prof.jpg'),
        ])->assertForbidden();
    }
}
