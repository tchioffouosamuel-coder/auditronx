<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Enseignants sans présence</title>
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
    <h1>Enseignants sans aucune présence enregistrée</h1>
    <div class="date">Situation au {{ $date->locale('fr')->translatedFormat('l d F Y') }}</div>
    @if ($enseignants->isEmpty())
        <p class="empty">Tous les enseignants ont au moins une présence enregistrée.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Nom</th>
                    <th>Matricule</th>
                    <th>Section</th>
                    <th>Fonction</th>
                    <th>Téléphone</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($enseignants as $enseignant)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $enseignant->nom }}</td>
                        <td>{{ $enseignant->matricule ?? '—' }}</td>
                        <td>{{ $enseignant->section ?? '—' }}</td>
                        <td>{{ $enseignant->fonction ?? '—' }}</td>
                        <td>{{ $enseignant->tel ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p>{{ $enseignants->count() }} enseignant(s) sans aucune présence</p>
    @endif
</body>

</html>
