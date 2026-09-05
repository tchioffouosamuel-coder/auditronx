<?php

namespace Database\Seeders;

use App\Models\AccessPoint;
use App\Models\Accreditation;
use App\Models\Device;
use App\Models\Enseignant;
use App\Models\QrPoint;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Amorçage minimal pour accéder à la plateforme sur un environnement neuf
 * (aucun compte n'existe après les migrations). Idempotent : rejouable sans
 * dupliquer (updateOrCreate partout), donc safe en re-`db:seed`.
 *
 * Mots de passe volontairement fixes et faibles ("ChangeMe123!") : c'est un
 * amorçage, pas un provisioning de prod — à changer immédiatement après la
 * première connexion.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $direction = Accreditation::updateOrCreate(
            ['label' => 'Direction'],
            ['groupe' => '*', 'niveau' => null] // '*' = accès total (§ modèle Accreditation)
        );

        $admin = User::updateOrCreate(
            ['email' => 'superadmin@auditron.ltm'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('ChangeMe123!'),
                'accreditation_id' => $direction->id,
                'email_verified_at' => now(),
            ]
        );

        // Enseignant admin (`est_admin`) : active immédiatement son device sans
        // passer par le flux OTP (voir DeviceController::requestActivation) —
        // utile pour tester l'app mobile sans manipulation d'administration.
        $enseignant = Enseignant::updateOrCreate(
            ['tel' => '+237600000000'],
            [
                'nom' => 'Admin Test',
                'matricule' => 'ADM-0001',
                'email' => 'enseignant.admin@auditron.ltm',
                'password' => Hash::make('ChangeMe123!'),
                'est_admin' => true,
            ]
        );

        $qrPoint = QrPoint::updateOrCreate(
            ['code' => 'QR-TEST-01'],
            ['label' => 'Point de test']
        );

        $accessPoint = AccessPoint::updateOrCreate(
            ['bssid' => 'AA:BB:CC:DD:EE:01'],
            ['ssid' => 'AUDITRON-BORNE-01', 'label' => 'Point de test']
        );

        // Device relay_gateway (§hardware) : provisionne la borne ESP32 pour
        // tester /api/relay/sync sans passer par l'endpoint HTTP d'admin.
        $relayDevice = Device::updateOrCreate(
            ['device_uuid' => 'esp32-borne-seed'],
            ['device_type' => 'relay_gateway', 'activated_at' => now()]
        );
        $relayDevice->tokens()->delete();
        $relayToken = $relayDevice->createToken('esp32-borne-seed')->plainTextToken;

        $this->command?->info('--- Comptes créés (mots de passe à changer) ---');
        $this->command?->info("Backoffice : {$admin->email} / ChangeMe123!");
        $this->command?->info("Enseignant admin : {$enseignant->tel} / ChangeMe123!");
        $this->command?->info("QR point de test : {$qrPoint->code}");
        $this->command?->info("Access point de test (bssid) : {$accessPoint->bssid}");
        $this->command?->info("Token device relais (esp32_borne/config.h RELAY_API_TOKEN) : {$relayToken}");
    }
}