<?php
// Widget : séance de course à pied du jour, avec un aperçu de demain.

function widget_entrainement(): void
{
    $aujourdhui = date('Y-m-d');
    $publie = (bool)semaine_entrainement(lundi_de($aujourdhui));
    $seances = seances_du($aujourdhui);
    $demain = seances_du(decaler($aujourdhui, 1));
    ?>
    <a class="carte lien" href="/entrainement.php">
        <p class="carte-titre"><?= icone('course', 16) ?> Séance du jour</p>

        <div class="remplit">
            <?php if (!$publie): ?>
                <p class="meta">Aucun plan publié pour cette semaine.</p>
            <?php elseif (!$seances): ?>
                <h2 class="repos">Rien de prévu</h2>
            <?php endif; ?>
            <?php foreach ($seances as $s): ?>
                <h2 class="<?= est_repos($s) ? 'repos' : '' ?>"><?= e($s['type']) ?></h2>
                <?php if ($s['detail'] !== ''): ?><p class="detail-seance"><?= e($s['detail']) ?></p><?php endif; ?>
            <?php endforeach; ?>
        </div>

        <p class="meta bas separe">
            <?= $demain ? 'Demain : ' . e(implode(' + ', array_column($demain, 'type'))) : 'Voir la semaine' ?>
            <?= icone('droite', 16) ?>
        </p>
    </a>
    <?php
}
