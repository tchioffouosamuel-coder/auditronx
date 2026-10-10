<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * §4.2 — détection quotidienne des absences répétées (Alertes).
 *
 * Passe par `tenants:run` : une seule entrée cron sur le serveur, rejouée dans
 * la base de chaque établissement actif. Sans ce relais, la commande
 * s'exécuterait sur la connexion centrale, où aucune table métier n'existe.
 *
 * L'heure reste 18:00 serveur : les fuseaux par établissement n'ont de sens
 * que pour l'affichage des pointages, et un décalage d'une heure sur une
 * détection d'absences quotidienne n'en change pas le résultat.
 */
Schedule::command('tenants:run', ['auditron:detect-absences'])->dailyAt('18:00');
