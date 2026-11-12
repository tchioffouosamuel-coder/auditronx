<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture <?php echo e($facture->numero); ?></title>
    <style>
        body { color: #172b25; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .header { border-bottom: 2px solid #0f6e49; margin-bottom: 28px; padding-bottom: 14px; }
        .brand { color: #0f6e49; font-size: 22px; font-weight: bold; }
        .muted { color: #64748b; }
        .grid { width: 100%; margin: 24px 0; }
        .grid td { vertical-align: top; width: 50%; }
        table.lines { border-collapse: collapse; margin-top: 32px; width: 100%; }
        .lines th, .lines td { border-bottom: 1px solid #dbe4df; padding: 12px 8px; text-align: left; }
        .lines th { background: #f1f5f3; }
        .amount { font-size: 16px; font-weight: bold; text-align: right !important; }
        .footer { border-top: 1px solid #dbe4df; color: #64748b; margin-top: 48px; padding-top: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">Auditron</div>
        <div class="muted">Facture <?php echo e($facture->numero); ?></div>
    </div>

    <table class="grid">
        <tr>
            <td>
                <strong>Facturé à</strong><br>
                <?php echo e($facture->etablissement->nom); ?><br>
                <?php echo e($facture->etablissement->ville); ?><br>
                <?php echo e($facture->etablissement->contact_email); ?>

            </td>
            <td style="text-align: right">
                <strong>Date d’émission :</strong> <?php echo e($facture->emise_le?->format('d/m/Y') ?? '—'); ?><br>
                <strong>Date d’échéance :</strong> <?php echo e($facture->echeance_le?->format('d/m/Y') ?? '—'); ?><br>
                <strong>Statut :</strong> <?php echo e(ucfirst($facture->statut)); ?>

            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Description</th>
                <th>Montant</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $facture->lignes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ligne): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($ligne->libelle); ?></td>
                    <td><?php echo e(number_format((float) $ligne->montant, 0, ',', ' ')); ?> <?php echo e($facture->devise); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td class="amount">Total</td>
                <td class="amount"><?php echo e(number_format((float) $facture->montant, 0, ',', ' ')); ?> <?php echo e($facture->devise); ?></td>
            </tr>
        </tbody>
    </table>

    <?php if($facture->moyen_paiement || $facture->reference_paiement): ?>
        <p>
            <strong>Paiement :</strong> <?php echo e($facture->moyen_paiement ?? '—'); ?>

            <?php if($facture->reference_paiement): ?> · Référence <?php echo e($facture->reference_paiement); ?> <?php endif; ?>
            <?php if($facture->payee_le): ?> · Reçu le <?php echo e($facture->payee_le->format('d/m/Y')); ?> <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if($facture->notes): ?>
        <p><strong>Note :</strong> <?php echo e($facture->notes); ?></p>
    <?php endif; ?>

    <div class="footer">Document généré par Auditron.</div>
</body>
</html>
<?php /**PATH C:\laragon\www\auditron-qr\saas\api\resources\views/pdf/facture-plateforme.blade.php ENDPATH**/ ?>