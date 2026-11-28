{{--
    Tableau du bulletin annuel : une colonne par trimestre, puis la note
    finale (moyenne des trimestres programmés). Le tiret marque un trimestre
    où le module n'est pas programmé — hors du dénominateur, pas un zéro.
--}}
@php($n = count($page['trimestres']))
<table class="grille">
    <thead>
        <tr>
            <th style="width:6%">CODE</th>
            <th style="width:{{ 31 - 2 * $n }}%">MODULES</th>
            <th style="width:5%">CRÉD.</th>
            <th style="width:5%">VH</th>
            @foreach ($page['trimestres'] as $t)
                <th style="width:6%">T{{ $t }}<br>/100</th>
            @endforeach
            <th style="width:7%">NOTE<br>FINALE</th>
            <th style="width:6%">MENTION</th>
            <th style="width:6%">SANCT.</th>
            <th style="width:{{ 34 - 4 * $n }}%">APPRÉCIATION</th>
            <th style="width:6%">SIGN.</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($page['blocs'] as $bloc)
            <tr class="bloc"><td colspan="{{ 9 + $n }}">{{ $bloc['libelle'] }}</td></tr>
            @foreach ($bloc['lignes'] as $l)
                <tr class="{{ $l['manquante'] ? 'manq' : '' }}">
                    <td class="c">{{ $l['code'] }}</td>
                    <td>{{ $l['intitule'] }}</td>
                    <td class="c">{{ $l['credit'] }}</td>
                    <td class="c">{{ $l['volume_horaire'] }}</td>
                    @foreach ($page['trimestres'] as $t)
                        <td class="c">{{ $l['notes'][$t] }}</td>
                    @endforeach
                    <td class="c b {{ $l['sous_seuil'] ? 'rouge' : '' }}">{{ $l['note_finale'] }}</td>
                    <td class="c">{{ $l['mention'] }}</td>
                    <td class="c">{{ $l['sanction'] }}</td>
                    <td>{{ $l['appreciation'] }}</td>
                    <td class="c">{{ $l['signataire'] }}</td>
                </tr>
            @endforeach
        @endforeach
        <tr class="total">
            <td colspan="2" class="r">TOTAUX / MOYENNE GÉNÉRALE ANNUELLE</td>
            <td class="c">{{ $page['totaux']['credits'] }}</td>
            <td class="c">{{ $page['totaux']['volume_horaire'] }}</td>
            <td colspan="{{ $n }}"></td>
            <td class="c">{{ $page['totaux']['moyenne'] }}</td>
            <td class="c">{{ $page['totaux']['mention'] }}</td>
            <td colspan="3"></td>
        </tr>
    </tbody>
</table>
