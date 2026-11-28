{{--
    Tableau du bulletin annuel, colonnes du canevas officiel : une note par
    trimestre, la note finale (moyenne des trimestres programmés), crédit,
    VH, mention, sanction, appréciation — plus la signature. Le tiret marque
    un trimestre où le module n'est pas programmé : hors du dénominateur,
    pas un zéro.
--}}
@php($n = count($page['trimestres']))
<table class="grille">
    <thead>
        <tr>
            <th style="width:{{ 42 - 6 * $n }}%" class="g">MODULES</th>
            @foreach ($page['trimestres'] as $t)
                <th style="width:6%">Note Trim {{ $t }}</th>
            @endforeach
            <th style="width:7%">NOTE Finale (/100)</th>
            <th style="width:6%">CRÉDIT</th>
            <th style="width:5%">VH</th>
            <th style="width:7%">MENTION</th>
            <th style="width:7%">SANCTION</th>
            <th style="width:20%">APPRÉCIATION</th>
            <th style="width:5%">SIGN.</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($page['blocs'] as $bloc)
            <tr class="bloc"><td colspan="{{ 8 + $n }}">{{ $bloc['libelle'] }}</td></tr>
            @forelse ($bloc['lignes'] as $l)
                <tr class="{{ $l['manquante'] ? 'manq' : '' }}">
                    <td><span class="b">{{ $l['code'] }}</span> · {{ $l['intitule'] }}</td>
                    @foreach ($page['trimestres'] as $t)
                        <td class="c">{{ $l['notes'][$t] }}</td>
                    @endforeach
                    <td class="c b">{{ $l['note_finale'] }}</td>
                    <td class="c">{{ $l['credit'] }}</td>
                    <td class="c">{{ $l['volume_horaire'] }}</td>
                    <td class="c">{{ $l['mention'] }}</td>
                    <td class="c {{ $l['non_acquise'] ? 'rouge b' : '' }}">{{ $l['sanction'] }}</td>
                    <td>{{ $l['appreciation'] }}</td>
                    <td class="c">{{ $l['signataire'] }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 8 + $n }}" class="i">Non couverts par le référentiel de formation {{ $page['classe']['specialite_code'] }} – sans objet</td></tr>
            @endforelse
        @endforeach
        <tr class="total">
            <td>Moyenne Générale des notes pondérées</td>
            @foreach ($page['trimestres'] as $t)
                <td></td>
            @endforeach
            <td class="c">{{ $page['pied']['moyenne'] }}</td>
            <td class="c">{{ $page['pied']['credits'] }}</td>
            <td class="c">{{ $page['pied']['volume_horaire'] }}</td>
            <td class="c">{{ $page['pied']['mention'] }}</td>
            <td class="c {{ $page['pied']['defavorable'] ? 'rouge' : '' }}">{{ $page['pied']['decision'] }}</td>
            <td colspan="2" class="normal">{{ $page['pied']['competences'] }}</td>
        </tr>
        <tr>
            <td class="b">Seuil de réussite</td>
            <td colspan="{{ 7 + $n }}">{{ $page['seuil'] }}</td>
        </tr>
    </tbody>
</table>
