
<?php ($s = $page['synthese']); ?>
<?php ($case = fn (bool $cochee) => $cochee ? '[X]' : '[ ]'); ?>

<h2>II. SYNTHÈSE DES RÉSULTATS</h2>
<div class="ligne">
    <span class="b">Moyenne de la classe :</span> <?php echo e($s['moyenne_classe']); ?> / 100 |
    <span class="b">Moyenne la plus forte :</span> <?php echo e($s['moyenne_max']); ?> / 100 |
    <span class="b">Moyenne la plus faible :</span> <?php echo e($s['moyenne_min']); ?> / 100 |
    <span class="b">Moyenne de l'élève :</span> <?php echo e($s['moyenne']); ?> / 100
</div>
<div class="ligne">
    <span class="b">Position par rapport au groupe :</span>
    <?php $__currentLoopData = $s['positions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $valeur => $libelle): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if(! $loop->first): ?> | <?php endif; ?> <?php echo e($case($s['position'] === $valeur)); ?> <?php echo e($libelle); ?>

    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<table class="legende">
    <tr>
        <td class="b" style="width:11%">Légende APC :</td>
        <td>
            <?php $__currentLoopData = $page['legende']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mention): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <span style="margin-right:9px;white-space:nowrap"><?php echo e($mention); ?></span>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </td>
    </tr>
</table>

<h2>III. DÉCISION DU CONSEIL DE CLASSE</h2>
<div class="ligne">
    <span class="b">Validation des compétences professionnelles :</span>
    <?php echo e($case($s['validation'] === \App\Services\BaremeApc::VALIDATION_OUI)); ?> OUI
    <?php echo e($case($s['validation'] === \App\Services\BaremeApc::VALIDATION_NON_PARTIEL)); ?> NON / PARTIEL
</div>
<div class="ligne">
    <span class="b">DÉCISION GLOBALE :</span>
    <?php $__currentLoopData = $s['decisions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $valeur => $libelle): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php echo e($case($s['decision'] === $valeur)); ?> <span class="rouge b"><?php echo e($libelle); ?></span>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<div class="ligne"><span class="b">Observation :</span> <?php echo e($s['observation']); ?></div>

<table class="signatures">
    <tr>
        <td style="width:25%">Le Parent</td>
        <td style="width:25%">Le Professeur principal</td>
        <td style="width:25%">Le Censeur</td>
        <td style="width:25%">
            Le Chef d'établissement
            <?php if($page['valide_le']): ?>
                <br><span style="font-size:7px">Conseil du <?php echo e($page['valide_le']); ?></span>
            <?php endif; ?>
        </td>
    </tr>
</table>
<?php /**PATH C:\laragon\www\auditron-qr\saas\api\resources\views/pdf/partials/bulletin-synthese.blade.php ENDPATH**/ ?>