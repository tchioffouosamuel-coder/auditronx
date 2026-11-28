
<table class="grille">
    <thead>
        <tr>
            <th style="width:33%" class="g">MODULES</th>
            <th style="width:8%">Moyenne UEA(/<?php echo e($page['bareme_uea']); ?>)</th>
            <th style="width:9%">Moyenne de l'Épreuve Intégra (/<?php echo e($page['bareme_integratrice']); ?>)</th>
            <th style="width:8%">NOTE FINALE (/100)</th>
            <th style="width:6%">CRÉDIT</th>
            <th style="width:7%">MENTION</th>
            <th style="width:8%">SANCTION</th>
            <th style="width:15%">APPRÉCIATION</th>
            <th style="width:6%">SIGN.</th>
        </tr>
    </thead>
    <tbody>
        <?php $__currentLoopData = $page['blocs']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bloc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr class="bloc"><td colspan="9"><?php echo e($bloc['libelle']); ?></td></tr>
            <?php $__empty_1 = true; $__currentLoopData = $bloc['lignes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="<?php echo e($l['manquante'] ? 'manq' : ''); ?>">
                    <td><span class="b"><?php echo e($l['code']); ?></span> · <?php echo e($l['intitule']); ?></td>
                    <td class="c"><?php echo e($l['moyenne_uea']); ?></td>
                    <td class="c"><?php echo e($l['note_integratrice']); ?></td>
                    <td class="c b"><?php echo e($l['note_finale']); ?></td>
                    <td class="c"><?php echo e($l['credit']); ?></td>
                    <td class="c"><?php echo e($l['mention']); ?></td>
                    <td class="c <?php echo e($l['non_acquise'] ? 'rouge b' : ''); ?>"><?php echo e($l['sanction']); ?></td>
                    <td><?php echo e($l['appreciation']); ?></td>
                    <td class="c"><?php echo e($l['signataire']); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="9" class="i">Non couverts par le référentiel de formation <?php echo e($page['classe']['specialite_code']); ?> – sans objet</td></tr>
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <tr class="total">
            <td>Moyenne Générale des notes pondérées</td>
            <td></td>
            <td></td>
            <td class="c"><?php echo e($page['pied']['moyenne']); ?></td>
            <td class="c"><?php echo e($page['pied']['credits']); ?></td>
            <td class="c"><?php echo e($page['pied']['mention']); ?></td>
            <td class="c <?php echo e($page['pied']['defavorable'] ? 'rouge' : ''); ?>"><?php echo e($page['pied']['decision']); ?></td>
            <td colspan="2" class="normal"><?php echo e($page['pied']['competences']); ?></td>
        </tr>
        <tr>
            <td class="b">Seuil de réussite</td>
            <td colspan="8"><?php echo e($page['seuil']); ?></td>
        </tr>
    </tbody>
</table>
<?php /**PATH C:\laragon\www\auditron-qr\saas\api\resources\views/pdf/bulletin-trimestre.blade.php ENDPATH**/ ?>