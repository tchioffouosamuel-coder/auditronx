<?php

use App\Http\Controllers\Api\Central\AbonnementController;
use App\Http\Controllers\Api\Central\AnnuaireController;
use App\Http\Controllers\Api\Central\AuthController;
use App\Http\Controllers\Api\Central\CatalogueController;
use App\Http\Controllers\Api\Central\EtablissementController;
use App\Http\Controllers\Api\Central\FactureController;
use App\Http\Controllers\Api\Central\PlanController;
use App\Http\Controllers\Api\Central\TableauDeBordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes de la plateforme — préfixe /api/central
|--------------------------------------------------------------------------
|
| Ces routes ne passent PAS par le middleware `tenant` : elles travaillent sur
| la base centrale (annuaire des abonnés, abonnements, facturation) et sont
| réservées au personnel Auditron, à deux exceptions publiques près — le
| catalogue et l'annuaire, dont les clients ont besoin avant de savoir quel
| établissement ils interrogent.
|
| Aucune route ici ne lit de donnée métier d'un établissement, à l'exception
| des compteurs d'usage, qui ouvrent explicitement un contexte locataire.
|
*/

// Publiques : ce dont le portail et l'app ont besoin avant toute connexion.
Route::get('/catalogue', [CatalogueController::class, 'index']);
Route::post('/annuaire/resolve', [AnnuaireController::class, 'resolve']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'platform'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/tableau-de-bord', [TableauDeBordController::class, 'index']);

    // Lecture du parc ouverte à tout le personnel Auditron (support inclus).
    Route::get('/etablissements', [EtablissementController::class, 'index']);
    Route::get('/etablissements/{etablissement}', [EtablissementController::class, 'show']);

    Route::get('/plans', [PlanController::class, 'index']);
    Route::get('/abonnements', [AbonnementController::class, 'index']);
    Route::get('/factures', [FactureController::class, 'index']);

    // Tout ce qui crée, provisionne, facture ou détruit : super-admin seul.
    Route::middleware('platform:admin')->group(function () {
        Route::post('/etablissements', [EtablissementController::class, 'store']);
        Route::put('/etablissements/{etablissement}', [EtablissementController::class, 'update']);
        Route::post('/etablissements/{etablissement}/provision', [EtablissementController::class, 'provision']);
        Route::post('/etablissements/{etablissement}/statut', [EtablissementController::class, 'statut']);
        Route::post('/etablissements/{etablissement}/migrate', [EtablissementController::class, 'migre']);
        Route::delete('/etablissements/{etablissement}', [EtablissementController::class, 'destroy']);

        Route::post('/plans', [PlanController::class, 'store']);
        Route::put('/plans/{plan}', [PlanController::class, 'update']);
        Route::delete('/plans/{plan}', [PlanController::class, 'destroy']);

        Route::post('/abonnements', [AbonnementController::class, 'store']);
        Route::put('/abonnements/{abonnement}', [AbonnementController::class, 'update']);
        Route::post('/abonnements/{abonnement}/renouvelle', [AbonnementController::class, 'renouvelle']);

        Route::post('/factures', [FactureController::class, 'store']);
        Route::post('/factures/{facture}/payee', [FactureController::class, 'marquePayee']);
        Route::post('/factures/{facture}/annule', [FactureController::class, 'annule']);
    });
});
