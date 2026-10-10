<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes web
|--------------------------------------------------------------------------
|
| Il n'y a pas d'interface web ici : cette application est une API, servie au
| portail React, à l'application mobile et aux bornes. La racine ne rend donc
| pas de vue Blade — elle répond en JSON, comme tout le reste.
|
| Ce n'est pas qu'une question de cohérence : rendre une vue oblige à disposer
| d'un dossier de vues compilées accessible en écriture, et une installation
| toute fraîche se traduisait par une 500 « Please provide a valid cache
| path » sur la page d'accueil, avant même d'avoir pu tester quoi que ce soit.
|
*/

Route::get('/', fn () => response()->json([
    'service' => 'Auditron X',
    'statut' => 'en ligne',
    // Rappel utile au premier contact : sans cet en-tête, les routes métier
    // répondent 400.
    'documentation' => 'Les routes /api/* exigent l’en-tête X-Tenant.',
]));
