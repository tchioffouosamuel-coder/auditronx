<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Journal des présences</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 24px;
        }

        h1 {
            text-align: center;
            font-size: 16px;
            margin-bottom: 4px;
        }

        .date {
            text-align: center;
            margin-bottom: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 6px 8px;
            text-align: left;
        }

        th {
            background: #e8edf2;
        }

        .empty {
            text-align: center;
            padding: 20px;
        }
    </style>
</head>

<body>
    <h1>Journal des présences</h1>
    <div class="date">{{ $date->locale('fr')->translatedFormat('l d F Y') }}</div>
    @if ($presences->isEmpty())
        <p class="empty">Aucune présence enregistrée pour cette date.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Enseignant</th>
                    <th>Section</th>
                    <th>Arrivée</th>
                    <th>Départ</th>
                    <th>Source</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($presences as $presence)
                    <tr>
                        <td>{{ $presence->enseignant?->nom ?? '—' }}</td>
                        <td>{{ $presence->enseignant?->section ?? '—' }}</td>
                        <td>{{ $presence->heure_arrivee?->format('H:i') ?? '—' }}</td>
                        <td>{{ $presence->heure_depart?->format('H:i') ?? '—' }}</td>
                        <td>{{ $presence->source ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p>{{ $presences->count() }} présence(s)</p>
    @endif
</body>

</html>
