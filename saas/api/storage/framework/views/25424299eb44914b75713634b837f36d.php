
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title><?php echo e($titre); ?></title>
    <style>
        @page { margin: 9mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8px; color: #111; }
        table { width: 100%; border-collapse: collapse; }
        .grille th, .grille td { border: 0.6px solid #333; padding: 2px 3px; }
        .grille th { background: #e4e8ee; font-size: 7.4px; }
        .c { text-align: center; }
        .b { font-weight: bold; }
        h1 { font-size: 13px; text-align: center; margin: 2px 0; }
        h2 { font-size: 9.5px; margin: 8px 0 3px; }
        .sous-titre { text-align: center; font-size: 9px; margin-bottom: 6px; }
        .resume td { border: none; padding: 1px 6px 1px 0; font-size: 8.5px; }
        .signatures td { border: none; text-align: center; height: 40px; vertical-align: top; }
        .filigrane {
            position: fixed; top: 220px; left: 120px; width: 800px; text-align: center;
            font-size: 80px; font-weight: bold; color: #c8102e; opacity: 0.12; transform: rotate(-25deg);
        }
    </style>
</head>

<body>
    <?php if($filigrane): ?>
        <div class="filigrane"><?php echo e($filigrane); ?></div>
    <?php endif; ?>

    <?php echo $__env->make('pdf.partials.entete', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <h1><?php echo e($titre); ?></h1>
    <div class="sous-titre">
        Classe : <span class="b"><?php echo e($classe['nom'] ?: $classe['code']); ?></span> —
        <?php echo e($periode); ?> — Année Scolaire : <?php echo e($annee); ?>

    </div>

    <table class="resume">
        <tr>
            <td>Effectif : <span class="b"><?php echo e($effectif); ?></span></td>
            <td>Élèves classés : <span class="b"><?php echo e($effectif_classe); ?></span></td>
            <td>Moyenne de la classe : <span class="b"><?php echo e($moyenne_classe); ?></span></td>
            <td>Plus forte : <?php echo e($moyenne_max); ?></td>
            <td>Plus faible : <?php echo e($moyenne_min); ?></td>
            <?php $__currentLoopData = $decisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $libelle => $nombre): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <td><?php echo e($libelle); ?> : <span class="b"><?php echo e($nombre); ?></span></td>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tr>
    </table>

    <h2>Résultats et décisions</h2>
    <table class="grille">
        <thead>
            <tr>
                <th style="width:6%">Rang</th>
                <th style="width:9%">Matricule</th>
                <th style="width:20%">Nom et prénoms</th>
                <th style="width:7%">Statut</th>
                <th style="width:6%">Moyenne</th>
                <th style="width:5%">Mention</th>
                <th style="width:5%">Crédits</th>
                <th style="width:6%">Comp. non val.</th>
                <th style="width:10%">Décision proposée</th>
                <th style="width:10%">Décision retenue</th>
                <th style="width:16%">Observation</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $eleves; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td class="c"><?php echo e($e['rang']); ?></td>
                    <td><?php echo e($e['matricule']); ?></td>
                    <td><?php echo e($e['nom_complet']); ?></td>
                    <td class="c"><?php echo e($e['statut']); ?></td>
                    <td class="c b"><?php echo e($e['moyenne']); ?></td>
                    <td class="c"><?php echo e($e['mention']); ?></td>
                    <td class="c"><?php echo e($e['credits']); ?></td>
                    <td class="c"><?php echo e($e['non_validees']); ?></td>
                    <td><?php echo e($e['decision_proposee'] ?? '—'); ?></td>
                    <td class="b"><?php echo e($e['decision'] ?? '—'); ?><?php echo e($e['modifiee'] ? ' *' : ''); ?></td>
                    <td><?php echo e($e['observation']); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
    <div style="font-size:7px;margin-top:2px">* décision modifiée par le conseil de classe.</div>

    <h2>Compétences validées par module</h2>
    <table class="grille">
        <thead>
            <tr>
                <th style="width:8%">Code</th>
                <th style="width:48%">Module</th>
                <th style="width:10%">Élèves notés</th>
                <th style="width:12%">Compétences validées</th>
                <th style="width:10%">Taux</th>
                <th style="width:12%">Moyenne du module</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $modules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td class="c"><?php echo e($m['code']); ?></td>
                    <td><?php echo e($m['intitule']); ?></td>
                    <td class="c"><?php echo e($m['notes']); ?></td>
                    <td class="c"><?php echo e($m['validees']); ?></td>
                    <td class="c b"><?php echo e($m['taux']); ?></td>
                    <td class="c"><?php echo e($m['moyenne']); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>

    <table class="signatures" style="margin-top:10px">
        <tr>
            <td style="width:33%">Le Professeur principal</td>
            <td style="width:33%">Le Censeur</td>
            <td style="width:34%">
                Le Chef d'établissement, président du conseil
                <?php if($valide_le): ?>
                    <br><span style="font-size:7px">Conseil validé le <?php echo e($valide_le); ?></span>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</body>

</html>
<?php /**PATH C:\laragon\www\auditron-qr\saas\api\resources\views/pdf/pv-conseil.blade.php ENDPATH**/ ?>