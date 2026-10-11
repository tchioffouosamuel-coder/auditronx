<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Journal hebdomadaire des présences</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 8px;
            margin: 18px;
        }

        h1 {
            text-align: center;
            font-size: 15px;
            margin-bottom: 4px;
        }

        .periode {
            text-align: center;
            margin-bottom: 14px;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 3px 4px;
            text-align: center;
        }

        th {
            background: #e8edf2;
            font-size: 8px;
        }

        th.jour span {
            display: block;
            font-weight: normal;
            font-size: 7px;
        }

        td.nom,
        th.nom {
            text-align: left;
            width: 180px;
        }

        /* Absent à un jour attendu : c'est l'information que l'on cherche
           dans ce document, elle doit ressortir même en impression noir et
           blanc, d'où le fond gris en plus de la couleur. */
        td.absent {
            background: #f3d7d7;
            color: #8a1f1f;
            font-weight: bold;
        }

        td.hors {
            background: #f4f4f4;
            color: #999;
        }

        /* Jour férié : personne n'est attendu, la cellule ne doit pas se lire
           comme un oubli de pointage ni comme un jour sans cours. */
        td.ferie {
            background: #efe6c8;
            color: #6b5a14;
        }

        .legende {
            margin-top: 10px;
            font-size: 8px;
            color: #444;
        }

        .legende span {
            margin-right: 14px;
        }

        .empty {
            text-align: center;
            padding: 20px;
        }
    </style>
</head>

<body>
    <h1>Journal hebdomadaire des présences</h1>
    <div class="periode">
        Semaine du <?php echo e($debut->locale('fr')->translatedFormat('d F Y')); ?>

        au <?php echo e($fin->locale('fr')->translatedFormat('d F Y')); ?>

    </div>

    <?php if($lignes->isEmpty()): ?>
        <p class="empty">Aucun membre du personnel dans votre périmètre.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th class="nom">Personnel</th>
                    <th>Section</th>
                    <?php $__currentLoopData = $jours; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $jour): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <th class="jour">
                            <?php echo e(ucfirst($jour->locale('fr')->translatedFormat('D'))); ?>

                            <span><?php echo e($jour->format('d/m')); ?></span>
                        </th>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <th>Présences</th>
                    <th>Taux</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $lignes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ligne): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td class="nom"><?php echo e($ligne['nom']); ?></td>
                        <td><?php echo e($ligne['section'] ?? '—'); ?></td>
                        <?php $__currentLoopData = $ligne['jours']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cellule): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $classe = $cellule['ferie'] || $cellule['a_venir']
                                    ? ($cellule['ferie'] ? 'ferie' : 'hors')
                                    : (! $cellule['attendu']
                                        ? 'hors'
                                        : ($cellule['present'] ? '' : 'absent'));
                            ?>
                            <td class="<?php echo e($classe); ?>">
                                <?php if($cellule['present']): ?>
                                    <?php echo e($cellule['heure_arrivee'] ?? '—'); ?><br>
                                    <?php echo e($cellule['heure_depart'] ?? '—'); ?>

                                <?php elseif($cellule['ferie']): ?>
                                    FÉRIÉ
                                <?php elseif($cellule['a_venir']): ?>
                                    –
                                <?php elseif($cellule['attendu']): ?>
                                    ABS
                                <?php else: ?>
                                    ·
                                <?php endif; ?>
                            </td>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <td><?php echo e($ligne['jours_presents']); ?> / <?php echo e($ligne['jours_attendus']); ?></td>
                        <td><?php echo e($ligne['taux_assiduite'] === null ? '—' : $ligne['taux_assiduite'] . ' %'); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>

        <div class="legende">
            <span><strong>HH:MM / HH:MM</strong> arrivée et départ</span>
            <span><strong>ABS</strong> attendu, aucun pointage</span>
            <span><strong>FÉRIÉ</strong> jour férié, personne n'est attendue</span>
            <span><strong>·</strong> non attendu ce jour-là</span>
            <span><strong>–</strong> jour à venir, pas encore évalué</span>
            <span><strong>—</strong> taux indisponible : aucun jour attendu, emploi du temps probablement absent</span>
        </div>
        <p><?php echo e($lignes->count()); ?> membre(s) du personnel</p>
    <?php endif; ?>
</body>

</html>
<?php /**PATH C:\laragon\www\auditron-qr\saas\api\resources\views/pdf/journal-hebdomadaire.blade.php ENDPATH**/ ?>