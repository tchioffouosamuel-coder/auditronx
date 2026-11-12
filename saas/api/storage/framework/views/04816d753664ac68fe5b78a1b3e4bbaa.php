<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Fiche de contrôle</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 10px
        }

        h1 {
            text-align: center;
            font-size: 16px;
            margin: 5px 0;
            text-transform: uppercase
        }

        h2 {
            font-size: 12px;
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
            font-size: 12px
        }

        td {
            font-size: 12px
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
            font-size: 9px;
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 3px
        }
    </style>
</head>

<body>
    <?php echo $__env->make('pdf.partials.entete', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <h1>Fiche de contrôle d'assiduité et
        ponctualité<br><?php echo e(\Carbon\Carbon::createFromFormat('m', $mois)->locale('fr')->translatedFormat('F')); ?>

        <?php echo e($annee); ?></h1>
    <h2>ENSEIGNANT</h2>
    <table class="info-table">
        <tr>
            <th>Nom</th>
            <td class="bold"><?php echo e(strtoupper($enseignant->nom)); ?></td>
            <th>Téléphone</th>
            <td><?php echo e($enseignant->tel ?? '-'); ?></td>
            <th>Matricule</th>
            <td><?php echo e($enseignant->matricule ?? '-'); ?></td>
        </tr>
    </table>
    <?php if(!empty($horaire_special)): ?>
        <h2>EMPLOI DU TEMPS SPÉCIAL</h2>
        <table>
            <tr>
                <th style="width:12%">Jour</th>
                <th>Horaires</th>
            </tr>
            <?php $__currentLoopData = $emploi_special; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $plage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td class="bold"><?php echo e([1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'][$plage->jour] ?? '-'); ?></td>
                    <td><?php echo e($plage->heure_debut); ?> - <?php echo e($plage->heure_fin); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </table>
    <?php elseif(!empty($horaire_administratif)): ?>
        <h2>HORAIRE DE TRAVAIL (PERSONNEL ADMINISTRATIF)</h2>
        <table>
            <tr>
                <th style="width:12%">Jour</th>
                <th>Horaires</th>
            </tr>
            <?php $__currentLoopData = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $numero => $jour): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if(in_array($numero, $horaire_administratif['jours'], true)): ?>
                    <tr>
                        <td class="bold"><?php echo e($jour); ?></td>
                        <td><?php echo e($horaire_administratif['heure_debut']); ?> - <?php echo e($horaire_administratif['heure_fin']); ?></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </table>
    <?php else: ?>
    <h2>EMPLOI DU TEMPS HEBDOMADAIRE</h2>
    <table>
        <tr>
            <th style="width:12%">Jour</th>
            <th>Horaires</th>
            <th>Discipline</th>
            <th>Classe</th>
        </tr>
        <?php $__currentLoopData = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $jour): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if(isset($emploi_par_jour[$jour])): ?>
                <?php $__currentLoopData = $emploi_par_jour[$jour]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $emp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <?php if($key == 0): ?>
                            <td rowspan="<?php echo e($emploi_par_jour[$jour]->count()); ?>" class="bold"><?php echo e($jour); ?></td>
                        <?php endif; ?>
                        <td>
                            <?php echo e($emp->heure_debut); ?> - <?php echo e($emp->heure_fin); ?></td>
                        <td class="text-left"><?php echo e($emp->discipline->nom ?? '-'); ?></td>
                        <td><?php echo e($emp->classe->nom ?? '-'); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </table>
    <?php endif; ?>
    <h2>DÉTAIL MENSUEL DES PRÉSENCES</h2>
    <table>
        <tr>
            <th style="width:8%">Date</th>
            <th style="width:8%">Jour</th>
            <th style="width:7%">Cours</th>
            <th>Prévu début</th>
            <th>Prévu fin</th>
            <th>Scan 1</th>
            <th>Scan 2</th>
            <th>Retard (min)</th>
            <th>Anticip. (min)</th>
            <th>Périodes à rattraper</th>
            <th>Statut</th>
        </tr>
        <?php $__currentLoopData = $details; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr class="<?php echo e($d['absent'] && !$d['est_signale'] ? 'absent' : ''); ?>">
                <td><?php echo e($d['date']); ?></td>
                <td><?php echo e($d['jour']); ?></td>
                <td><?php echo e($d['nb_cours']); ?></td>
                <td><?php echo e($d['heure_debut_prevue']); ?></td>
                <td><?php echo e($d['heure_fin_prevue']); ?></td>
                <?php if($d['futur']): ?>
                    <td colspan="5" class="center">-</td>
                    <td>A venir</td>
                <?php elseif($d['est_signale']): ?>
                    <td colspan="3" class="center bold" style="color:#0666cc">SIGNALÉ</td>
                    <td>0</td>
                    <td class="bold">0.00</td>
                    <td style="color:#0666cc">Signalé (<?php echo e(ucfirst($d['type_signalement'] ?? 'Excuse')); ?>)</td>
                <?php elseif($d['absent']): ?>
                    <td colspan="3" class="center bold">AUCUN POINTAGE</td>
                    <td>-</td>
                    <td class="bold"><?php echo e(number_format($d['periodes_a_rattraper'], 2, '.', '')); ?></td>
                    <td>Aucun pointage</td>
                <?php elseif($d['heure_arrivee'] === null && $d['heure_depart'] !== null): ?>
                    <td>-</td>
                    <td><?php echo e($d['heure_depart']); ?></td>
                    <td>-</td>
                    <td><?php echo e(round($d['anticipation_minutes'])); ?></td>
                    <td class="bold"><?php echo e(number_format($d['periodes_a_rattraper'], 2, '.', '')); ?></td>
                <td>Sortie seule</td><?php else: ?><td><?php echo e($d['heure_arrivee']); ?></td>
                    <td><?php echo e($d['heure_depart'] ?? '-'); ?></td>
                    <td><?php echo e($d['retard_minutes'] !== null ? round($d['retard_minutes']) : '-'); ?></td>
                    <td><?php echo e(round($d['anticipation_minutes'])); ?></td>
                    <td class="bold"><?php echo e(number_format($d['periodes_a_rattraper'], 2, '.', '')); ?></td>
                    <td>
                        <?php if($d['retard_minutes'] == 0 && $d['anticipation_minutes'] == 0): ?>
                            Ponctuel
                        <?php elseif($d['retard_minutes'] > 0 && $d['anticipation_minutes'] > 0): ?>
                            Retard+Anticip.
                        <?php elseif($d['retard_minutes'] > 0): ?>
                            Retard
                        <?php elseif($d['anticipation_minutes'] > 0): ?>
                            Anticipation
                        <?php else: ?>
                            Présent
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
            <td><?php echo e(round($total_retard_minutes)); ?></td>
            <td class="text-left bold"><?php echo e(!empty($horaire_administratif) || !empty($horaire_special) ? 'Jours de travail prévus' : 'Jours de cours prévus'); ?></td>
            <td><?php echo e($jours_attendus); ?></td>
        </tr>
        <tr>
            <td class="text-left bold">Total anticipation (min)</td>
            <td><?php echo e(round($total_anticipation_minutes)); ?></td>
            <td class="text-left bold">Présences validées</td>
            <td><?php echo e($presences_valides); ?></td>
        </tr>
        <?php if(!empty($horaire_special)): ?>
            
            <tr>
                <td class="text-left bold">Périodes à rattraper</td>
                <td class="bold">Sans objet</td>
                <td class="text-left bold">Taux d'assiduité</td>
                <td class="bold"><?php echo e(number_format($taux_assiduite, 1, '.', '')); ?>%</td>
            </tr>
        <?php else: ?>
            <tr>
                <td class="text-left bold">Périodes à rattraper (présence)</td>
                <td class="bold"><?php echo e(number_format($total_periodes_a_rattraper, 2, '.', '')); ?></td>
                <td class="text-left bold">Taux d'assiduité</td>
                <td class="bold"><?php echo e(number_format($taux_assiduite, 1, '.', '')); ?>%</td>
            </tr>
            <tr>
                <td class="text-left bold">Périodes à rattraper (aucun pointage)</td>
                <td class="bold"><?php echo e(number_format($total_periodes_absence, 2, '.', '')); ?></td>
                <td class="text-left bold">TOTAL périodes à rattraper</td>
                <td class="bold"><?php echo e(number_format($total_periodes_a_rattraper + $total_periodes_absence, 2, '.', '')); ?></td>
            </tr>
        <?php endif; ?>
        <tr>
            <td class="text-left bold">Jours ponctuels</td>
            <td><?php echo e($jours_ponctuels); ?> / <?php echo e($jours_prevus); ?></td>
            <td class="text-left bold">Taux de ponctualité</td>
            <td class="bold"><?php echo e(number_format($taux_ponctualite, 1, '.', '')); ?>%</td>
        </tr>
    </table>
    <div style="margin-top:10px;text-align:right;font-size:11px">Fait à Meiganga, le
        <?php echo e(\Carbon\Carbon::now()->locale('fr')->translatedFormat('d F Y')); ?><br><br><br><br><strong>Le
            Proviseur</strong></div>
    <div class="footer">Document généré le <?php echo e(\Carbon\Carbon::now()->format('d/m/Y à H:i')); ?> | Auditron-X | LTMG
        <?php echo e(\Carbon\Carbon::now()->format('Y')); ?></div>
</body>

</html>
<?php /**PATH C:\laragon\www\auditron-qr\saas\api\resources\views/pdf/retards-individuel.blade.php ENDPATH**/ ?>