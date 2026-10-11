<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Bilan mensuel d'assiduité et de ponctualité</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            margin: 10px
        }

        h1 {
            text-align: center;
            font-size: 14px;
            margin: 5px 0;
            text-transform: uppercase
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0
        }

        th,
        td {
            border: 1px solid #000;
            padding: 3px 4px;
            text-align: center
        }

        th {
            background: #e0e0e0;
            font-size: 8px
        }

        td {
            font-size: 8px
        }

        .text-left {
            text-align: left !important
        }

        .bold {
            font-weight: bold
        }

        .footer {
            margin-top: 15px;
            font-size: 7px;
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 5px
        }

        .summary {
            margin: 10px 0;
            padding: 6px;
            border: 1px solid #000;
            font-size: 8px
        }

        .retard-col {
            background: #fff3cd
        }

        .anticip-col {
            background: #cfe2ff
        }

        .absence-col {
            background: #f8d7da
        }

        .total-col {
            background: #d4edda;
            font-weight: bold
        }

        .assiduite-col {
            background: #e2e3e5;
            font-weight: bold
        }
    </style>
</head>

<body>
    <table style="border:none;margin-bottom:8px">
        <tr>
            <td style="border:none;width:40%;text-align:center;font-size:8px">REPUBLIQUE DU CAMEROUN<br>Paix - Travail -
                Patrie<br>LYCEE TECHNIQUE DE MEIGANGA</td>
            <td style="border:none;width:20%;text-align:center">
                <?php if(file_exists(public_path('logo.png'))): ?>
                    <img src="<?php echo e(public_path('logo.png')); ?>" alt="Logo" style="height:40px">
                <?php endif; ?>
            </td>
            <td style="border:none;width:40%;text-align:center;font-size:8px">REPUBLIC OF CAMEROON<br>Peace - Work -
                Fatherland<br>G.T.H.S MEIGANGA</td>
        </tr>
    </table>
    <h1>Bilan mensuel d'assiduité et de
        ponctualité<br><?php echo e(\Carbon\Carbon::createFromFormat('m', $mois)->locale('fr')->translatedFormat('F')); ?>

        <?php echo e($annee); ?></h1>
    <?php if(count($data) === 0): ?>
        <div style="text-align:center;padding:20px;border:1px solid #000;margin:20px 0"><strong>Aucune donnée
                disponible</strong><br>Tous les enseignants sont ponctuels ou aucun emploi du temps configuré.</div>
    <?php else: ?>
        <div class="summary"><strong>RÉSUMÉ GLOBAL:</strong> <?php echo e(count($data)); ?> enseignant(s) concerné(s) | Total
            périodes (présence): <?php echo e(number_format(array_sum(array_column($data, 'periodes_presence')), 1)); ?> | Total
            périodes (absence): <?php echo e(number_format(array_sum(array_column($data, 'periodes_absence')), 1)); ?> |
            <strong>TOTAL
                GÉNÉRAL: <?php echo e(number_format(array_sum(array_column($data, 'periodes_totales')), 1)); ?> périodes</strong>
        </div>
        <table>
            <thead>
                <tr>
                    <th rowspan="2" style="width:3%">N°</th>
                    <th rowspan="2" style="width:18%">Nom</th>
                    <th rowspan="2" style="width:9%">Téléphone</th>
                    <th rowspan="2" style="width:8%">Matricule</th>
                    <th rowspan="2" style="width:10%">Spécialité</th>
                    <th colspan="3" class="retard-col">RETARDS</th>
                    <th colspan="2" class="anticip-col">ANTICIPATIONS</th>
                    <th colspan="2" class="absence-col">ABSENCES</th>
                    <th rowspan="2" class="total-col" style="width:7%">TOTAL<br>Périodes</th>
                    <th rowspan="2" class="assiduite-col">Taux<br>d'assiduité</th>
                </tr>
                <tr>
                    <th class="retard-col">Jours</th>
                    <th class="retard-col">Min.</th>
                    <th class="retard-col">Pér.</th>
                    <th class="anticip-col">Jours</th>
                    <th class="anticip-col">Min.</th>
                    <th class="absence-col">Jours</th>
                    <th class="absence-col">Pér.</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $data; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ens): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($loop->iteration); ?></td>
                        <td class="text-left bold"><?php echo e(strtoupper($ens['nom'])); ?></td>
                        <td><?php echo e($ens['tel'] ?? '-'); ?></td>
                        <td><?php echo e($ens['matricule'] ?? '-'); ?></td>
                        <td class="text-left"><?php echo e($ens['specialite'] ?? '-'); ?></td>
                        <td class="retard-col"><?php echo e($ens['nb_jours_retard']); ?></td>
                        <td class="retard-col"><?php echo e($ens['total_retard_minutes']); ?></td>
                        <td class="retard-col bold">
                            <?php echo e(number_format(max(0, ($ens['total_retard_minutes'] - 10) / 40), 1)); ?>

                        </td>
                        <td class="anticip-col"><?php echo e($ens['nb_jours_anticipation']); ?></td>
                        <td class="anticip-col"><?php echo e($ens['total_anticipation_minutes']); ?></td>
                        <td class="absence-col"><?php echo e($ens['nb_jours_absence']); ?></td>
                        <td class="absence-col bold"><?php echo e($ens['periodes_absence']); ?></td>
                        <td class="total-col"><?php echo e($ens['periodes_totales']); ?></td>
                        <td class="assiduite-col"><?php echo e(number_format($ens['taux_assiduite'], 1)); ?>%</td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
            <tfoot>
                <tr style="background:#e0e0e0;font-weight:bold">
                    <td colspan="5" class="text-left">TOTAUX</td>
                    <td class="retard-col"><?php echo e(array_sum(array_column($data, 'nb_jours_retard'))); ?></td>
                    <td class="retard-col"><?php echo e(array_sum(array_column($data, 'total_retard_minutes'))); ?></td>
                    <td class="retard-col">
                        <?php echo e(number_format(array_sum(array_map(fn($e) => max(0, ($e['total_retard_minutes'] - 10) / 40), $data)), 1)); ?>

                    </td>
                    <td class="anticip-col"><?php echo e(array_sum(array_column($data, 'nb_jours_anticipation'))); ?></td>
                    <td class="anticip-col"><?php echo e(array_sum(array_column($data, 'total_anticipation_minutes'))); ?></td>
                    <td class="absence-col"><?php echo e(array_sum(array_column($data, 'nb_jours_absence'))); ?></td>
                    <td class="absence-col"><?php echo e(number_format(array_sum(array_column($data, 'periodes_absence')), 1)); ?>

                    </td>
                    <td class="total-col"><?php echo e(number_format(array_sum(array_column($data, 'periodes_totales')), 1)); ?>

                    </td>
                    <td class="assiduite-col"></td>
                </tr>
            </tfoot>
        </table>
        <div style="margin-top:8px;font-size:7px"><strong>LÉGENDE:</strong> <span
                style="background:#fff3cd;padding:2px 4px;border:1px solid #000">RETARDS</span> = Arrivée en retard
            &nbsp; <span style="background:#cfe2ff;padding:2px 4px;border:1px solid #000">ANTICIPATIONS</span> = Départ
            anticipé &nbsp; <span style="background:#f8d7da;padding:2px 4px;border:1px solid #000">ABSENCES</span> =
            Absence complète &nbsp; <strong>Pér.</strong> = Périodes de 40 min à rattraper</div>
        <p style="font-size:7px"><strong>NOTE:</strong> Classement par ordre alphabétique des noms. Les jours signalés
            sont considérés comme valides pour le taux d'assiduité.</p>
    <?php endif; ?>
    <div style="margin-top:10px;text-align:right;font-size:8px">Fait à Meiganga, le
        <?php echo e(\Carbon\Carbon::now()->locale('fr')->translatedFormat('d F Y')); ?><br><br><br><br><strong>Le
            Proviseur</strong></div>
    <div class="footer">Document généré le <?php echo e(\Carbon\Carbon::now()->format('d/m/Y à H:i')); ?> | Auditron-X | LTMG
        <?php echo e(\Carbon\Carbon::now()->format('Y')); ?></div>
</body>

</html>
<?php /**PATH C:\laragon\www\auditron-qr\saas\api\resources\views/pdf/retards-cumule.blade.php ENDPATH**/ ?>