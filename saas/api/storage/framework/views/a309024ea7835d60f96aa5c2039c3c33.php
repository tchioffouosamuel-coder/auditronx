<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Enseignants sans présence</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 24px;
        }

        h1 {
            text-align: center;
            font-size: 16px;
            margin-bottom: 4px;
        }

        .date {
            text-align: center;
            margin-bottom: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 6px 8px;
            text-align: left;
        }

        th {
            background: #e8edf2;
        }

        .empty {
            text-align: center;
            padding: 20px;
        }
    </style>
</head>

<body>
    <h1>Enseignants sans aucune présence enregistrée</h1>
    <div class="date">Situation au <?php echo e($date->locale('fr')->translatedFormat('l d F Y')); ?></div>
    <?php if($enseignants->isEmpty()): ?>
        <p class="empty">Tous les enseignants ont au moins une présence enregistrée.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Nom</th>
                    <th>Matricule</th>
                    <th>Section</th>
                    <th>Fonction</th>
                    <th>Téléphone</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $enseignants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $enseignant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($loop->iteration); ?></td>
                        <td><?php echo e($enseignant->nom); ?></td>
                        <td><?php echo e($enseignant->matricule ?? '—'); ?></td>
                        <td><?php echo e($enseignant->section ?? '—'); ?></td>
                        <td><?php echo e($enseignant->fonction ?? '—'); ?></td>
                        <td><?php echo e($enseignant->tel ?? '—'); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
        <p><?php echo e($enseignants->count()); ?> enseignant(s) sans aucune présence</p>
    <?php endif; ?>
</body>

</html>
<?php /**PATH C:\laragon\www\auditron-qr\saas\api\resources\views/pdf/sans-presence.blade.php ENDPATH**/ ?>