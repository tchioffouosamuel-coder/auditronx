{{--
    Bas de bulletin : synthèse, position dans le groupe, validation des
    compétences, décision du conseil, observation, légende et signatures.

    Les choix sont imprimés en cases cochées plutôt qu'en texte seul : c'est
    la forme du modèle officiel, et elle montre aussi ce qui n'a pas été
    retenu.
--}}
@php($s = $page['synthese'])
@php($case = fn (bool $cochee) => $cochee ? '☒' : '☐')

<table style="margin-top:5px">
    <tr>
        <td class="cadre" style="width:34%">
            <div class="b" style="margin-bottom:2px">SYNTHÈSE</div>
            Moyenne générale : <span class="b">{{ $s['moyenne'] }} / 100</span><br>
            Mention : <span class="b">{{ $s['mention'] ?? '—' }}</span>
            @if ($s['appreciation']) ({{ $s['appreciation'] }}) @endif<br>
            Rang : <span class="b">{{ $s['rang'] }}</span> sur {{ $s['effectif_classe'] }}<br>
            Compétences non validées : {{ $s['competences_non_validees'] }}<br>
            Moyenne de la classe : {{ $s['moyenne_classe'] }}<br>
            Plus forte / plus faible : {{ $s['moyenne_max'] }} / {{ $s['moyenne_min'] }}
        </td>
        <td class="cadre" style="width:33%">
            <div class="b" style="margin-bottom:2px">POSITION PAR RAPPORT AU GROUPE</div>
            @foreach ($s['positions'] as $valeur => $libelle)
                <span class="case">{{ $case($s['position'] === $valeur) }}</span> {{ $libelle }}<br>
            @endforeach
            <div class="b" style="margin:3px 0 2px">VALIDATION DES COMPÉTENCES</div>
            <span class="case">{{ $case($s['validation'] === \App\Services\BaremeApc::VALIDATION_OUI) }}</span> OUI
            &nbsp;&nbsp;
            <span class="case">{{ $case($s['validation'] === \App\Services\BaremeApc::VALIDATION_NON_PARTIEL) }}</span> NON / PARTIEL
        </td>
        <td class="cadre" style="width:33%">
            <div class="b" style="margin-bottom:2px">DÉCISION DU CONSEIL DE CLASSE</div>
            @foreach ($s['decisions'] as $valeur => $libelle)
                <span class="case">{{ $case($s['decision'] === $valeur) }}</span> {{ $libelle }}<br>
            @endforeach
            <div class="b" style="margin:3px 0 1px">Observation :</div>
            {{ $s['observation'] }}
        </td>
    </tr>
</table>

<table class="legende" style="margin-top:3px">
    <tr>
        <td class="b">Légende :</td>
        @foreach ($page['legende'] as $m)
            <td>{{ $m['lettre'] }} {{ $m['borne'] }} : {{ $m['appreciation'] }}</td>
        @endforeach
        <td>S : compétence validée · E : non acquise</td>
    </tr>
</table>

<table class="signatures" style="margin-top:6px">
    <tr>
        <td style="width:25%">Le Parent</td>
        <td style="width:25%">Le Professeur principal</td>
        <td style="width:25%">Le Censeur</td>
        <td style="width:25%">
            Le Chef d'établissement
            @if ($page['valide_le'])
                <br><span style="font-size:6.6px">Conseil du {{ $page['valide_le'] }}</span>
            @endif
        </td>
    </tr>
</table>
