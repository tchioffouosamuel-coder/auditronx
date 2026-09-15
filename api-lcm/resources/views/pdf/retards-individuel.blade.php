<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1 { font-size: 16px; }
        .fiche { margin-top: 12px; }
        .fiche div { margin-bottom: 4px; }
    </style>
</head>
<body>
    <h1>Fiche individuelle de retards</h1>
    <div class="fiche">
        <div><strong>Nom :</strong> {{ $enseignant->nom }}</div>
        <div><strong>Matricule :</strong> {{ $enseignant->matricule }}</div>
        <div><strong>Section :</strong> {{ $enseignant->section }}</div>
        <div><strong>Période :</strong> {{ $debut->format('d/m/Y') }} au {{ $fin->format('d/m/Y') }}</div>
        <div><strong>Jours de retard :</strong> {{ $ligne['jours_retard'] ?? 0 }}</div>
        <div><strong>Minutes cumulées :</strong> {{ $ligne['minutes_retard_total'] ?? 0 }}</div>
    </div>
</body>
</html>
