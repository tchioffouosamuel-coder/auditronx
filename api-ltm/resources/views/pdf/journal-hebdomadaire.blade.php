<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Journal hebdomadaire des présences</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 8px;
            margin: 18px;
        }

        h1 {
            text-align: center;
            font-size: 15px;
            margin-bottom: 4px;
        }

        .periode {
            text-align: center;
            margin-bottom: 14px;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 3px 4px;
            text-align: center;
        }

        th {
            background: #e8edf2;
            font-size: 8px;
        }

        th.jour span {
            display: block;
            font-weight: normal;
            font-size: 7px;
        }

        td.nom,
        th.nom {
            text-align: left;
            width: 180px;
        }

        /* Absent à un jour attendu : c'est l'information que l'on cherche
           dans ce document, elle doit ressortir même en impression noir et
           blanc, d'où le fond gris en plus de la couleur. */
        td.absent {
            background: #f3d7d7;
            color: #8a1f1f;
            font-weight: bold;
        }

        td.hors {
            background: #f4f4f4;
            color: #999;
        }

        .legende {
            margin-top: 10px;
            font-size: 8px;
            color: #444;
        }

        .legende span {
            margin-right: 14px;
        }

        .empty {
            text-align: center;
            padding: 20px;
        }
    </style>
</head>

<body>
    <h1>Journal hebdomadaire des présences</h1>
    <div class="periode">
        Semaine du {{ $debut->locale('fr')->translatedFormat('d F Y') }}
        au {{ $fin->locale('fr')->translatedFormat('d F Y') }}
    </div>

    @if ($lignes->isEmpty())
        <p class="empty">Aucun membre du personnel dans votre périmètre.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th class="nom">Personnel</th>
                    <th>Section</th>
                    @foreach ($jours as $jour)
                        <th class="jour">
                            {{ ucfirst($jour->locale('fr')->translatedFormat('D')) }}
                            <span>{{ $jour->format('d/m') }}</span>
                        </th>
                    @endforeach
                    <th>Présences</th>
                    <th>Taux</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lignes as $ligne)
                    <tr>
                        <td class="nom">{{ $ligne['nom'] }}</td>
                        <td>{{ $ligne['section'] ?? '—' }}</td>
                        @foreach ($ligne['jours'] as $cellule)
                            @php
                                $classe = ! $cellule['attendu']
                                    ? 'hors'
                                    : ($cellule['present'] ? '' : 'absent');
                            @endphp
                            <td class="{{ $classe }}">
                                @if ($cellule['present'])
                                    {{ $cellule['heure_arrivee'] ?? '—' }}<br>
                                    {{ $cellule['heure_depart'] ?? '—' }}
                                @elseif ($cellule['attendu'])
                                    ABS
                                @else
                                    ·
                                @endif
                            </td>
                        @endforeach
                        <td>{{ $ligne['jours_presents'] }} / {{ $ligne['jours_attendus'] }}</td>
                        <td>{{ $ligne['taux_assiduite'] === null ? '—' : $ligne['taux_assiduite'] . ' %' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="legende">
            <span><strong>HH:MM / HH:MM</strong> arrivée et départ</span>
            <span><strong>ABS</strong> attendu, aucun pointage</span>
            <span><strong>·</strong> non attendu ce jour-là</span>
            <span><strong>—</strong> taux indisponible : aucun jour attendu, emploi du temps probablement absent</span>
        </div>
        <p>{{ $lignes->count() }} membre(s) du personnel</p>
    @endif
</body>

</html>
