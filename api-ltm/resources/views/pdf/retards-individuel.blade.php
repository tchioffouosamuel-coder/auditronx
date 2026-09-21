<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Fiche de contrôle</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            margin: 10px
        }

        h1 {
            text-align: center;
            font-size: 13px;
            margin: 5px 0;
            text-transform: uppercase
        }

        h2 {
            font-size: 10px;
            margin: 8px 0 4px;
            border-bottom: 1px solid #000;
            padding-bottom: 2px
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0
        }

        th,
        td {
            border: 1px solid #000;
            padding: 3px 4px;
            text-align: center
        }

        th {
            background: #e0e0e0;
            font-size: 10px
        }

        td {
            font-size: 10px
        }

        .info-table th {
            width: 18%;
            text-align: left
        }

        .info-table td {
            text-align: left
        }

        .text-left {
            text-align: left !important
        }

        .bold {
            font-weight: bold
        }

        .center {
            text-align: center
        }

        .absent {
            background: #d0d0d0
        }

        .footer {
            margin-top: 10px;
            font-size: 7px;
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 3px
        }
    </style>
</head>

<body>
    <table style="border:none;margin-bottom:5px">
        <tr>
            <td style="border:none;width:40%;text-align:center;font-size:8px">REPUBLIQUE DU CAMEROUN<br>Paix - Travail -
                Patrie<br>LYCEE TECHNIQUE DE MEIGANGA</td>
            <td style="border:none;width:20%;text-align:center">
                @if (file_exists(public_path('logo.png')))
                    <img src="{{ public_path('logo.png') }}" alt="Logo" style="height:40px">
                @endif
            </td>
            <td style="border:none;width:40%;text-align:center;font-size:8px">REPUBLIC OF CAMEROON<br>Peace - Work -
                Fatherland<br>G.T.H.S MEIGANGA</td>
        </tr>
    </table>
    <h1>Fiche de contrôle d'assiduité et
        ponctualité<br>{{ \Carbon\Carbon::createFromFormat('m', $mois)->locale('fr')->translatedFormat('F') }}
        {{ $annee }}</h1>
    <h2>ENSEIGNANT</h2>
    <table class="info-table">
        <tr>
            <th>Nom</th>
            <td class="bold">{{ strtoupper($enseignant->nom) }}</td>
            <th>Téléphone</th>
            <td>{{ $enseignant->tel ?? '-' }}</td>
            <th>Matricule</th>
            <td>{{ $enseignant->matricule ?? '-' }}</td>
        </tr>
    </table>
    <h2>EMPLOI DU TEMPS HEBDOMADAIRE</h2>
    <table>
        <tr>
            <th style="width:12%">Jour</th>
            <th>Horaires</th>
            <th>Discipline</th>
            <th>Classe</th>
        </tr>
        @foreach (['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'] as $jour)
            @if (isset($emploi_par_jour[$jour]))
                @foreach ($emploi_par_jour[$jour] as $key => $emp)
                    <tr>
                        @if ($key == 0)
                            <td rowspan="{{ $emploi_par_jour[$jour]->count() }}" class="bold">{{ $jour }}</td>
                        @endif
                        <td>
                            {{ $emp->heure_debut }} - {{ $emp->heure_fin }}</td>
                        <td class="text-left">{{ $emp->discipline->nom ?? '-' }}</td>
                        <td>{{ $emp->classe->nom ?? '-' }}</td>
                    </tr>
                @endforeach
            @endif
        @endforeach
    </table>
    <h2>DÉTAIL MENSUEL DES PRÉSENCES</h2>
    <table>
        <tr>
            <th style="width:8%">Date</th>
            <th style="width:8%">Jour</th>
            <th style="width:7%">Cours</th>
            <th>Prévu début</th>
            <th>Prévu fin</th>
            <th>Arrivée</th>
            <th>Départ</th>
            <th>Retard (min)</th>
            <th>Anticip. (min)</th>
            <th>Périodes à rattraper</th>
            <th>Statut</th>
        </tr>
        @foreach ($details as $d)
            <tr class="{{ $d['absent'] && !$d['est_signale'] ? 'absent' : '' }}">
                <td>{{ $d['date'] }}</td>
                <td>{{ $d['jour'] }}</td>
                <td>{{ $d['nb_cours'] }}</td>
                <td>{{ $d['heure_debut_prevue'] }}</td>
                <td>{{ $d['heure_fin_prevue'] }}</td>
                @if ($d['futur'])
                    <td colspan="5" class="center">-</td>
                    <td>A venir</td>
                @elseif($d['est_signale'])
                    <td colspan="3" class="center bold" style="color:#0666cc">SIGNALÉ</td>
                    <td>0</td>
                    <td class="bold">0.00</td>
                    <td style="color:#0666cc">Signalé ({{ ucfirst($d['type_signalement'] ?? 'Excuse') }})</td>
                @elseif($d['absent'])
                    <td colspan="3" class="center bold">ABSENT</td>
                    <td>-</td>
                    <td class="bold">{{ number_format($d['periodes_a_rattraper'], 2, '.', '') }}</td>
                    <td>Absence</td>
                @elseif($d['heure_arrivee'] === null && $d['heure_depart'] !== null)
                    <td>-</td>
                    <td>{{ $d['heure_depart'] }}</td>
                    <td>-</td>
                    <td>{{ round($d['anticipation_minutes']) }}</td>
                    <td class="bold">{{ number_format($d['periodes_a_rattraper'], 2, '.', '') }}</td>
                <td>Sortie seule</td>@else<td>{{ $d['heure_arrivee'] }}</td>
                    <td>{{ $d['heure_depart'] ?? '-' }}</td>
                    <td>{{ $d['retard_minutes'] !== null ? round($d['retard_minutes']) : '-' }}</td>
                    <td>{{ round($d['anticipation_minutes']) }}</td>
                    <td class="bold">{{ number_format($d['periodes_a_rattraper'], 2, '.', '') }}</td>
                    <td>
                        @if ($d['retard_minutes'] == 0 && $d['anticipation_minutes'] == 0)
                            Ponctuel
                        @elseif($d['retard_minutes'] > 0 && $d['anticipation_minutes'] > 0)
                            Retard+Anticip.
                        @elseif($d['retard_minutes'] > 0)
                            Retard
                        @elseif($d['anticipation_minutes'] > 0)
                            Anticipation
                        @else
                            Présent
                        @endif
                    </td>
                @endif
            </tr>
        @endforeach
    </table>
    <h2>RÉSUMÉ MENSUEL</h2>
    <table>
        <tr>
            <th>Indicateur</th>
            <th>Valeur</th>
            <th>Indicateur</th>
            <th>Valeur</th>
        </tr>
        <tr>
            <td class="text-left bold">Total retard (min)</td>
            <td>{{ round($total_retard_minutes) }}</td>
            <td class="text-left bold">Jours de cours prévus</td>
            <td>{{ $jours_attendus }}</td>
        </tr>
        <tr>
            <td class="text-left bold">Total anticipation (min)</td>
            <td>{{ round($total_anticipation_minutes) }}</td>
            <td class="text-left bold">Présences validées</td>
            <td>{{ $presences_valides }}</td>
        </tr>
        <tr>
            <td class="text-left bold">Périodes à rattraper (présence)</td>
            <td class="bold">{{ number_format($total_periodes_a_rattraper, 2, '.', '') }}</td>
            <td class="text-left bold">Taux d'assiduité</td>
            <td class="bold">{{ number_format($taux_assiduite, 1, '.', '') }}%</td>
        </tr>
        <tr>
            <td class="text-left bold">Périodes à rattraper (absence)</td>
            <td class="bold">{{ number_format($total_periodes_absence, 2, '.', '') }}</td>
            <td class="text-left bold">TOTAL périodes à rattraper</td>
            <td class="bold">{{ number_format($total_periodes_a_rattraper + $total_periodes_absence, 2, '.', '') }}</td>
        </tr>
    </table>
    <div style="margin-top:10px;text-align:right;font-size:9px">Fait à Meiganga, le
        {{ \Carbon\Carbon::now()->locale('fr')->translatedFormat('d F Y') }}<br><br><br><br><strong>Le
            Proviseur</strong></div>
    <div class="footer">Document généré le {{ \Carbon\Carbon::now()->format('d/m/Y à H:i') }} | Auditron-X | LTMG
        {{ \Carbon\Carbon::now()->format('Y') }}</div>
</body>

</html>
