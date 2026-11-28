{{-- Titre et identité de l'élève, disposés comme sur le canevas officiel. --}}
<h1>{{ $page['titre'] }}</h1>

<table class="identite">
    <tr>
        <td colspan="2"><span class="b">SPÉCIALITÉ :</span> {{ $page['classe']['specialite'] }}</td>
        <td colspan="2" class="r" style="white-space:nowrap"><span class="b">Année Scolaire :</span> {{ $page['annee'] }}</td>
    </tr>
    <tr>
        <td style="width:42%"><span class="b">Nom de l'Élève :</span> <u>{{ $page['eleve']['nom_complet'] }}</u></td>
        <td style="width:22%"><span class="b">Classe :</span> <u>{{ $page['classe']['nom'] ?: $page['classe']['code'] }}</u></td>
        <td style="width:22%"><span class="b">Matricule :</span> <u>{{ $page['eleve']['matricule'] }}</u></td>
        <td style="width:14%" class="r"><span class="b">Effectif :</span> <u>{{ $page['effectif'] }}</u></td>
    </tr>
    <tr>
        <td colspan="4">
            <span class="b">Né(e) le :</span> <u>{{ $page['eleve']['date_naissance'] }}</u>
            <span class="b">à</span> <u>{{ $page['eleve']['lieu_naissance'] }}</u>
            &nbsp;<span class="b">Sexe</span> <u>{{ $page['eleve']['sexe'] }}</u>
            &nbsp;<span class="b">Statut :</span>
            @foreach ($page['statuts'] as $valeur => $libelle)
                @if (! $loop->first) / @endif
                @if ($page['statut'] === $valeur)<u>{{ $libelle }}</u>@else{{ $libelle }}@endif
            @endforeach
        </td>
    </tr>
</table>

<h2>I. ÉVALUATIONS ET MAÎTRISE DES COMPÉTENCES</h2>
