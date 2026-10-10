<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Liste du personnel</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 24px;
        }

        h1 {
            text-align: center;
            font-size: 16px;
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
    <h1>Liste du personnel</h1>
    @if ($personnel->isEmpty())
        <p class="empty">Aucun personnel enregistré.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Matricule</th>
                    <th>Fonction</th>
                    <th>Section</th>
                    <th>Grade</th>
                    <th>Téléphone</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($personnel as $enseignant)
                    <tr>
                        <td>{{ $enseignant->nom }}</td>
                        <td>{{ $enseignant->matricule }}</td>
                        <td>{{ $enseignant->fonction ?? '—' }}</td>
                        <td>{{ $enseignant->section ?? '—' }}</td>
                        <td>{{ $enseignant->grade ?? '—' }}</td>
                        <td>{{ $enseignant->tel ?? '—' }}</td>
                        <td>{{ $enseignant->email ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>

</html>
