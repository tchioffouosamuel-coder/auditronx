<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Liste du personnel</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 24px;
        }

        h1 {
            text-align: center;
            font-size: 16px;
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
    <h1>Liste du personnel</h1>
    <?php if($personnel->isEmpty()): ?>
        <p class="empty">Aucun personnel enregistré.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Matricule</th>
                    <th>Fonction</th>
                    <th>Section</th>
                    <th>Grade</th>
                    <th>Téléphone</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $personnel; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $enseignant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($enseignant->nom); ?></td>
                        <td><?php echo e($enseignant->matricule); ?></td>
                        <td><?php echo e($enseignant->fonction ?? '—'); ?></td>
                        <td><?php echo e($enseignant->section ?? '—'); ?></td>
                        <td><?php echo e($enseignant->grade ?? '—'); ?></td>
                        <td><?php echo e($enseignant->tel ?? '—'); ?></td>
                        <td><?php echo e($enseignant->email ?? '—'); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>

</html>
<?php /**PATH C:\laragon\www\auditron-qr\saas\api\resources\views/pdf/personnel.blade.php ENDPATH**/ ?>