<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Discipline;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CahierTexteTest extends TestCase
{
    use RefreshDatabase;

    private function creerCours(Enseignant $enseignant): EmploiDuTemps
    {
        return EmploiDuTemps::create([
            'enseignant_id' => $enseignant->id,
            'classe_id' => Classe::factory()->create()->id,
            'discipline_id' => Discipline::factory()->create()->id,
            'jour' => now()->isoWeekday(),
            'heure_debut' => '08:00',
            'heure_fin' => '09:00',
        ]);
    }

    public function test_un_enseignant_peut_saisir_une_entree_pour_son_propre_cours(): void
    {
        $enseignant = Enseignant::factory()->create();
        $cours = $this->creerCours($enseignant);
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $this->postJson('/api/cahier-texte', [
            'emploi_du_temps_id' => $cours->id,
            'date' => now()->toDateString(),
            'contenu' => 'Introduction au chapitre 1',
        ])->assertCreated()->assertJsonPath('enseignant_id', $enseignant->id);

        $this->assertDatabaseHas('cahier_texte_entrees', [
            'enseignant_id' => $enseignant->id,
            'emploi_du_temps_id' => $cours->id,
        ]);
    }

    public function test_un_enseignant_ne_peut_pas_saisir_pour_le_cours_dun_autre(): void
    {
        $autre = Enseignant::factory()->create();
        $cours = $this->creerCours($autre);

        $enseignant = Enseignant::factory()->create();
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $this->postJson('/api/cahier-texte', [
            'emploi_du_temps_id' => $cours->id,
            'date' => now()->toDateString(),
            'contenu' => 'Tentative usurpation',
        ])->assertNotFound();
    }

    public function test_ladmin_ne_peut_plus_saisir_dentree_lui_meme(): void
    {
        $enseignant = Enseignant::factory()->create();
        $cours = $this->creerCours($enseignant);

        $admin = User::factory()->create();
        $this->withToken($admin->createToken('backoffice')->plainTextToken);

        $this->postJson('/api/cahier-texte', [
            'emploi_du_temps_id' => $cours->id,
            'date' => now()->toDateString(),
            'contenu' => 'Saisie admin interdite',
        ])->assertForbidden();
    }

    public function test_ladmin_garde_un_acces_en_lecture_a_tous_les_enseignants(): void
    {
        $enseignant = Enseignant::factory()->create();
        $cours = $this->creerCours($enseignant);
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);
        $this->postJson('/api/cahier-texte', [
            'emploi_du_temps_id' => $cours->id,
            'date' => now()->toDateString(),
            'contenu' => 'Entrée existante',
        ])->assertCreated();

        $admin = User::factory()->create();
        $this->withToken($admin->createToken('backoffice')->plainTextToken);

        $this->getJson("/api/cahier-texte/{$enseignant->id}")->assertOk()->assertJsonFragment(['contenu' => 'Entrée existante']);
    }

    public function test_un_enseignant_ne_peut_pas_consulter_lhistorique_dun_autre(): void
    {
        $autre = Enseignant::factory()->create();
        $enseignant = Enseignant::factory()->create();
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $this->getJson("/api/cahier-texte/{$autre->id}")->assertForbidden();
    }
}
