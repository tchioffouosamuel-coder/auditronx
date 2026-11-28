
<h1><?php echo e($page['titre']); ?></h1>
<div class="sous-titre">Année Scolaire : <?php echo e($page['annee']); ?></div>

<table class="identite" style="margin-bottom:4px">
    <tr>
        <td style="width:50%">Nom de l'Élève : <span class="b"><?php echo e($page['eleve']['nom_complet']); ?></span></td>
        <td style="width:25%">Matricule : <span class="b"><?php echo e($page['eleve']['matricule']); ?></span></td>
        <td style="width:25%">Sexe : <?php echo e($page['eleve']['sexe']); ?></td>
    </tr>
    <tr>
        <td>Né(e) le : <?php echo e($page['eleve']['naissance']); ?></td>
        <td>Classe : <span class="b"><?php echo e($page['classe']['nom'] ?: $page['classe']['code']); ?></span></td>
        <td>Effectif : <?php echo e($page['effectif']); ?></td>
    </tr>
    <tr>
        <td>Spécialité : <?php echo e($page['classe']['specialite']); ?></td>
        <td colspan="2">Statut : <?php echo e($page['eleve']['statut']); ?></td>
    </tr>
</table>
<?php /**PATH C:\laragon\www\auditron-qr\saas\api\resources\views/pdf/partials/bulletin-identite.blade.php ENDPATH**/ ?>