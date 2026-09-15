<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1 { font-size: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 4px 8px; text-align: left; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <h1>Bilan des retards — {{ $debut->format('d/m/Y') }} au {{ $fin->format('d/m/Y') }}</h1>
    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>Matricule</th>
                <th>Section</th>
                <th>Jours de retard</th>
                <th>Minutes cumulées</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lignes as $ligne)
                <tr>
                    <td>{{ $ligne['nom'] }}</td>
                    <td>{{ $ligne['matricule'] }}</td>
                    <td>{{ $ligne['section'] }}</td>
                    <td>{{ $ligne['jours_retard'] }}</td>
                    <td>{{ $ligne['minutes_retard_total'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
