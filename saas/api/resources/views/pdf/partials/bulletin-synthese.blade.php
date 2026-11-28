{{--
    Sections II et III du canevas officiel : synthèse des résultats, puis
    décision du conseil de classe. Les choix s'impriment en « [X] / [ ] »,
    comme sur le modèle : on voit aussi ce qui n'a pas été retenu.

    Le rang n'y figure pas : le modèle ne l'imprime pas. Il reste au PV.
--}}
@php($s = $page['synthese'])
@php($case = fn (bool $cochee) => $cochee ? '[X]' : '[ ]')

<h2>II. SYNTHÈSE DES RÉSULTATS</h2>
<div class="ligne">
    <span class="b">Moyenne de la classe :</span> {{ $s['moyenne_classe'] }} / 100 |
    <span class="b">Moyenne la plus forte :</span> {{ $s['moyenne_max'] }} / 100 |
    <span class="b">Moyenne la plus faible :</span> {{ $s['moyenne_min'] }} / 100 |
    <span class="b">Moyenne de l'élève :</span> {{ $s['moyenne'] }} / 100
</div>
<div class="ligne">
    <span class="b">Position par rapport au groupe :</span>
    @foreach ($s['positions'] as $valeur => $libelle)
        @if (! $loop->first) | @endif {{ $case($s['position'] === $valeur) }} {{ $libelle }}
    @endforeach
</div>
<table class="legende">
    <tr>
        <td class="b" style="width:11%">Légende APC :</td>
        <td>
            @foreach ($page['legende'] as $mention)
                <span style="margin-right:9px;white-space:nowrap">{{ $mention }}</span>
            @endforeach
        </td>
    </tr>
</table>

<h2>III. DÉCISION DU CONSEIL DE CLASSE</h2>
<div class="ligne">
    <span class="b">Validation des compétences professionnelles :</span>
    {{ $case($s['validation'] === \App\Services\BaremeApc::VALIDATION_OUI) }} OUI
    {{ $case($s['validation'] === \App\Services\BaremeApc::VALIDATION_NON_PARTIEL) }} NON / PARTIEL
</div>
<div class="ligne">
    <span class="b">DÉCISION GLOBALE :</span>
    @foreach ($s['decisions'] as $valeur => $libelle)
        {{ $case($s['decision'] === $valeur) }} <span class="rouge b">{{ $libelle }}</span>
    @endforeach
</div>
<div class="ligne"><span class="b">Observation :</span> {{ $s['observation'] }}</div>

<table class="signatures">
    <tr>
        <td style="width:25%">Le Parent</td>
        <td style="width:25%">Le Professeur principal</td>
        <td style="width:25%">Le Censeur</td>
        <td style="width:25%">
            Le Chef d'établissement
            @if ($page['valide_le'])
                <br><span style="font-size:7px">Conseil du {{ $page['valide_le'] }}</span>
            @endif
        </td>
    </tr>
</table>
