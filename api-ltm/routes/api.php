<?php

use App\Http\Controllers\Api\AbsenceAlertController;
use App\Http\Controllers\Api\AccessPointController;
use App\Http\Controllers\Api\AccreditationController;
use App\Http\Controllers\Api\AssiduiteController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CahierTexteController;
use App\Http\Controllers\Api\ClasseController;
use App\Http\Controllers\Api\CoursEnseignantController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DeviceLogController;
use App\Http\Controllers\Api\DisciplineController;
use App\Http\Controllers\Api\EmploiDuTempsController;
use App\Http\Controllers\Api\EnseignantController;
use App\Http\Controllers\Api\FerieController;
use App\Http\Controllers\Api\FicheProgressionController;
use App\Http\Controllers\Api\FirmwareController;
use App\Http\Controllers\Api\MonAssiduiteController;
use App\Http\Controllers\Api\MyPresenceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\ParametreController;
use App\Http\Controllers\Api\PresenceValidationController;
use App\Http\Controllers\Api\ProgrammeController;
use App\Http\Controllers\Api\QrPointController;
use App\Http\Controllers\Api\RelayImportController;
use App\Http\Controllers\Api\RelaySyncController;
use App\Http\Controllers\Api\RetardsController;
use App\Http\Controllers\Api\SignalementController;
use App\Http\Controllers\Api\SpreadsheetController;
use App\Http\Controllers\Api\StatistiquesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authentification backoffice (session React)
Route::post('/login', [AuthController::class, 'login']);

// Identification (tel + mot de passe) et activation d'un device — §4.1 revu,
// accessibles sans authentification préalable.
Route::post('/devices/request-activation', [DeviceController::class, 'requestActivation']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/me/fcm-token', [AuthController::class, 'updateFcmToken']);
    Route::put('/me/password', [AuthController::class, 'updatePassword']);

    Route::post('/otp/generate', [OtpController::class, 'generate'])->middleware('backoffice');
    Route::post('/devices/{device}/revoke', [DeviceController::class, 'revoke']);
    Route::post('/devices/{device}/rotate-token', [DeviceController::class, 'rotateToken']);
    Route::post('/devices/provision-relay', [DeviceController::class, 'provisionRelay']);
    Route::post('/devices/fcm-token', [DeviceController::class, 'updateFcmToken']);

    Route::post('/attendance/scan', [AttendanceController::class, 'scan']);
    Route::post('/attendance/admin-proxy', [AttendanceController::class, 'adminProxy']);

    // Passerelle offline ESP1/ESP2 (§hardware) : lots de pointages relayés en différé.
    Route::post('/relay/sync', [RelaySyncController::class, 'sync']);
    // Moniteur série à distance de la borne (§hardware, diagnostic).
    Route::post('/relay/logs', [DeviceLogController::class, 'store']);

    // Historique personnel & notifications (§4.1 — app mobile)
    Route::get('/mes-presences', [MyPresenceController::class, 'index']);
    Route::get('/mon-assiduite', MonAssiduiteController::class);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

    Route::get('/cahier-texte/{enseignant}', [CahierTexteController::class, 'index']);
    Route::post('/cahier-texte', [CahierTexteController::class, 'store']);
    Route::get('/mes-cours-du-jour', [CoursEnseignantController::class, 'index']);
    Route::post('/mes-cours-du-jour/lecon-toggle', [CoursEnseignantController::class, 'toggleLecon']);

    Route::get('/fiche-progression', [FicheProgressionController::class, 'index']);
    Route::apiResource('programmes', ProgrammeController::class)
        ->except(['show']);

    // Gestion (§4.2 / §4.3) — équivalent JSON des routes web de gestion existantes
    Route::get('/personnel/{enseignant}/assiduite', [EnseignantController::class, 'assiduite']);
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
    Route::apiResource('accreditations', AccreditationController::class)
        ->middleware(['backoffice', 'accreditation-admin']);

    // Journal d'audit (§4.2) — lecture seule, réservée aux accréditations à
    // accès total : il sert justement à contrôler les autres rôles.
    Route::middleware(['backoffice', 'accreditation-admin'])->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/audit-logs/actions', [AuditLogController::class, 'actions']);
    });
    Route::post('/personnel/import', [EnseignantController::class, 'import']);

    // Import/export/modèle XLSX génériques (§4.2) pour les entités principales.
    // Préfixées `/spreadsheet/...` pour ne pas entrer en collision avec les
    // apiResource `/personnel/{enseignant}` etc. déclarées ci-dessus.
    Route::pattern('spreadsheetEntity', 'personnel|classes|disciplines|emplois|progressions');
    Route::get('/spreadsheet/personnel/export-pdf', [SpreadsheetController::class, 'exportPersonnelPdf'])->middleware('backoffice');
    Route::get('/spreadsheet/{spreadsheetEntity}/template', [SpreadsheetController::class, 'template']);
    Route::get('/spreadsheet/{spreadsheetEntity}/export', [SpreadsheetController::class, 'export']);
    Route::post('/spreadsheet/{spreadsheetEntity}/import', [SpreadsheetController::class, 'import']);

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
    Route::get('/assiduite/journal/pdf', [AssiduiteController::class, 'journalPdf']);
    Route::get('/assiduite/personnel-inactif', [AssiduiteController::class, 'personnelInactif']);
    Route::get('/assiduite/sans-presence', [AssiduiteController::class, 'sansPresence']);
    Route::get('/assiduite/sans-presence/pdf', [AssiduiteController::class, 'sansPresencePdf']);
    Route::get('/statistiques/export-zip', [StatistiquesController::class, 'exportZip']);

    // Configuration (§4.2) — horaire fixe du personnel administratif.
    Route::get('/parametres/horaires-administratifs', [ParametreController::class, 'horairesAdministratifs']);
    Route::put('/parametres/horaires-administratifs', [ParametreController::class, 'definirHorairesAdministratifs'])->middleware('backoffice');

    // Validation des présences (§4.2)
    Route::get('/presences/validation', [PresenceValidationController::class, 'index']);
    Route::post('/presences/validation/toggle', [PresenceValidationController::class, 'toggle']);

    // Alertes (§4.2)
    Route::get('/absences/alertes', [AbsenceAlertController::class, 'index']);

    // Administration des appareils (§4.2)
    Route::get('/devices', [DeviceController::class, 'index']);
    Route::get('/devices/{device}/logs', [DeviceLogController::class, 'index'])->middleware('backoffice');
    Route::delete('/devices/{device}/logs', [DeviceLogController::class, 'destroy'])->middleware('backoffice');
    // Import manuel du queue.jsonl d'une borne qui ne parvient pas à synchroniser.
    Route::post('/relay/import', [RelayImportController::class, 'import'])->middleware('backoffice');

    // Mises à jour OTA des bornes : gestion (backoffice) + manifest/téléchargement (borne).
    Route::middleware('backoffice')->group(function () {
        Route::get('/firmwares', [FirmwareController::class, 'index']);
        Route::post('/firmwares', [FirmwareController::class, 'store']);
        Route::post('/firmwares/{firmware}/activate', [FirmwareController::class, 'activate']);
        Route::post('/firmwares/{firmware}/deactivate', [FirmwareController::class, 'deactivate']);
        Route::delete('/firmwares/{firmware}', [FirmwareController::class, 'destroy']);
    });
    Route::get('/relay/firmware/manifest', [FirmwareController::class, 'manifest']);
    Route::get('/relay/firmware/{firmware}/download', [FirmwareController::class, 'download']);
    Route::apiResource('access-points', AccessPointController::class)
        ->parameters(['access-points' => 'accessPoint'])
        ->except(['show']);
    Route::apiResource('qr-points', QrPointController::class)
        ->parameters(['qr-points' => 'qrPoint'])
        ->except(['show']);
});