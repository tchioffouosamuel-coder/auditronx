<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Journalise les créations, modifications, suppressions et restaurations des
 * modèles métier (§4.2).
 *
 * Branché dans `AppServiceProvider` sur la liste `MODELES_AUDITES`. Se place au
 * niveau du modèle et non du contrôleur : une suppression reste tracée quelle
 * que soit la route qui la déclenche (API, import, commande artisan).
 */
class AuditObserver
{
    public function created(Model $model): void
    {
        Audit::enregistrer(
            self::action($model, 'cree'),
            $model,
            self::valeurs($model->getAttributes()),
        );
    }

    public function updated(Model $model): void
    {
        $changements = self::diff($model);

        // `updated` se déclenche aussi sur une suppression logique : c'est
        // `deleted` qui porte l'information utile, on évite le doublon.
        if ($changements === [] || array_key_exists('deleted_at', $changements)) {
            return;
        }

        Audit::enregistrer(self::action($model, 'modifie'), $model, $changements);
    }

    public function deleted(Model $model): void
    {
        $logique = in_array(SoftDeletes::class, class_uses_recursive($model), true)
            && ! $model->isForceDeleting();

        Audit::enregistrer(
            self::action($model, $logique ? 'supprime' : 'supprime_definitivement'),
            $model,
            self::valeurs($model->getOriginal()),
            ['suppression_logique' => $logique],
        );
    }

    public function restored(Model $model): void
    {
        Audit::enregistrer(self::action($model, 'restaure'), $model);
    }

    /** `App\Models\EmploiDuTemps` + `supprime` → `emploi_du_temps.supprime`. */
    private static function action(Model $model, string $verbe): string
    {
        return Str::snake(class_basename($model)) . '.' . $verbe;
    }

    /** Valeurs modifiées, sous la forme {champ: {avant, apres}}. */
    private static function diff(Model $model): array
    {
        $changements = [];
        $avant = $model->getOriginal();

        foreach ($model->getChanges() as $champ => $apres) {
            if (in_array($champ, ['updated_at', 'created_at'], true)) {
                continue;
            }

            $paire = Audit::nettoyer([$champ => $apres]);
            $paireAvant = Audit::nettoyer([$champ => $avant[$champ] ?? null]);

            $changements[$champ] = [
                'avant' => $paireAvant[$champ],
                'apres' => $paire[$champ],
            ];
        }

        return $changements;
    }

    /** Instantané complet d'une ligne, pour une création ou une suppression. */
    private static function valeurs(array $attributs): array
    {
        unset($attributs['created_at'], $attributs['updated_at']);

        return Audit::nettoyer($attributs);
    }

    /** Les modèles dont les écritures sont journalisées. */
    public static function modelesSurveilles(): array
    {
        return [
            \App\Models\Enseignant::class,
            \App\Models\User::class,
            \App\Models\Accreditation::class,
            \App\Models\Classe::class,
            \App\Models\Discipline::class,
            \App\Models\EmploiDuTemps::class,
            \App\Models\Ferie::class,
            \App\Models\Signalement::class,
            \App\Models\Presence::class,
            \App\Models\CoursValidation::class,
            \App\Models\Device::class,
            \App\Models\AccessPoint::class,
            \App\Models\QrPoint::class,
            \App\Models\Firmware::class,
            \App\Models\Parametre::class,
            \App\Models\Programme::class,
            \App\Models\CahierTexteEntree::class,
        ];
    }

    /** Modèles explicitement exclus : volumineux, techniques ou déjà tracés ailleurs. */
    public static function modelesIgnores(): array
    {
        return [AuditLog::class, \App\Models\DeviceLog::class, \App\Models\Otp::class];
    }
}
