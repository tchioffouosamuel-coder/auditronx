{{--
    Tableau d'un bulletin trimestriel : 11 colonnes. Les deux parts de la
    note (UEA / épreuve intégratrice) y figurent, puisque c'est leur somme
    que le parent doit pouvoir refaire.
--}}
<table class="grille">
    <thead>
        <tr>
            <th style="width:6%">CODE</th>
            <th style="width:29%">MODULES</th>
            <th style="width:5%">CRÉD.</th>
            <th style="width:5%">VH</th>
            <th style="width:6%">UEA<br>/{{ $page['bareme_uea'] ?? 60 }}</th>
            <th style="width:6%">ÉPR. INT.<br>/{{ $page['bareme_integratrice'] ?? 40 }}</th>
            <th style="width:7%">NOTE<br>/100</th>
            <th style="width:6%">MENTION</th>
            <th style="width:6%">SANCT.</th>
            <th style="width:18%">APPRÉCIATION</th>
            <th style="width:6%">SIGN.</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($page['blocs'] as $bloc)
            <tr class="bloc"><td colspan="11">{{ $bloc['libelle'] }}</td></tr>
            @foreach ($bloc['lignes'] as $l)
                <tr class="{{ $l['manquante'] ? 'manq' : '' }}">
                    <td class="c">{{ $l['code'] }}</td>
                    <td>{{ $l['intitule'] }}</td>
                    <td class="c">{{ $l['credit'] }}</td>
                    <td class="c">{{ $l['volume_horaire'] }}</td>
                    <td class="c">{{ $l['moyenne_uea'] }}</td>
                    <td class="c">{{ $l['note_integratrice'] }}</td>
                    <td class="c b {{ $l['sous_seuil'] ? 'rouge' : '' }}">{{ $l['note_finale'] }}</td>
                    <td class="c">{{ $l['mention'] }}</td>
                    <td class="c">{{ $l['sanction'] }}</td>
                    <td>{{ $l['appreciation'] }}</td>
                    <td class="c">{{ $l['signataire'] }}</td>
                </tr>
            @endforeach
        @endforeach
        <tr class="total">
            <td colspan="2" class="r">TOTAUX / MOYENNE GÉNÉRALE</td>
            <td class="c">{{ $page['totaux']['credits'] }}</td>
            <td class="c">{{ $page['totaux']['volume_horaire'] }}</td>
            <td colspan="2"></td>
            <td class="c">{{ $page['totaux']['moyenne'] }}</td>
            <td class="c">{{ $page['totaux']['mention'] }}</td>
            <td colspan="3"></td>
        </tr>
    </tbody>
</table>
