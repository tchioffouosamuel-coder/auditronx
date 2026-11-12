{{--
    Bilan mensuel, en trois volets : le bilan global (assiduité + ponctualité),
    l'assiduité seule, la ponctualité seule. Un seul gabarit pour les trois :
    ce sont les mêmes tableaux, c'est le document qui en retient un ou les deux
    — deux gabarits finiraient par diverger sur la mise en page d'un même
    tableau.
--}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>{{ $titre }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 10px
        }

        h1 {
            text-align: center;
            font-size: 17px;
            margin: 5px 0;
            text-transform: uppercase
        }

        h2 {
            font-size: 12px;
            margin: 12px 0 4px
        }

        .sous-titre {
            text-align: center;
            font-size: 12px;
            margin-bottom: 8px
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0
        }

        th,
        td {
            border: 1px solid #000;
            padding: 3px 4px;
            text-align: center;
            font-size: 10px
        }

        th {
            background: #e0e0e0
        }

        .text-left {
            text-align: left
        }

        .total {
            background: #d4edda;
            font-weight: bold
        }

        .note {
            margin-top: 8px;
            font-size: 9px;
            color: #555
        }

        .vide {
            text-align: center;
            font-size: 10px;
            color: #555;
            padding: 8px
        }
    </style>
</head>

<body>
    @include('pdf.partials.entete')

    <h1>{{ $titre }}</h1>
    <div class="sous-titre">{{ $categorie }} — {{ $bilan['libelle'] }}</div>

    @if ($volet !== 'ponctualite')
        @forelse ($bilan['sections'] as $section)
            <h2>
                {{ $section['section'] }} — {{ $section['effectif'] }}
                personne{{ $section['effectif'] > 1 ? 's' : '' }} · taux moyen {{ $section['taux_assiduite'] }} %
            </h2>
            <table>
                <thead>
                    <tr>
                        <th class="text-left">Personnel</th>
                        <th>Matricule</th>
                        <th>Fonction</th>
                        <th>Jours attendus</th>
                        <th>Jours assurés</th>
                        <th>Signalements</th>
                        <th>Taux</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($section['lignes'] as $ligne)
                        <tr>
                            <td class="text-left">{{ $ligne['nom'] }}</td>
                            <td>{{ $ligne['matricule'] ?? '—' }}</td>
                            <td>{{ $ligne['fonction'] ?? '—' }}</td>
                            <td>{{ $ligne['jours_attendus'] }}</td>
                            <td>{{ $ligne['jours_presents'] }}</td>
                            <td>{{ $ligne['jours_signales'] }}</td>
                            <td>{{ $ligne['taux_assiduite'] }} %</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @empty
            <div class="vide">Aucun personnel dans le périmètre pour ce mois.</div>
        @endforelse

        @if ($bilan['sections'])
            <h2>Synthèse par section</h2>
            <table>
                <thead>
                    <tr>
                        <th class="text-left">Section</th>
                        <th>Effectif</th>
                        <th>Jours attendus</th>
                        <th>Jours assurés</th>
                        <th>Taux</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bilan['sections'] as $section)
                        <tr>
                            <td class="text-left">{{ $section['section'] }}</td>
                            <td>{{ $section['effectif'] }}</td>
                            <td>{{ $section['jours_attendus'] }}</td>
                            <td>{{ $section['jours_presents'] }}</td>
                            <td>{{ $section['taux_assiduite'] }} %</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td class="text-left">TOTAL</td>
                        <td>{{ $bilan['total']['effectif'] }}</td>
                        <td>{{ $bilan['total']['jours_attendus'] }}</td>
                        <td>{{ $bilan['total']['jours_presents'] }}</td>
                        <td>{{ $bilan['total']['taux_assiduite'] }} %</td>
                    </tr>
                </tbody>
            </table>
        @endif
    @endif

    @if ($volet !== 'assiduite')
        {{-- Dans le document consacré au classement, le titre le dit déjà. --}}
        @if ($volet !== 'ponctualite')
            <h2>Classement de ponctualité</h2>
        @endif
        @if ($bilan['ponctualite'])
            <table>
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th class="text-left">Personnel</th>
                        <th>Matricule</th>
                        <th>Section</th>
                        <th>Jours prévus</th>
                        <th>Jours ponctuels</th>
                        <th>Retards</th>
                        <th>Minutes</th>
                        <th>Taux</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($bilan['ponctualite'] as $rang => $ligne)
                        <tr>
                            <td>{{ $rang + 1 }}</td>
                            <td class="text-left">{{ $ligne['nom'] }}</td>
                            <td>{{ $ligne['matricule'] ?? '—' }}</td>
                            <td>{{ $ligne['section'] ?? '—' }}</td>
                            <td>{{ $ligne['jours_prevus'] }}</td>
                            <td>{{ $ligne['jours_ponctuels'] }}</td>
                            <td>{{ $ligne['nb_jours_retard'] }}</td>
                            <td>{{ $ligne['total_retard_minutes'] }}</td>
                            <td>{{ $ligne['taux_ponctualite'] }} %</td>
                            <td>{{ $ligne['score_ponctualite'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="note">
                Le score pondère le taux de ponctualité par les minutes de retard cumulées : à nombre de jours
                ponctuels égal, les retards les plus longs pèsent le plus.
            </div>
        @else
            <div class="vide">Aucun personnel dans le périmètre pour ce mois.</div>
        @endif
    @endif
</body>

</html>
