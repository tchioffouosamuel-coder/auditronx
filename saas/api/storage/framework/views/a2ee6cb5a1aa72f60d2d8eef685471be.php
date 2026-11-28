
<?php ($s = $page['synthese']); ?>
<?php ($case = fn (bool $cochee) => $cochee ? '☒' : '☐'); ?>

<table style="margin-top:5px">
    <tr>
        <td class="cadre" style="width:34%">
            <div class="b" style="margin-bottom:2px">SYNTHÈSE</div>
            Moyenne générale : <span class="b"><?php echo e($s['moyenne']); ?> / 100</span><br>
            Mention : <span class="b"><?php echo e($s['mention'] ?? '—'); ?></span>
            <?php if($s['appreciation']): ?> (<?php echo e($s['appreciation']); ?>) <?php endif; ?><br>
            Rang : <span class="b"><?php echo e($s['rang']); ?></span> sur <?php echo e($s['effectif_classe']); ?><br>
            Compétences non validées : <?php echo e($s['competences_non_validees']); ?><br>
            Moyenne de la classe : <?php echo e($s['moyenne_classe']); ?><br>
            Plus forte / plus faible : <?php echo e($s['moyenne_max']); ?> / <?php echo e($s['moyenne_min']); ?>

        </td>
        <td class="cadre" style="width:33%">
            <div class="b" style="margin-bottom:2px">POSITION PAR RAPPORT AU GROUPE</div>
            <?php $__currentLoopData = $s['positions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $valeur => $libelle): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <span class="case"><?php echo e($case($s['position'] === $valeur)); ?></span> <?php echo e($libelle); ?><br>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <div class="b" style="margin:3px 0 2px">VALIDATION DES COMPÉTENCES</div>
            <span class="case"><?php echo e($case($s['validation'] === \App\Services\BaremeApc::VALIDATION_OUI)); ?></span> OUI
            &nbsp;&nbsp;
            <span class="case"><?php echo e($case($s['validation'] === \App\Services\BaremeApc::VALIDATION_NON_PARTIEL)); ?></span> NON / PARTIEL
        </td>
        <td class="cadre" style="width:33%">
            <div class="b" style="margin-bottom:2px">DÉCISION DU CONSEIL DE CLASSE</div>
            <?php $__currentLoopData = $s['decisions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $valeur => $libelle): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <span class="case"><?php echo e($case($s['decision'] === $valeur)); ?></span> <?php echo e($libelle); ?><br>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <div class="b" style="margin:3px 0 1px">Observation :</div>
            <?php echo e($s['observation']); ?>

        </td>
    </tr>
</table>

<table class="legende" style="margin-top:3px">
    <tr>
        <td class="b">Légende :</td>
        <?php $__currentLoopData = $page['legende']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <td><?php echo e($m['lettre']); ?> <?php echo e($m['borne']); ?> : <?php echo e($m['appreciation']); ?></td>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <td>S : compétence validée · E : non acquise</td>
    </tr>
</table>

<table class="signatures" style="margin-top:6px">
    <tr>
        <td style="width:25%">Le Parent</td>
        <td style="width:25%">Le Professeur principal</td>
        <td style="width:25%">Le Censeur</td>
        <td style="width:25%">
            Le Chef d'établissement
            <?php if($page['valide_le']): ?>
                <br><span style="font-size:6.6px">Conseil du <?php echo e($page['valide_le']); ?></span>
            <?php endif; ?>
        </td>
    </tr>
</table>
<?php /**PATH C:\laragon\www\auditron-qr\saas\api\resources\views/pdf/partials/bulletin-synthese.blade.php ENDPATH**/ ?>