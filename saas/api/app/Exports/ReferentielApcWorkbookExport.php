<?php

namespace App\Exports;

use App\Models\Module;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Classeur du référentiel APC, dans la disposition du document diffusé par
 * l'organisation pédagogique : un onglet par spécialité et par niveau, les
 * modules rangés dans la colonne de leur bloc, le volume horaire en regard,
 * et une ligne « TOTAL <niveau> (h) » en bas.
 *
 * C'est délibérément la même mise en page que celle attendue à l'import : le
 * circuit modèle → remplissage → import boucle sans conversion intermédiaire,
 * et un classeur exporté puis réimporté rend exactement le même référentiel.
 * Un format tabulaire « une ligne = un module » aurait été plus simple à
 * produire, mais aurait obligé l'administration à ressaisir les classeurs
 * qu'elle reçoit.
 */
class ReferentielApcWorkbookExport implements Export, WithMultipleSheets
{
    /** En-têtes des colonnes, dans l'ordre des blocs du bulletin, puis le volume horaire. */
    public const ENTETES = [
        'MODULES DES COMPÉTENCES PARTICULIÈRES',
        'MODULES DES COMPÉTENCES GÉNÉRALES',
        'MODULES DES ENSEIGNEMENTS GÉNÉRAUX',
        'MODULES DES ENSEIGNEMENTS COMPLÉMENTAIRES',
        'VH',
    ];

    public const ENTETES_CONTROLE = [
        'Spécialité',
        'Total calculé (h)',
        "Total indiqué dans l'OP (h)",
        'Écart',
        'Contrôle',
    ];

    /** Onglet d'exemple quand aucun référentiel n'est encore saisi. */
    private const ONGLET_VIERGE = 'SPECIALITE';

    private const NIVEAU_VIERGE = 'Seconde';

    /**
     * @param  array<int, array{code: string, niveau: string, modules: array<int, array{bloc: string, code: ?string, intitule: string, volume_horaire: ?int}>}>  $groupes
     */
    public function __construct(private readonly array $groupes) {}

    public function sheets(): array
    {
        if ($this->groupes === []) {
            return [
                new ReferentielApcFeuilleExport(self::ONGLET_VIERGE, self::NIVEAU_VIERGE, []),
                new ReferentielApcControleExport([]),
            ];
        }

        $sheets = [];
        $titresUtilises = [];
        $controle = [];

        foreach ($this->groupes as $groupe) {
            $sheets[] = new ReferentielApcFeuilleExport(
                $this->titreUnique($groupe, $titresUtilises),
                $groupe['niveau'],
                $groupe['modules'],
            );

            $total = array_sum(array_map(
                fn (array $module) => (int) ($module['volume_horaire'] ?? 0),
                $groupe['modules'],
            ));

            // La colonne « total indiqué dans l'OP » est laissée vide : c'est
            // la valeur du document officiel, que l'application ne connaît
            // pas. Renseignée à la main, elle est recoupée au prochain import.
            $controle[] = [$groupe['code'], $total, null, null, null];
        }

        $sheets[] = new ReferentielApcControleExport($controle);

        return $sheets;
    }

    /**
     * Un établissement qui a saisi la Seconde et la Première d'une même
     * spécialité a deux onglets : le code seul ne les distinguerait pas, et
     * Excel refuse deux onglets homonymes.
     *
     * @param  array{code: string, niveau: string}  $groupe
     * @param  array<int, string>  $titresUtilises
     */
    private function titreUnique(array $groupe, array &$titresUtilises): string
    {
        $base = preg_replace('/[\\\\\/?*\[\]:]/', ' ', trim($groupe['code'])) ?: 'Spécialité';

        if (in_array(mb_strtolower($base), $titresUtilises, true)) {
            $base .= ' '.$groupe['niveau'];
        }

        $titre = mb_substr($base, 0, 31);
        $suffixe = 2;

        while (in_array(mb_strtolower($titre), $titresUtilises, true)) {
            $fin = ' ('.$suffixe++.')';
            $titre = mb_substr($base, 0, 31 - mb_strlen($fin)).$fin;
        }

        $titresUtilises[] = mb_strtolower($titre);

        return $titre;
    }
}

/**
 * Un onglet : les modules d'une spécialité pour un niveau, chacun sur sa
 * propre ligne, dans la colonne de son bloc.
 */
class ReferentielApcFeuilleExport implements Export, FromArray, WithHeadings, WithTitle
{
    /** Lignes vides laissées sous les en-têtes d'un onglet vierge, pour la saisie. */
    private const LIGNES_VIERGES = 20;

    /**
     * @param  array<int, array{bloc: string, code: ?string, intitule: string, volume_horaire: ?int}>  $modules
     */
    public function __construct(
        private readonly string $titre,
        private readonly string $niveau,
        private readonly array $modules,
    ) {}

    public function array(): array
    {
        $colonnes = array_flip(array_keys(Module::BLOCS));
        $lignes = [];

        foreach ($this->modules as $module) {
            $ligne = array_fill(0, count(Module::BLOCS) + 1, null);
            $colonne = $colonnes[$module['bloc']] ?? 0;

            // Le code est réécrit entre parenthèses à la fin de l'intitulé :
            // c'est là que le lecteur d'import va le chercher, et c'est la
            // forme du document d'origine.
            $ligne[$colonne] = $module['code'] === null || $module['code'] === ''
                ? $module['intitule']
                : "{$module['intitule']} ({$module['code']})";
            $ligne[count(Module::BLOCS)] = $module['volume_horaire'];

            $lignes[] = $ligne;
        }

        if ($lignes === []) {
            $lignes = array_fill(0, self::LIGNES_VIERGES, array_fill(0, count(Module::BLOCS) + 1, null));
        }

        $total = array_sum(array_map(
            fn (array $module) => (int) ($module['volume_horaire'] ?? 0),
            $this->modules,
        ));

        // La ligne TOTAL n'est pas décorative : c'est d'elle que l'import tire
        // le niveau du référentiel.
        $totalLigne = array_fill(0, count(Module::BLOCS) + 1, null);
        $totalLigne[0] = 'TOTAL '.mb_strtoupper($this->niveau, 'UTF-8').' (h)';
        $totalLigne[count(Module::BLOCS)] = $this->modules === [] ? null : $total;

        $lignes[] = $totalLigne;

        return $lignes;
    }

    public function headings(): array
    {
        return ReferentielApcWorkbookExport::ENTETES;
    }

    public function title(): string
    {
        return $this->titre;
    }
}

/** Onglet de recoupement des totaux d'heures. */
class ReferentielApcControleExport implements Export, FromArray, WithHeadings, WithTitle
{
    /** @param array<int, array<int, mixed>> $lignes */
    public function __construct(private readonly array $lignes) {}

    public function array(): array
    {
        return $this->lignes;
    }

    public function headings(): array
    {
        return ReferentielApcWorkbookExport::ENTETES_CONTROLE;
    }

    public function title(): string
    {
        return 'Contrôle';
    }
}
