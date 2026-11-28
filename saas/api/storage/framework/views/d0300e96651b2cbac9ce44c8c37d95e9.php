
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Bulletins de compétences</title>
    <style>
        @page { margin: 9mm 9mm 8mm 9mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8.6px; color: #111; }
        .page { position: relative; }
        .saut { page-break-after: always; }
        table { width: 100%; border-collapse: collapse; }
        .grille th, .grille td { border: 0.6px solid #333; padding: 2.6px 2.5px; }
        .grille th { background: #e4e8ee; font-size: 7.6px; text-align: center; }
        .c { text-align: center; }
        .r { text-align: right; }
        .b { font-weight: bold; }
        .bloc td { background: #f1f3f6; font-weight: bold; font-size: 8px; }
        .total td { background: #e4e8ee; font-weight: bold; }
        .rouge { color: #b00020; }
        .manq { background: #fff1c2; }
        h1 { font-size: 13px; text-align: center; margin: 2px 0 1px; letter-spacing: .3px; }
        .sous-titre { text-align: center; font-size: 8.5px; margin-bottom: 4px; }
        .identite td { border: none; padding: 1.5px 2px; font-size: 9px; }
        .cadre { border: 0.6px solid #333; padding: 3px 4px; vertical-align: top; }
        .case { font-size: 9px; }
        .legende td { border: none; padding: 0 4px 0 0; font-size: 7.2px; }
        .signatures td { border: none; text-align: center; font-size: 8.6px; height: 48px; vertical-align: top; }
        .filigrane {
            position: absolute; top: 330px; left: 40px; width: 620px;
            text-align: center; font-size: 64px; font-weight: bold;
            color: #c8102e; opacity: 0.13; transform: rotate(-35deg);
        }
    </style>
</head>

<body>
    <?php $__currentLoopData = $pages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $page): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="page <?php echo e($loop->last ? '' : 'saut'); ?>">
            <?php if($page['filigrane']): ?>
                <div class="filigrane"><?php echo e($page['filigrane']); ?></div>
            <?php endif; ?>

            <?php echo $__env->make('pdf.partials.entete', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('pdf.partials.bulletin-identite', ['page' => $page], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <?php if($page['annuel']): ?>
                <?php echo $__env->make('pdf.bulletin-annuel', ['page' => $page], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php else: ?>
                <?php echo $__env->make('pdf.bulletin-trimestre', ['page' => $page], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php endif; ?>

            <?php echo $__env->make('pdf.partials.bulletin-synthese', ['page' => $page], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</body>

</html>
<?php /**PATH C:\laragon\www\auditron-qr\saas\api\resources\views/pdf/bulletins.blade.php ENDPATH**/ ?>