<?php

use App\Http\Controllers\Api\AbsenceAlertController;
use App\Http\Controllers\Api\AccessPointController;
use App\Http\Controllers\Api\AccreditationController;
use App\Http\Controllers\Api\AssiduiteController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CahierTexteController;
use App\Http\Controllers\Api\ClasseController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceActivationRequestController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DisciplineController;
use App\Http\Controllers\Api\EmploiDuTempsController;
use App\Http\Controllers\Api\EnseignantController;
use App\Http\Controllers\Api\FerieController;
use App\Http\Controllers\Api\FicheProgressionController;
use App\Http\Controllers\Api\MyPresenceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\PresenceCorrecteurController;
use App\Http\Controllers\Api\PresenceValidationController;
use App\Http\Controllers\Api\ProgrammeController;
use App\Http\Controllers\Api\QrPointController;
use App\Http\Controllers\Api\RelaySyncController;
use App\Http\Controllers\Api\RetardsController;
use App\Http\Controllers\Api\SignalementController;
use App\Http\Controllers\Api\StatistiquesController;
use App\Http\Controllers\Api\VisageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authentification backoffice (session React)
Route::post('/login', [AuthController::class, 'login']);

// Identification (tel + mot de passe) et activation d'un device — §4.1 revu,
// accessibles sans authentification préalable.
Route::post('/devices/request-activation', [DeviceController::class, 'requestActivation']);
Route::post('/devices/activate', [DeviceController::class, 'activate']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/otp/generate', [OtpController::class, 'generate']);
    Route::post('/devices/{device}/revoke', [DeviceController::class, 'revoke']);
    Route::post('/devices/provision-kiosk', [DeviceController::class, 'provisionKiosk']);
    Route::post('/devices/provision-relay', [DeviceController::class, 'provisionRelay']);
    Route::post('/devices/fcm-token', [DeviceController::class, 'updateFcmToken']);

    Route::post('/attendance/scan', [AttendanceController::class, 'scan']);
    Route::post('/attendance/admin-proxy', [AttendanceController::class, 'adminProxy']);
    Route::post('/attendance/facial-scan', [AttendanceController::class, 'facialScan']);

    // Passerelle offline ESP1/ESP2 (§hardware) : lots de pointages relayés en différé.
    Route::post('/relay/sync', [RelaySyncController::class, 'sync']);

    // Historique personnel & notifications (§4.1 — app mobile)
    Route::get('/wifi-access-points', [AccessPointController::class, 'wifiCredentials']);
    Route::get('/mes-presences', [MyPresenceController::class, 'index']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

    Route::post('/visages/enroll', [VisageController::class, 'enroll']);
    Route::delete('/visages/{enseignant}', [VisageController::class, 'revoke']);

    Route::get('/cahier-texte/{enseignant}', [CahierTexteController::class, 'index']);
    Route::post('/cahier-texte', [CahierTexteController::class, 'store']);

    Route::get('/fiche-progression', [FicheProgressionController::class, 'index']);
    Route::apiResource('programmes', ProgrammeController::class)
        ->except(['show']);

    // Gestion (§4.2 / §4.3) — équivalent JSON des routes web de gestion existantes
    Route::apiResource('personnel', EnseignantController::class)
        ->parameters(['personnel' => 'enseignant']);
    Route::apiResource('classes', ClasseController::class)
        ->parameters(['classes' => 'classe']);
    Route::apiResource('disciplines', DisciplineController::class);
    Route::apiResource('emplois', EmploiDuTempsController::class)
        ->parameters(['emplois' => 'emploiDuTemp']);
    Route::post('/signalements/bulk', [SignalementController::class, 'storeBulk']);
    Route::apiResource('signalements', SignalementController::class);
    Route::apiResource('feries', FerieController::class)
        ->parameters(['feries' => 'ferie']);
    Route::apiResource('accreditations', AccreditationController::class);
    Route::post('/personnel/import', [EnseignantController::class, 'import']);

    // Tableau de bord (§4.2)
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Retards & bilans (§4.2)
    Route::get('/retards/parametres', [RetardsController::class, 'parametres']);
    Route::put('/retards/parametres', [RetardsController::class, 'definirParametres']);
    Route::get('/retards/bilan-cumule', [RetardsController::class, 'bilanCumule']);
    Route::get('/retards/bilan/{enseignant}', [RetardsController::class, 'bilanIndividuel']);
    Route::get('/retards', [RetardsController::class, 'index']);

    // Assiduité & rapports (§4.2)
    Route::get('/assiduite/stats', [AssiduiteController::class, 'stats']);
    Route::get('/assiduite/journal', [AssiduiteController::class, 'journal']);
    Route::get('/assiduite/personnel-inactif', [AssiduiteController::class, 'personnelInactif']);
    Route::get('/statistiques/export-zip', [StatistiquesController::class, 'exportZip']);

    // Correcteur de présences (§4.2)
    Route::get('/presences/anomalies', [PresenceCorrecteurController::class, 'anomalies']);
    Route::post('/presences/corriger', [PresenceCorrecteurController::class, 'corriger']);

    // Validation des présences (§4.2)
    Route::get('/presences/validation', [PresenceValidationController::class, 'index']);
    Route::post('/presences/validation/toggle', [PresenceValidationController::class, 'toggle']);

    // Alertes (§4.2)
    Route::get('/absences/alertes', [AbsenceAlertController::class, 'index']);

    // Administration des appareils (§4.2)
    Route::get('/devices', [DeviceController::class, 'index']);
    Route::get('/devices/activation-requests', [DeviceActivationRequestController::class, 'index']);
    Route::post('/devices/activation-requests/{activationRequest}/generate-otp', [DeviceActivationRequestController::class, 'generateOtp']);
    Route::apiResource('access-points', AccessPointController::class)
        ->parameters(['access-points' => 'accessPoint'])
        ->except(['show']);
    Route::apiResource('qr-points', QrPointController::class)
        ->parameters(['qr-points' => 'qrPoint'])
        ->except(['show']);
});
