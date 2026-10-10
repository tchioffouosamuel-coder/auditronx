<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Firmware;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * OTA des bornes relais (§hardware), même cycle que campuspass : upload
 * (inactif) puis activation explicite. Différence : pas de manifest public
 * sur un bucket — le binaire contient le token API de la borne, il est donc
 * stocké en privé et servi uniquement à la borne concernée, authentifiée
 * (voir FirmwareController::manifest/download).
 */
class FirmwareService
{
    private const DISK = 'local';

    /** Octet magique d'en-tête d'une image applicative ESP32 (esp_image_header_t). */
    private const ESP_IMAGE_MAGIC = "\xE9";

    public function store(Device $device, UploadedFile $file, string $version, ?string $releaseNotes, User $uploader): Firmware
    {
        if (Firmware::where('device_id', $device->id)->where('version', $version)->exists()) {
            throw ValidationException::withMessages(['version' => ["La version {$version} existe déjà pour cette borne."]]);
        }

        // Garde-fou contre un mauvais fichier (.elf, partitions.bin,
        // bootloader...) : la borne le rejetterait de toute façon à
        // Update.begin(), mais autant le signaler dès l'upload.
        $handle = fopen($file->getRealPath(), 'rb');
        $magic = fread($handle, 1);
        fclose($handle);
        if ($magic !== self::ESP_IMAGE_MAGIC) {
            throw ValidationException::withMessages(['firmware' => ['Ce fichier n\'est pas un firmware ESP32 (attendu : .pio/build/<env>/firmware.bin).']]);
        }

        // SHA256 calculé côté serveur — ne jamais faire confiance au client.
        $sha256 = hash_file('sha256', $file->getRealPath());
        $path = $file->storeAs("firmwares/device-{$device->id}", "v{$version}.bin", self::DISK);

        return Firmware::create([
            'device_id' => $device->id,
            'version' => $version,
            'path' => $path,
            'sha256' => $sha256,
            'size_bytes' => $file->getSize(),
            'release_notes' => $releaseNotes,
            'is_active' => false,
            'uploaded_by' => $uploader->id,
        ]);
    }

    /** Une seule version active par borne : c'est celle que son manifest annonce. */
    public function activate(Firmware $firmware): void
    {
        DB::transaction(function () use ($firmware) {
            Firmware::where('device_id', $firmware->device_id)
                ->where('id', '!=', $firmware->id)
                ->update(['is_active' => false]);

            $firmware->update(['is_active' => true, 'published_at' => now()]);
        });
    }

    /** Retire la version active : la borne garde son firmware actuel. */
    public function deactivate(Firmware $firmware): void
    {
        $firmware->update(['is_active' => false]);
    }

    public function delete(Firmware $firmware): void
    {
        if ($firmware->is_active) {
            throw ValidationException::withMessages(['firmware' => ['Impossible de supprimer la version active. Désactivez-la ou activez-en une autre d\'abord.']]);
        }

        Storage::disk(self::DISK)->delete($firmware->path);
        $firmware->delete();
    }

    public function absolutePath(Firmware $firmware): string
    {
        return Storage::disk(self::DISK)->path($firmware->path);
    }
}
