
<?php ($entete = app(\App\Services\EnteteEtablissement::class)->colonnes()); ?>

<?php if($entete['gauche'] || $entete['droite'] || $entete['logo']): ?>
    <table style="border:none;margin-bottom:8px">
        <tr>
            <td style="border:none;width:40%;text-align:center;font-size:10px">
                <?php echo implode('<br>', array_map('e', $entete['gauche'])); ?>

            </td>
            <td style="border:none;width:20%;text-align:center">
                
                <?php if($entete['logo']): ?>
                    <img src="<?php echo e($entete['logo']); ?>" alt="Logo" style="height:48px">
                <?php endif; ?>
            </td>
            <td style="border:none;width:40%;text-align:center;font-size:10px">
                <?php echo implode('<br>', array_map('e', $entete['droite'])); ?>

            </td>
        </tr>
    </table>
    <?php if($entete['contact'] !== ''): ?>
        <div style="text-align:center;font-size:9px;margin-bottom:6px"><?php echo e($entete['contact']); ?></div>
    <?php endif; ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\auditron-qr\saas\api\resources\views/pdf/partials/entete.blade.php ENDPATH**/ ?>