<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Discipline;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Presence;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Assiduité du mois consultée par l'enseignant depuis l'app mobile (§4.1). */
class MonAssiduiteTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsEnseignant(Enseignant $enseignant): void
    {
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);
    }

    /** Donne un cours à $enseignant le jour de la semaine de $date. */
    private function cours(Enseignant $enseignant, Carbon $date): void
    {
        EmploiDuTemps::create([
            'enseignant_id' => $enseignant->id,
            'classe_id' => Classe::factory()->create()->id,
            'discipline_id' => Discipline::factory()->create()->id,
            'jour' => $date->isoWeekday(),
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
        ]);
    }

    public function test_un_enseignant_sans_emploi_du_temps_le_sait(): void
    {
        $enseignant = Enseignant::factory()->create(['section' => 'Industrielle']);
        $this->actingAsEnseignant($enseignant);

        $this->getJson('/api/mon-assiduite')
            ->assertOk()
            ->assertJsonPath('taux_assiduite', 0)
            ->assertJsonPath('jours_attendus', 0)
            ->assertJsonPath('emploi_du_temps_charge', false);
    }

    public function test_le_taux_compte_les_jours_attendus_et_les_jours_pointes(): void
    {
        $enseignant = Enseignant::factory()->create(['section' => 'Industrielle']);

        // Un seul jour de la semaine est travaillé, donc 4 ou 5 occurrences
        // dans le mois : on compare au compte réel plutôt qu'à une constante.
        $jour = Carbon::now()->startOfMonth();
        $this->cours($enseignant, $jour);

        $attendus = collect();
        for ($d = $jour->copy(); $d->month === $jour->month; $d->addDay()) {
            if ($d->isoWeekday() === $jour->isoWeekday()) $attendus->push($d->copy());
        }

        Presence::create([
            'enseignant_id' => $enseignant->id,
            'date' => $attendus->first()->toDateString(),
            'heure_arrivee' => $attendus->first()->copy()->setTime(8, 0),
            'source' => 'app_mobile',
        ]);

        $this->actingAsEnseignant($enseignant);

        $reponse = $this->getJson('/api/mon-assiduite')->assertOk();

        $reponse->assertJsonPath('jours_attendus', $attendus->count());
        $reponse->assertJsonPath('jours_presents', 1);
        $reponse->assertJsonPath('emploi_du_temps_charge', true);
        $this->assertEqualsWithDelta(
            round(1 / $attendus->count() * 100, 1),
            $reponse->json('taux_assiduite'),
            0.1,
        );
    }

    public function test_un_administratif_est_attendu_sans_emploi_du_temps(): void
    {
        $agent = Enseignant::factory()->create(['section' => 'Administration']);
        $this->actingAsEnseignant($agent);

        $reponse = $this->getJson('/api/mon-assiduite')->assertOk();

        $this->assertTrue($reponse->json('horaire_fixe'));
        $this->assertGreaterThan(0, $reponse->json('jours_attendus'));
    }

    public function test_un_compte_backoffice_na_pas_acces_a_cette_route(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        $this->getJson('/api/mon-assiduite')->assertForbidden();
    }
}
