{{--
    Tableau du bulletin trimestriel, colonnes du canevas officiel : le code
    dans la case du module, les deux parts de la note, la note finale, le
    crédit, mention, sanction, appréciation — plus la colonne de signature de
    l'enseignant. Seuls les modules programmés au trimestre y figurent.
--}}
<table class="grille">
    <thead>
        <tr>
            <th style="width:33%" class="g">MODULES</th>
            <th style="width:8%">Moyenne UEA(/{{ $page['bareme_uea'] }})</th>
            <th style="width:9%">Moyenne de l'Épreuve Intégra (/{{ $page['bareme_integratrice'] }})</th>
            <th style="width:8%">NOTE FINALE (/100)</th>
            <th style="width:6%">CRÉDIT</th>
            <th style="width:7%">MENTION</th>
            <th style="width:8%">SANCTION</th>
            <th style="width:15%">APPRÉCIATION</th>
            <th style="width:6%">SIGN.</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($page['blocs'] as $bloc)
            <tr class="bloc"><td colspan="9">{{ $bloc['libelle'] }}</td></tr>
            @forelse ($bloc['lignes'] as $l)
                <tr class="{{ $l['manquante'] ? 'manq' : '' }}">
                    <td><span class="b">{{ $l['code'] }}</span> · {{ $l['intitule'] }}</td>
                    <td class="c">{{ $l['moyenne_uea'] }}</td>
                    <td class="c">{{ $l['note_integratrice'] }}</td>
                    <td class="c b">{{ $l['note_finale'] }}</td>
                    <td class="c">{{ $l['credit'] }}</td>
                    <td class="c">{{ $l['mention'] }}</td>
                    <td class="c {{ $l['non_acquise'] ? 'rouge b' : '' }}">{{ $l['sanction'] }}</td>
                    <td>{{ $l['appreciation'] }}</td>
                    <td class="c">{{ $l['signataire'] }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="i">Non couverts par le référentiel de formation {{ $page['classe']['specialite_code'] }} – sans objet</td></tr>
            @endforelse
        @endforeach
        <tr class="total">
            <td>Moyenne Générale des notes pondérées</td>
            <td></td>
            <td></td>
            <td class="c">{{ $page['pied']['moyenne'] }}</td>
            <td class="c">{{ $page['pied']['credits'] }}</td>
            <td class="c">{{ $page['pied']['mention'] }}</td>
            <td class="c {{ $page['pied']['defavorable'] ? 'rouge' : '' }}">{{ $page['pied']['decision'] }}</td>
            <td colspan="2" class="normal">{{ $page['pied']['competences'] }}</td>
        </tr>
        <tr>
            <td class="b">Seuil de réussite</td>
            <td colspan="8">{{ $page['seuil'] }}</td>
        </tr>
    </tbody>
</table>
