
<h1><?php echo e($page['titre']); ?></h1>

<table class="identite">
    <tr>
        <td colspan="2"><span class="b">SPÉCIALITÉ :</span> <?php echo e($page['classe']['specialite']); ?></td>
        <td colspan="2" class="r" style="white-space:nowrap"><span class="b">Année Scolaire :</span> <?php echo e($page['annee']); ?></td>
    </tr>
    <tr>
        <td style="width:42%"><span class="b">Nom de l'Élève :</span> <u><?php echo e($page['eleve']['nom_complet']); ?></u></td>
        <td style="width:22%"><span class="b">Classe :</span> <u><?php echo e($page['classe']['nom'] ?: $page['classe']['code']); ?></u></td>
        <td style="width:22%"><span class="b">Matricule :</span> <u><?php echo e($page['eleve']['matricule']); ?></u></td>
        <td style="width:14%" class="r"><span class="b">Effectif :</span> <u><?php echo e($page['effectif']); ?></u></td>
    </tr>
    <tr>
        <td colspan="4">
            <span class="b">Né(e) le :</span> <u><?php echo e($page['eleve']['date_naissance']); ?></u>
            <span class="b">à</span> <u><?php echo e($page['eleve']['lieu_naissance']); ?></u>
            &nbsp;<span class="b">Sexe</span> <u><?php echo e($page['eleve']['sexe']); ?></u>
            &nbsp;<span class="b">Statut :</span>
            <?php $__currentLoopData = $page['statuts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $valeur => $libelle): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if(! $loop->first): ?> / <?php endif; ?>
                <?php if($page['statut'] === $valeur): ?><u><?php echo e($libelle); ?></u><?php else: ?><?php echo e($libelle); ?><?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </td>
    </tr>
</table>

<h2>I. ÉVALUATIONS ET MAÎTRISE DES COMPÉTENCES</h2>
<?php /**PATH C:\laragon\www\auditron-qr\saas\api\resources\views/pdf/partials/bulletin-identite.blade.php ENDPATH**/ ?>