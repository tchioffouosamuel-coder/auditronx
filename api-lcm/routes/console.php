<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// §4.2 — détection quotidienne des absences répétées (Alertes).
Schedule::command('auditron:detect-absences')->dailyAt('18:00');
