{{-- Titre et identité de l'élève, en tête de chaque bulletin. --}}
<h1>{{ $page['titre'] }}</h1>
<div class="sous-titre">Année Scolaire : {{ $page['annee'] }}</div>

<table class="identite" style="margin-bottom:4px">
    <tr>
        <td style="width:50%">Nom de l'Élève : <span class="b">{{ $page['eleve']['nom_complet'] }}</span></td>
        <td style="width:25%">Matricule : <span class="b">{{ $page['eleve']['matricule'] }}</span></td>
        <td style="width:25%">Sexe : {{ $page['eleve']['sexe'] }}</td>
    </tr>
    <tr>
        <td>Né(e) le : {{ $page['eleve']['naissance'] }}</td>
        <td>Classe : <span class="b">{{ $page['classe']['nom'] ?: $page['classe']['code'] }}</span></td>
        <td>Effectif : {{ $page['effectif'] }}</td>
    </tr>
    <tr>
        <td>Spécialité : {{ $page['classe']['specialite'] }}</td>
        <td colspan="2">Statut : {{ $page['eleve']['statut'] }}</td>
    </tr>
</table>
