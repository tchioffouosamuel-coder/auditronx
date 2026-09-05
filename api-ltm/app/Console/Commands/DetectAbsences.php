<?php

namespace App\Console\Commands;

use App\Services\AbsenceDetectorService;
use Illuminate\Console\Command;

class DetectAbsences extends Command
{
    protected $signature = 'auditron:detect-absences {--date=}';

    protected $description = 'Détecte les absences répétées et journalise les alertes (§4.2).';

    public function handle(AbsenceDetectorService $service): int
    {
        $date = $this->option('date') ? now()->parse($this->option('date')) : now();

        $service->detecterPour($date);

        $this->info("Détection des absences effectuée pour le {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
