
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title><?php echo e($titre); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 10px
        }

        h1 {
            text-align: center;
            font-size: 17px;
            margin: 5px 0;
            text-transform: uppercase
        }

        h2 {
            font-size: 12px;
            margin: 12px 0 4px
        }

        .sous-titre {
            text-align: center;
            font-size: 12px;
            margin-bottom: 8px
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0
        }

        th,
        td {
            border: 1px solid #000;
            padding: 3px 4px;
            text-align: center;
            font-size: 10px
        }

        th {
            background: #e0e0e0
        }

        .text-left {
            text-align: left
        }

        .total {
            background: #d4edda;
            font-weight: bold
        }

        .note {
            margin-top: 8px;
            font-size: 9px;
            color: #555
        }

        .vide {
            text-align: center;
            font-size: 10px;
            color: #555;
            padding: 8px
        }
    </style>
</head>

<body>
    <?php echo $__env->make('pdf.partials.entete', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <h1><?php echo e($titre); ?></h1>
    <div class="sous-titre"><?php echo e($categorie); ?> — <?php echo e($bilan['libelle']); ?></div>

    <?php if($volet !== 'ponctualite'): ?>
        <?php $__empty_1 = true; $__currentLoopData = $bilan['sections']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <h2>
                <?php echo e($section['section']); ?> — <?php echo e($section['effectif']); ?>

                personne<?php echo e($section['effectif'] > 1 ? 's' : ''); ?> · taux moyen <?php echo e($section['taux_assiduite']); ?> %
            </h2>
            <table>
                <thead>
                    <tr>
                        <th class="text-left">Personnel</th>
                        <th>Matricule</th>
                        <th>Fonction</th>
                        <th>Jours attendus</th>
                        <th>Jours assurés</th>
                        <th>Signalements</th>
                        <th>Taux</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $section['lignes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ligne): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td class="text-left"><?php echo e($ligne['nom']); ?></td>
                            <td><?php echo e($ligne['matricule'] ?? '—'); ?></td>
                            <td><?php echo e($ligne['fonction'] ?? '—'); ?></td>
                            <td><?php echo e($ligne['jours_attendus']); ?></td>
                            <td><?php echo e($ligne['jours_presents']); ?></td>
                            <td><?php echo e($ligne['jours_signales']); ?></td>
                            <td><?php echo e($ligne['taux_assiduite']); ?> %</td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="vide">Aucun personnel dans le périmètre pour ce mois.</div>
        <?php endif; ?>

        <?php if($bilan['sections']): ?>
            <h2>Synthèse par section</h2>
            <table>
                <thead>
                    <tr>
                        <th class="text-left">Section</th>
                        <th>Effectif</th>
                        <th>Jours attendus</th>
                        <th>Jours assurés</th>
                        <th>Taux</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $bilan['sections']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td class="text-left"><?php echo e($section['section']); ?></td>
                            <td><?php echo e($section['effectif']); ?></td>
                            <td><?php echo e($section['jours_attendus']); ?></td>
                            <td><?php echo e($section['jours_presents']); ?></td>
                            <td><?php echo e($section['taux_assiduite']); ?> %</td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <tr class="total">
                        <td class="text-left">TOTAL</td>
                        <td><?php echo e($bilan['total']['effectif']); ?></td>
                        <td><?php echo e($bilan['total']['jours_attendus']); ?></td>
                        <td><?php echo e($bilan['total']['jours_presents']); ?></td>
                        <td><?php echo e($bilan['total']['taux_assiduite']); ?> %</td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <?php if($volet !== 'assiduite'): ?>
        
        <?php if($volet !== 'ponctualite'): ?>
            <h2>Classement de ponctualité</h2>
        <?php endif; ?>
        <?php if($bilan['ponctualite']): ?>
            <table>
                <thead>
                    <tr>
                        <th>Rang</th>
                        <th class="text-left">Personnel</th>
                        <th>Matricule</th>
                        <th>Section</th>
                        <th>Jours prévus</th>
                        <th>Jours ponctuels</th>
                        <th>Retards</th>
                        <th>Minutes</th>
                        <th>Taux</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $bilan['ponctualite']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rang => $ligne): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td><?php echo e($rang + 1); ?></td>
                            <td class="text-left"><?php echo e($ligne['nom']); ?></td>
                            <td><?php echo e($ligne['matricule'] ?? '—'); ?></td>
                            <td><?php echo e($ligne['section'] ?? '—'); ?></td>
                            <td><?php echo e($ligne['jours_prevus']); ?></td>
                            <td><?php echo e($ligne['jours_ponctuels']); ?></td>
                            <td><?php echo e($ligne['nb_jours_retard']); ?></td>
                            <td><?php echo e($ligne['total_retard_minutes']); ?></td>
                            <td><?php echo e($ligne['taux_ponctualite']); ?> %</td>
                            <td><?php echo e($ligne['score_ponctualite']); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
            <div class="note">
                Le score pondère le taux de ponctualité par les minutes de retard cumulées : à nombre de jours
                ponctuels égal, les retards les plus longs pèsent le plus.
            </div>
        <?php else: ?>
            <div class="vide">Aucun personnel dans le périmètre pour ce mois.</div>
        <?php endif; ?>
    <?php endif; ?>
</body>

</html>
<?php /**PATH C:\laragon\www\auditron-qr\saas\api\resources\views/pdf/bilan-mensuel.blade.php ENDPATH**/ ?>