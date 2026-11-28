<?php

namespace Tests\Feature;

use App\Models\Enseignant;
use App\Models\Parametre;
use App\Models\User;
use App\Services\BaremeApc;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Barème des bulletins de compétences (modèle APC).
 *
 * Le cœur de ce test n'est pas le service : ce sont les **quatre bulletins de
 * la formation des censeurs industriels**, reproduits ici chiffre par chiffre
 * — deux trimestriels et deux annuels, une élève admise et un élève en échec.
 * Tant qu'ils ressortent à l'identique, le calcul est juste ; le jour où une
 * règle change, c'est ici qu'on verra ce qu'elle déplace.
 *
 * Les valeurs attendues sont celles imprimées sur les bulletins de référence,
 * pas des valeurs recalculées pour l'occasion.
 */
class BaremeApcTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Le barème est mémorisé par base de données, et la mémoire est
        // statique : elle survivrait d'un test à l'autre dans le même
        // processus, et un test qui déplace le seuil fausserait les suivants.
        BaremeApc::oublier();
    }

    protected function tearDown(): void
    {
        BaremeApc::oublier();
        parent::tearDown();
    }

    private function actingAsBackoffice(): User
    {
        $user = User::factory()->create();
        $this->withToken($user->createToken('backoffice')->plainTextToken);

        return $user;
    }

    /* ------------------------------------------------------------------ */
    /* Cas 1 — bulletin du 3e trimestre d'une élève admise                 */
    /* ------------------------------------------------------------------ */

    /**
     * NGONO Aline Carine, 1re année TPV, trimestre 3 : moyenne 84,4 / 100,
     * 60 crédits, toutes compétences validées, succès.
     *
     * @return array<int, array{code: string, uea: int, integratrice: int, note: float, credit: int, mention: ?string, sanction: string}>
     */
    private function casTrimestreAdmise(): array
    {
        return [
            ['code' => 'C10', 'uea' => 52, 'integratrice' => 35, 'note' => 87.0, 'credit' => 10, 'mention' => 'B', 'sanction' => 'S'],
            ['code' => 'C11', 'uea' => 50, 'integratrice' => 34, 'note' => 84.0, 'credit' => 10, 'mention' => 'C', 'sanction' => 'S'],
            ['code' => 'C12', 'uea' => 54, 'integratrice' => 36, 'note' => 90.0, 'credit' => 10, 'mention' => 'A', 'sanction' => 'S'],
            ['code' => 'C3', 'uea' => 51, 'integratrice' => 33, 'note' => 84.0, 'credit' => 9, 'mention' => 'C', 'sanction' => 'S'],
            ['code' => 'C4', 'uea' => 49, 'integratrice' => 33, 'note' => 82.0, 'credit' => 10, 'mention' => 'C', 'sanction' => 'S'],
            ['code' => 'C5', 'uea' => 47, 'integratrice' => 31, 'note' => 78.0, 'credit' => 9, 'mention' => 'D', 'sanction' => 'S'],
            ['code' => 'C7', 'uea' => 53, 'integratrice' => 35, 'note' => 88.0, 'credit' => 2, 'mention' => 'B', 'sanction' => 'S'],
        ];
    }

    public function test_bulletin_trimestriel_dune_eleve_admise(): void
    {
        $lignes = [];

        foreach ($this->casTrimestreAdmise() as $module) {
            $note = BaremeApc::noteFinaleTrimestre($module['uea'], $module['integratrice']);

            $this->assertSame($module['note'], $note, "note finale du module {$module['code']}");
            $this->assertSame($module['mention'], BaremeApc::mention($note), "mention du module {$module['code']}");
            $this->assertSame($module['sanction'], BaremeApc::sanction($note), "sanction du module {$module['code']}");

            $lignes[] = ['note' => $note, 'credit' => $module['credit']];
        }

        $synthese = BaremeApc::synthese($lignes);

        $this->assertSame(84.4, $synthese['moyenne_generale']);
        $this->assertSame(60, $synthese['total_credits']);
        $this->assertSame(0, $synthese['competences_non_validees']);
        $this->assertSame('C', $synthese['mention']);
        $this->assertSame(BaremeApc::VALIDATION_OUI, $synthese['validation_competences']);
        $this->assertSame(
            BaremeApc::DECISION_SUCCES,
            BaremeApc::decisionTrimestre($synthese['moyenne_generale'], $synthese['competences_non_validees']),
        );
    }

    public function test_les_appreciations_suivent_la_mention(): void
    {
        $this->assertSame('Excellent', BaremeApc::appreciation(90));
        $this->assertSame('Très bien', BaremeApc::appreciation(87));
        $this->assertSame('Bien', BaremeApc::appreciation(84));
        $this->assertSame('Assez bien', BaremeApc::appreciation(78));
        $this->assertSame('Passable', BaremeApc::appreciation(71));
        $this->assertSame(BaremeApc::APPRECIATION_NON_ACQUISE, BaremeApc::appreciation(69));
        $this->assertNull(BaremeApc::appreciation(null));
    }

    /* ------------------------------------------------------------------ */
    /* Cas 2 — bulletin annuel d'une élève admise                          */
    /* ------------------------------------------------------------------ */

    /**
     * Même élève, bulletin annuel : 84,1 / 100, 97 crédits, 1410 h.
     *
     * C13 (stage en entreprise) n'est programmé aucun trimestre. Son crédit
     * est imprimé entre parenthèses et il ne compte ni dans la moyenne, ni
     * dans les 97 crédits, ni dans les 1410 heures — c'est le piège de ce cas.
     *
     * @return array<int, array{code: string, trimestres: array<int, ?int>, note: ?float, credit: int, vh: int}>
     */
    private function casAnnuelAdmise(): array
    {
        return [
            ['code' => 'C8', 'trimestres' => [85, 88, null], 'note' => 86.5, 'credit' => 10, 'vh' => 135],
            ['code' => 'C9', 'trimestres' => [80, 83, null], 'note' => 81.5, 'credit' => 9, 'vh' => 135],
            ['code' => 'C10', 'trimestres' => [84, 86, 87], 'note' => 85.7, 'credit' => 10, 'vh' => 150],
            ['code' => 'C11', 'trimestres' => [null, 81, 84], 'note' => 82.5, 'credit' => 10, 'vh' => 135],
            ['code' => 'C12', 'trimestres' => [null, 87, 90], 'note' => 88.5, 'credit' => 10, 'vh' => 135],
            ['code' => 'C13', 'trimestres' => [null, null, null], 'note' => null, 'credit' => 20, 'vh' => 300],
            ['code' => 'C1', 'trimestres' => [92, null, null], 'note' => 92.0, 'credit' => 2, 'vh' => 30],
            ['code' => 'C2', 'trimestres' => [88, 90, null], 'note' => 89.0, 'credit' => 9, 'vh' => 120],
            ['code' => 'C3', 'trimestres' => [null, 82, 84], 'note' => 83.0, 'credit' => 9, 'vh' => 135],
            ['code' => 'C4', 'trimestres' => [79, 81, 82], 'note' => 80.7, 'credit' => 10, 'vh' => 150],
            ['code' => 'C5', 'trimestres' => [76, 77, 78], 'note' => 77.0, 'credit' => 9, 'vh' => 150],
            ['code' => 'C6', 'trimestres' => [83, 85, null], 'note' => 84.0, 'credit' => 7, 'vh' => 105],
            ['code' => 'C7', 'trimestres' => [null, null, 88], 'note' => 88.0, 'credit' => 2, 'vh' => 30],
        ];
    }

    public function test_bulletin_annuel_dune_eleve_admise(): void
    {
        $lignes = [];

        foreach ($this->casAnnuelAdmise() as $module) {
            $note = BaremeApc::noteFinaleAnnuelle($module['trimestres']);

            $this->assertSame($module['note'], $note, "note annuelle du module {$module['code']}");

            $lignes[] = ['note' => $note, 'credit' => $module['credit'], 'volume_horaire' => $module['vh']];
        }

        $synthese = BaremeApc::synthese($lignes);

        $this->assertSame(84.1, $synthese['moyenne_generale']);
        $this->assertSame(97, $synthese['total_credits'], 'le stage non programmé ne compte pas');
        $this->assertSame(1410, $synthese['total_volume_horaire'], 'ses 300 h non plus');
        $this->assertSame(12, $synthese['modules_notes']);
        $this->assertSame(0, $synthese['competences_non_validees']);
        $this->assertSame('C', $synthese['mention']);
        $this->assertSame(
            BaremeApc::DECISION_ADMIS,
            BaremeApc::decisionAnnuelle($synthese['moyenne_generale'], $synthese['competences_non_validees']),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Cas 3 — bulletin du 3e trimestre d'un élève en échec                */
    /* ------------------------------------------------------------------ */

    /**
     * TCHAMBA Kevin Arnaud, trimestre 3 : 67,2 / 100, 4 compétences non
     * validées, échec. Les modules sous le seuil n'ont pas de mention — un
     * tiret — mais bien une sanction « E ».
     *
     * @return array<int, array{code: string, uea: int, integratrice: int, note: float, credit: int, mention: ?string, sanction: string}>
     */
    private function casTrimestreEchec(): array
    {
        return [
            ['code' => 'C10', 'uea' => 40, 'integratrice' => 24, 'note' => 64.0, 'credit' => 10, 'mention' => null, 'sanction' => 'E'],
            ['code' => 'C11', 'uea' => 38, 'integratrice' => 22, 'note' => 60.0, 'credit' => 10, 'mention' => null, 'sanction' => 'E'],
            ['code' => 'C12', 'uea' => 43, 'integratrice' => 28, 'note' => 71.0, 'credit' => 10, 'mention' => 'E', 'sanction' => 'S'],
            ['code' => 'C3', 'uea' => 42, 'integratrice' => 27, 'note' => 69.0, 'credit' => 9, 'mention' => null, 'sanction' => 'E'],
            ['code' => 'C4', 'uea' => 41, 'integratrice' => 25, 'note' => 66.0, 'credit' => 10, 'mention' => null, 'sanction' => 'E'],
            ['code' => 'C5', 'uea' => 44, 'integratrice' => 28, 'note' => 72.0, 'credit' => 9, 'mention' => 'E', 'sanction' => 'S'],
            ['code' => 'C7', 'uea' => 46, 'integratrice' => 29, 'note' => 75.0, 'credit' => 2, 'mention' => 'D', 'sanction' => 'S'],
        ];
    }

    public function test_bulletin_trimestriel_dun_eleve_en_echec(): void
    {
        $lignes = [];

        foreach ($this->casTrimestreEchec() as $module) {
            $note = BaremeApc::noteFinaleTrimestre($module['uea'], $module['integratrice']);

            $this->assertSame($module['note'], $note, "note finale du module {$module['code']}");
            $this->assertSame($module['mention'], BaremeApc::mention($note), "mention du module {$module['code']}");
            $this->assertSame($module['sanction'], BaremeApc::sanction($note), "sanction du module {$module['code']}");

            $lignes[] = ['note' => $note, 'credit' => $module['credit']];
        }

        $synthese = BaremeApc::synthese($lignes);

        $this->assertSame(67.2, $synthese['moyenne_generale']);
        $this->assertSame(60, $synthese['total_credits']);
        $this->assertSame(4, $synthese['competences_non_validees']);
        $this->assertNull($synthese['mention'], 'sous le seuil, le bulletin imprime un tiret');
        $this->assertSame(BaremeApc::VALIDATION_NON_PARTIEL, $synthese['validation_competences']);
        $this->assertSame(
            BaremeApc::DECISION_ECHEC,
            BaremeApc::decisionTrimestre($synthese['moyenne_generale'], $synthese['competences_non_validees']),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Cas 4 — bulletin annuel d'un élève en échec                         */
    /* ------------------------------------------------------------------ */

    /**
     * Même élève, bulletin annuel : 66,9 / 100, 7 compétences non validées,
     * redoublement — avec les mêmes 97 crédits et 1410 h que l'élève admise,
     * puisque la maquette de la classe est la même.
     *
     * @return array<int, array{code: string, trimestres: array<int, ?int>, note: ?float, credit: int, vh: int}>
     */
    private function casAnnuelEchec(): array
    {
        return [
            ['code' => 'C8', 'trimestres' => [62, 65, null], 'note' => 63.5, 'credit' => 10, 'vh' => 135],
            ['code' => 'C9', 'trimestres' => [70, 72, null], 'note' => 71.0, 'credit' => 9, 'vh' => 135],
            ['code' => 'C10', 'trimestres' => [63, 66, 64], 'note' => 64.3, 'credit' => 10, 'vh' => 150],
            ['code' => 'C11', 'trimestres' => [null, 61, 60], 'note' => 60.5, 'credit' => 10, 'vh' => 135],
            ['code' => 'C12', 'trimestres' => [null, 70, 71], 'note' => 70.5, 'credit' => 10, 'vh' => 135],
            ['code' => 'C13', 'trimestres' => [null, null, null], 'note' => null, 'credit' => 20, 'vh' => 300],
            ['code' => 'C1', 'trimestres' => [78, null, null], 'note' => 78.0, 'credit' => 2, 'vh' => 30],
            ['code' => 'C2', 'trimestres' => [68, 71, null], 'note' => 69.5, 'credit' => 9, 'vh' => 120],
            ['code' => 'C3', 'trimestres' => [null, 66, 69], 'note' => 67.5, 'credit' => 9, 'vh' => 135],
            ['code' => 'C4', 'trimestres' => [64, 67, 66], 'note' => 65.7, 'credit' => 10, 'vh' => 150],
            ['code' => 'C5', 'trimestres' => [70, 71, 72], 'note' => 71.0, 'credit' => 9, 'vh' => 150],
            ['code' => 'C6', 'trimestres' => [60, 63, null], 'note' => 61.5, 'credit' => 7, 'vh' => 105],
            ['code' => 'C7', 'trimestres' => [null, null, 75], 'note' => 75.0, 'credit' => 2, 'vh' => 30],
        ];
    }

    public function test_bulletin_annuel_dun_eleve_en_echec(): void
    {
        $lignes = [];

        foreach ($this->casAnnuelEchec() as $module) {
            $note = BaremeApc::noteFinaleAnnuelle($module['trimestres']);

            $this->assertSame($module['note'], $note, "note annuelle du module {$module['code']}");

            $lignes[] = ['note' => $note, 'credit' => $module['credit'], 'volume_horaire' => $module['vh']];
        }

        $synthese = BaremeApc::synthese($lignes);

        $this->assertSame(66.9, $synthese['moyenne_generale']);
        $this->assertSame(97, $synthese['total_credits']);
        $this->assertSame(1410, $synthese['total_volume_horaire']);
        $this->assertSame(7, $synthese['competences_non_validees']);
        $this->assertSame(
            BaremeApc::DECISION_REDOUBLEMENT,
            BaremeApc::decisionAnnuelle($synthese['moyenne_generale'], $synthese['competences_non_validees']),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Règles isolées                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Une moyenne d'UEA sans épreuve intégratrice est une saisie en cours, pas
     * une note amputée de quarante points : elle ne produit aucune note.
     */
    public function test_une_saisie_incomplete_ne_produit_pas_de_note(): void
    {
        $this->assertNull(BaremeApc::noteFinaleTrimestre(52, null));
        $this->assertNull(BaremeApc::noteFinaleTrimestre(null, 35));
        $this->assertNull(BaremeApc::noteFinaleTrimestre(null, null));
        $this->assertSame(0.0, BaremeApc::noteFinaleTrimestre(0, 0), 'un zéro reste un zéro');
    }

    /** Un trimestre sans note sort du dénominateur de la moyenne annuelle. */
    public function test_la_moyenne_annuelle_ignore_les_trimestres_non_programmes(): void
    {
        $this->assertSame(86.5, BaremeApc::noteFinaleAnnuelle([85, 88, null]));
        $this->assertSame(86.5, BaremeApc::noteFinaleAnnuelle([85, 88]));
        $this->assertNull(BaremeApc::noteFinaleAnnuelle([null, null, null]));
        $this->assertNull(BaremeApc::noteFinaleAnnuelle([]));
    }

    /** Un module sans crédit ne pèse pas sur la moyenne générale. */
    public function test_la_moyenne_generale_ignore_les_modules_sans_credit_ni_note(): void
    {
        $this->assertSame(80.0, BaremeApc::moyenneGenerale([
            ['note' => 90.0, 'credit' => 10],
            ['note' => 70.0, 'credit' => 10],
            ['note' => 20.0, 'credit' => 0],
            ['note' => null, 'credit' => 10],
        ]));

        $this->assertNull(BaremeApc::moyenneGenerale([]));
        $this->assertNull(BaremeApc::moyenneGenerale([['note' => 90.0, 'credit' => 0]]));
    }

    /**
     * Les bornes de mention sont des bornes inférieures : 84,5 et 89,2, que le
     * tableau en intervalles fermés laissait sans mention, en ont une.
     */
    public function test_les_bornes_de_mention_ne_laissent_pas_de_trou(): void
    {
        $this->assertSame('C', BaremeApc::mention(84.5));
        $this->assertSame('B', BaremeApc::mention(89.2));
        $this->assertSame('A', BaremeApc::mention(100));
        $this->assertSame('E', BaremeApc::mention(70), 'le seuil de réussite est atteint, pas franchi');
        $this->assertNull(BaremeApc::mention(69.9));
    }

    public function test_la_position_dans_le_groupe_se_lit_par_tiercile(): void
    {
        $this->assertSame(BaremeApc::POSITION_TETE, BaremeApc::positionGroupe(1, 28));
        $this->assertSame(BaremeApc::POSITION_TETE, BaremeApc::positionGroupe(10, 28));
        $this->assertSame(BaremeApc::POSITION_MILIEU, BaremeApc::positionGroupe(11, 28));
        $this->assertSame(BaremeApc::POSITION_MILIEU, BaremeApc::positionGroupe(18, 28));
        $this->assertSame(BaremeApc::POSITION_DIFFICULTE, BaremeApc::positionGroupe(19, 28));
        $this->assertSame(BaremeApc::POSITION_DIFFICULTE, BaremeApc::positionGroupe(28, 28));

        // Rang hors effectif, effectif nul : rien à conclure plutôt qu'une
        // position inventée.
        $this->assertNull(BaremeApc::positionGroupe(0, 28));
        $this->assertNull(BaremeApc::positionGroupe(29, 28));
        $this->assertNull(BaremeApc::positionGroupe(null, 28));
        $this->assertNull(BaremeApc::positionGroupe(1, 0));
    }

    /**
     * Compétences manquantes mais moyenne au-dessus du seuil : le conseil se
     * voit proposer une remédiation, pas un échec.
     */
    public function test_une_moyenne_au_dessus_du_seuil_propose_la_remediation(): void
    {
        $this->assertSame(BaremeApc::DECISION_REMEDIATION, BaremeApc::decisionTrimestre(78.0, 2));
        $this->assertSame(BaremeApc::DECISION_SOUS_RESERVE, BaremeApc::decisionAnnuelle(78.0, 2));
    }

    /* ------------------------------------------------------------------ */
    /* Paramétrage par établissement                                       */
    /* ------------------------------------------------------------------ */

    public function test_le_bareme_par_defaut_est_celui_du_modele_national(): void
    {
        $this->actingAsBackoffice();

        $this->getJson('/api/parametres/bareme-apc')
            ->assertOk()
            ->assertJson([
                'seuil_reussite' => 70,
                'bareme_uea' => 60,
                'bareme_integratrice' => 40,
                'nombre_trimestres' => 3,
                'note_maximale' => 100,
                'mentions' => ['A' => 90, 'B' => 85, 'C' => 80, 'D' => 75, 'E' => 70],
            ]);
    }

    public function test_un_etablissement_peut_deplacer_son_seuil_et_ses_mentions(): void
    {
        $this->actingAsBackoffice();

        $this->putJson('/api/parametres/bareme-apc', [
            'seuil_reussite' => 75,
            'bareme_uea' => 50,
            'mentions' => ['A' => 92, 'B' => 88, 'C' => 84, 'D' => 80, 'E' => 75],
        ])->assertOk()->assertJson([
            'seuil_reussite' => 75,
            'bareme_uea' => 50,
            'bareme_integratrice' => 50,
        ]);

        $this->assertSame(75.0, BaremeApc::seuilReussite());
        $this->assertSame(['uea' => 50, 'integratrice' => 50], BaremeApc::repartition());

        // 72 validait la compétence avec le barème national ; plus ici.
        $this->assertFalse(BaremeApc::estValidee(72));
        $this->assertNull(BaremeApc::mention(72));
        $this->assertSame('E', BaremeApc::mention(76));
        $this->assertSame(BaremeApc::SANCTION_NON_ACQUISE, BaremeApc::sanction(72));
    }

    /**
     * Des bornes qui ne décroissent pas classeraient toutes les notes dans la
     * dernière lettre : elles sont refusées à la saisie.
     */
    public function test_des_bornes_de_mention_incoherentes_sont_refusees(): void
    {
        $this->actingAsBackoffice();

        $this->putJson('/api/parametres/bareme-apc', [
            'mentions' => ['A' => 80, 'B' => 85, 'C' => 80, 'D' => 75, 'E' => 70],
        ])->assertStatus(422);

        $this->assertSame(BaremeApc::MENTIONS, BaremeApc::mentions());
    }

    /**
     * Une valeur abîmée écrite directement en base ne doit pas produire des
     * bulletins silencieusement faux : un seuil à zéro validerait tout.
     */
    public function test_un_parametre_hors_bornes_revient_au_defaut(): void
    {
        Parametre::set(BaremeApc::CLE_SEUIL_REUSSITE, '-5');
        Parametre::set(BaremeApc::CLE_BAREME_UEA, '0');
        BaremeApc::oublier();

        $this->assertSame(BaremeApc::SEUIL_REUSSITE, BaremeApc::seuilReussite());
        $this->assertSame(60, BaremeApc::repartition()['uea']);
    }

    /** Le barème se lit sans être administrateur ; il ne se modifie pas. */
    public function test_seul_le_backoffice_modifie_le_bareme(): void
    {
        $enseignant = Enseignant::factory()->create();
        $this->withToken($enseignant->createToken('mobile')->plainTextToken);

        $this->getJson('/api/parametres/bareme-apc')->assertOk();
        $this->putJson('/api/parametres/bareme-apc', ['seuil_reussite' => 50])->assertForbidden();

        $this->assertSame(BaremeApc::SEUIL_REUSSITE, BaremeApc::seuilReussite());
    }
}
