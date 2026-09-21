<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Fiche d'assiduité</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12px;
            color: black;
            margin: 20px;
        }

        h1 {
            text-align: center;
            font-size: 18px;
            margin-bottom: 10px;
            text-transform: uppercase;
            color: black;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #999;
            padding: 6px 8px;
            text-align: center;
            word-wrap: break-word;
            vertical-align: middle;
        }

        th {
            background-color: #ecf0f1;
            font-weight: bold;
            font-size: 11px;
            color: black;
        }

        td {
            font-size: 10px;
        }

        .text-uppercase {
            text-transform: uppercase;
        }

        .text-capitalize {
            text-transform: capitalize;
        }

        .bg-danger {
            background-color: #f8d7da;
        }

        .bg-warning {
            background-color: #fff3cd;
        }

        .bg-success {
            background-color: #d4edda;
        }

        .bg-no-schedule {
            background-color: #f0f0f0;
        }

        .low-taux {
            color: #dc3545;
            font-weight: bold;
        }

        .medium-taux {
            color: #ff8c00;
            font-weight: bold;
        }

        .high-taux {
            color: #28a745;
            font-weight: bold;
        }

        .no-schedule-cell {
            background-color: #f5f5f5;
            color: #666;
            font-weight: bold;
            font-style: italic;
        }

        .empty-message {
            text-align: center;
            font-style: italic;
            color: #666;
            padding: 20px;
            background-color: #f8f9fa;
            margin: 20px 0;
        }

        .footer {
            margin-top: 40px;
            font-size: 10px;
            text-align: center;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .sign {
            margin-top: 40px;
            font-size: 12px;
            text-align: right;
            color: black;
        }

        .stats-summary {
            margin: 20px 0;
            padding: 10px;
            background-color: #f8f9fa;
            border-left: 4px solid #3498db;
            font-size: 11px;
        }

        .stats-summary strong {
            color: #2c3e50;
        }

        .col-num {
            width: 3%;
        }

        .col-nom {
            width: 15%;
            text-align: left;
        }

        .col-contact {
            width: 8%;
        }

        .col-fonction {
            width: 10%;
        }

        .col-matricule {
            width: 8%;
        }

        .col-specialite {
            width: 10%;
        }

        .col-cours {
            width: 6%;
        }

        .col-periodes {
            width: 7%;
        }

        .col-attendues {
            width: 8%;
        }

        .col-enregistrees {
            width: 8%;
        }

        .col-taux {
            width: 7%;
        }

        .col-observation {
            width: 10%;
        }
    </style>
</head>

<body>
    <table style="width:100%; border-collapse:collapse; background:transparent; margin-bottom:20px;">
        <tr>
            <th style="border:none; background:transparent; text-align:center; font-size:11px; width:38%;">
                REPUBLIQUE DU CAMEROUN<br><i>Paix - Travail - Patrie</i><br>
                MINISTERE DES ENSEIGNEMENTS SECONDAIRES<br>
                DELEGATION REGIONALE DES ENSEIGNEMENTS SECONDAIRES DE L'ADAMAOUA<br>
                DELEGATION DEPARTEMENTALE DU MBERE<br><b style="font-size:13px">LYCEE TECHNIQUE DE MEIGANGA</b>
            </th>
            <th style="border:none; background:transparent; text-align:center; width:24%;">
                @if (file_exists(public_path('logo.png')))
                    <img src="{{ public_path('logo.png') }}" alt="Logo"
                        style="height:70px; width:auto; display:block; margin:auto;">
                @endif
            </th>
            <th style="border:none; background:transparent; text-align:center; font-size:11px; width:38%;">
                REPUBLIC OF CAMEROON<br><i>Peace - Work - Fatherland</i><br>
                MINISTRY OF SECONDARY EDUCATION<br>
                ADAMAWA REGIONAL DELEGATION OF SECONDARY EDUCATION<br>
                MBERE DIVISIONAL DELEGATION<br><b style="font-size:13px">G.T.H.S MEIGANGA</b>
            </th>
        </tr>
    </table>

    <h1>
        Fiche d'assiduité mensuelle du personnel
        @if ($type === 'enseignant')
            ENSEIGNANT PERMANENT
        @elseif($type === 'vacataire')
            VACATAIRE
        @else
            ADMINISTRATIF
        @endif
        - {{ \Carbon\Carbon::createFromFormat('m', $mois)->locale('fr')->translatedFormat('F') }} {{ $annee }}
    </h1>
    <div style="text-align:center; font-size:11px; margin-bottom:15px;">Période : {{ $debut->format('d/m/Y') }} au
        {{ $fin->format('d/m/Y') }}</div>

    @if (count($data) > 0)
        @php
            $row = $data[0];
            $nbCours = $row['nb_cours'] ?? 0;
            $taux = floatval($row['taux'] ?? 0);
            $hasSignalement = !empty($row['dates_signalement']);
            $shouldShowData = $type === 'administratif' || $nbCours > 0;
            $rowClass = !$shouldShowData
                ? 'bg-no-schedule'
                : ($taux < 50
                    ? 'bg-danger'
                    : ($taux < 75
                        ? 'bg-warning'
                        : ($taux >= 90
                            ? 'bg-success'
                            : '')));
            $tauxClass = $taux < 50 ? 'low-taux' : ($taux < 75 ? 'medium-taux' : 'high-taux');
            $observation = !$shouldShowData
                ? 'Pas d\'emploi du temps'
                : ($hasSignalement
                    ? 'Signalement(s)'
                    : ($taux < 50
                        ? 'Assiduité très faible'
                        : ($taux < 75
                            ? 'Assiduité moyenne'
                            : ($taux >= 90
                                ? 'Très assidu'
                                : 'Assidu'))));
        @endphp
        <div class="stats-summary">
            <strong>Résumé :</strong> 1 personne | {{ $row['total_periodes'] ?? 0 }} périodes totales |
            {{ $row['nbre_attendues'] ?? 0 }} jour(s) attendu(s) | {{ $row['nbre_enregistrees'] ?? 0 }} jour(s)
            enregistré(s) |
            Taux d'assiduité : <strong>{{ number_format($taux, 1) }}%</strong>
            @if (($row['jours_retard'] ?? 0) > 0)
                | Retards : <strong>{{ $row['jours_retard'] }} jour(s), {{ $row['minutes_retard_total'] }}
                    minute(s)</strong>
            @endif
        </div>

        <table>
            <thead>
                <tr>
                    <th class="col-num">N°</th>
                    <th class="col-nom">Noms</th>
                    <th class="col-contact">Contact</th>
                    <th class="col-fonction">Fonction</th>
                    <th class="col-matricule">Matricule</th>
                    <th class="col-specialite">Spécialité</th>
                    <th class="col-cours">Nb Cours</th>
                    <th class="col-periodes">Périodes</th>
                    <th class="col-attendues">J. Attendus</th>
                    <th class="col-enregistrees">J. Enregistrés</th>
                    <th class="col-taux">Taux (%)</th>
                    <th class="col-observation">Observation</th>
                </tr>
            </thead>
            <tbody>
                <tr class="{{ $rowClass }}">
                    <td class="col-num">1</td>
                    <td class="col-nom text-uppercase">{{ $row['nom'] }}</td>
                    <td class="col-contact">{{ $row['tel'] ?? '—' }}</td>
                    <td class="col-fonction text-capitalize">{{ $row['fonction'] ?? '—' }}</td>
                    <td class="col-matricule">{{ $row['matricule'] ?? '—' }}</td>
                    <td class="col-specialite text-capitalize">{{ $row['specialite'] ?? '—' }}</td>
                    @if ($shouldShowData)
                        <td class="col-cours">{{ $nbCours }}</td>
                        <td class="col-periodes">{{ $row['total_periodes'] ?? 0 }}</td>
                        <td class="col-attendues">{{ $row['nbre_attendues'] ?? 0 }}</td>
                        <td class="col-enregistrees">{{ $row['nbre_enregistrees'] ?? 0 }}@if ($hasSignalement)
                                <sup style="color:#007bff">*</sup>
                            @endif
                        </td>
                        <td class="col-taux {{ $tauxClass }}">{{ number_format($taux, 1) }}</td>
                        <td class="col-observation" style="font-size:9px">{{ $observation }}</td>
                    @else
                        <td colspan="6" class="no-schedule-cell">AUCUN EMPLOI DU TEMPS ENREGISTRÉ (CALCUL IMPOSSIBLE)
                        </td>
                    @endif
                </tr>
            </tbody>
        </table>

        <div style="font-size:10px; margin-top:10px; color:#666;">
            <strong>Légende :</strong><br>
            • <strong>Nb Cours :</strong> Nombre de cours dans l'emploi du temps<br>
            • <strong>Périodes :</strong> Total de périodes de 40 minutes prévues dans la période<br>
            • <strong>J. Attendus :</strong> Jours de présence attendus selon l'emploi du temps<br>
            • <strong>J. Enregistrés :</strong> Jours de présence effectivement enregistrés<br>
            • <sup style="color:#007bff">*</sup> Présences incluant des signalements<br>
            • <span class="bg-danger" style="padding:2px 5px">Fond rouge</span> : Taux &lt; 50%
            | <span class="bg-warning" style="padding:2px 5px">Fond jaune</span> : 50% &lt;= Taux &lt; 75%
            | <span class="bg-success" style="padding:2px 5px">Fond vert</span> : Taux &gt;= 90%
        </div>
    @else
        <div class="empty-message"><strong>Aucun personnel enregistré</strong></div>
    @endif

    <div class="sign">Fait à Meiganga, le
        {{ \Carbon\Carbon::now()->locale('fr')->translatedFormat('d F Y') }}<br><br><br><strong>Le Proviseur</strong>
    </div>
    <div class="footer">Document généré automatiquement le
        {{ \Carbon\Carbon::now()->locale('fr')->translatedFormat('d/m/Y à H:i') }} par <strong>Auditron-X</strong> | ©
        Lycée Technique de Meiganga {{ \Carbon\Carbon::now()->format('Y') }}</div>
</body>

</html>
