
<table class="grille">
    <thead>
        <tr>
            <th style="width:6%">CODE</th>
            <th style="width:29%">MODULES</th>
            <th style="width:5%">CRÉD.</th>
            <th style="width:5%">VH</th>
            <th style="width:6%">UEA<br>/<?php echo e($page['bareme_uea'] ?? 60); ?></th>
            <th style="width:6%">ÉPR. INT.<br>/<?php echo e($page['bareme_integratrice'] ?? 40); ?></th>
            <th style="width:7%">NOTE<br>/100</th>
            <th style="width:6%">MENTION</th>
            <th style="width:6%">SANCT.</th>
            <th style="width:18%">APPRÉCIATION</th>
            <th style="width:6%">SIGN.</th>
        </tr>
    </thead>
    <tbody>
        <?php $__currentLoopData = $page['blocs']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bloc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr class="bloc"><td colspan="11"><?php echo e($bloc['libelle']); ?></td></tr>
            <?php $__currentLoopData = $bloc['lignes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="<?php echo e($l['manquante'] ? 'manq' : ''); ?>">
                    <td class="c"><?php echo e($l['code']); ?></td>
                    <td><?php echo e($l['intitule']); ?></td>
                    <td class="c"><?php echo e($l['credit']); ?></td>
                    <td class="c"><?php echo e($l['volume_horaire']); ?></td>
                    <td class="c"><?php echo e($l['moyenne_uea']); ?></td>
                    <td class="c"><?php echo e($l['note_integratrice']); ?></td>
                    <td class="c b <?php echo e($l['sous_seuil'] ? 'rouge' : ''); ?>"><?php echo e($l['note_finale']); ?></td>
                    <td class="c"><?php echo e($l['mention']); ?></td>
                    <td class="c"><?php echo e($l['sanction']); ?></td>
                    <td><?php echo e($l['appreciation']); ?></td>
                    <td class="c"><?php echo e($l['signataire']); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <tr class="total">
            <td colspan="2" class="r">TOTAUX / MOYENNE GÉNÉRALE</td>
            <td class="c"><?php echo e($page['totaux']['credits']); ?></td>
            <td class="c"><?php echo e($page['totaux']['volume_horaire']); ?></td>
            <td colspan="2"></td>
            <td class="c"><?php echo e($page['totaux']['moyenne']); ?></td>
            <td class="c"><?php echo e($page['totaux']['mention']); ?></td>
            <td colspan="3"></td>
        </tr>
    </tbody>
</table>
<?php /**PATH C:\laragon\www\auditron-qr\saas\api\resources\views/pdf/bulletin-trimestre.blade.php ENDPATH**/ ?>