{{--
    Procès-verbal du conseil de classe (§bulletins APC, lot 6), A4 paysage.

    Classe triée par rang, décision proposée et décision retenue côte à côte
    — un astérisque marque celles où le conseil s'est écarté du barème —, puis
    le taux de compétences validées par module.
--}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>{{ $titre }}</title>
    <style>
        @page { margin: 9mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8px; color: #111; }
        table { width: 100%; border-collapse: collapse; }
        .grille th, .grille td { border: 0.6px solid #333; padding: 2px 3px; }
        .grille th { background: #e4e8ee; font-size: 7.4px; }
        .c { text-align: center; }
        .b { font-weight: bold; }
        h1 { font-size: 13px; text-align: center; margin: 2px 0; }
        h2 { font-size: 9.5px; margin: 8px 0 3px; }
        .sous-titre { text-align: center; font-size: 9px; margin-bottom: 6px; }
        .resume td { border: none; padding: 1px 6px 1px 0; font-size: 8.5px; }
        .signatures td { border: none; text-align: center; height: 40px; vertical-align: top; }
        .filigrane {
            position: fixed; top: 220px; left: 120px; width: 800px; text-align: center;
            font-size: 80px; font-weight: bold; color: #c8102e; opacity: 0.12; transform: rotate(-25deg);
        }
    </style>
</head>

<body>
    @if ($filigrane)
        <div class="filigrane">{{ $filigrane }}</div>
    @endif

    @include('pdf.partials.entete')
    <h1>{{ $titre }}</h1>
    <div class="sous-titre">
        Classe : <span class="b">{{ $classe['nom'] ?: $classe['code'] }}</span> —
        {{ $periode }} — Année Scolaire : {{ $annee }}
    </div>

    <table class="resume">
        <tr>
            <td>Effectif : <span class="b">{{ $effectif }}</span></td>
            <td>Élèves classés : <span class="b">{{ $effectif_classe }}</span></td>
            <td>Moyenne de la classe : <span class="b">{{ $moyenne_classe }}</span></td>
            <td>Plus forte : {{ $moyenne_max }}</td>
            <td>Plus faible : {{ $moyenne_min }}</td>
            @foreach ($decisions as $libelle => $nombre)
                <td>{{ $libelle }} : <span class="b">{{ $nombre }}</span></td>
            @endforeach
        </tr>
    </table>

    <h2>Résultats et décisions</h2>
    <table class="grille">
        <thead>
            <tr>
                <th style="width:6%">Rang</th>
                <th style="width:9%">Matricule</th>
                <th style="width:20%">Nom et prénoms</th>
                <th style="width:7%">Statut</th>
                <th style="width:6%">Moyenne</th>
                <th style="width:5%">Mention</th>
                <th style="width:5%">Crédits</th>
                <th style="width:6%">Comp. non val.</th>
                <th style="width:10%">Décision proposée</th>
                <th style="width:10%">Décision retenue</th>
                <th style="width:16%">Observation</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($eleves as $e)
                <tr>
                    <td class="c">{{ $e['rang'] }}</td>
                    <td>{{ $e['matricule'] }}</td>
                    <td>{{ $e['nom_complet'] }}</td>
                    <td class="c">{{ $e['statut'] }}</td>
                    <td class="c b">{{ $e['moyenne'] }}</td>
                    <td class="c">{{ $e['mention'] }}</td>
                    <td class="c">{{ $e['credits'] }}</td>
                    <td class="c">{{ $e['non_validees'] }}</td>
                    <td>{{ $e['decision_proposee'] ?? '—' }}</td>
                    <td class="b">{{ $e['decision'] ?? '—' }}{{ $e['modifiee'] ? ' *' : '' }}</td>
                    <td>{{ $e['observation'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div style="font-size:7px;margin-top:2px">* décision modifiée par le conseil de classe.</div>

    <h2>Compétences validées par module</h2>
    <table class="grille">
        <thead>
            <tr>
                <th style="width:8%">Code</th>
                <th style="width:48%">Module</th>
                <th style="width:10%">Élèves notés</th>
                <th style="width:12%">Compétences validées</th>
                <th style="width:10%">Taux</th>
                <th style="width:12%">Moyenne du module</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($modules as $m)
                <tr>
                    <td class="c">{{ $m['code'] }}</td>
                    <td>{{ $m['intitule'] }}</td>
                    <td class="c">{{ $m['notes'] }}</td>
                    <td class="c">{{ $m['validees'] }}</td>
                    <td class="c b">{{ $m['taux'] }}</td>
                    <td class="c">{{ $m['moyenne'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signatures" style="margin-top:10px">
        <tr>
            <td style="width:33%">Le Professeur principal</td>
            <td style="width:33%">Le Censeur</td>
            <td style="width:34%">
                Le Chef d'établissement, président du conseil
                @if ($valide_le)
                    <br><span style="font-size:7px">Conseil validé le {{ $valide_le }}</span>
                @endif
            </td>
        </tr>
    </table>
</body>

</html>
