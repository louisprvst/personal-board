<?php
// Widget : la semaine d'entraînement en un coup d'œil (un point par jour).
// Point plein = séance, point vide = repos ; le jour courant est entouré.

function widget_semaine(): void
{
    $aujourdhui = date('Y-m-d');
    $lundi = lundi_de($aujourdhui);
    $semaine = semaine_entrainement($lundi);
    if (!$semaine) return;

    $jours = seances_par_jour($semaine, $lundi);
    $nb = 0;
    foreach ($jours as $seances) {
        $nb += count(array_filter($seances, fn($s) => !est_repos($s)));
    }
    ?>
    <a class="carte lien" href="/entrainement.php">
        <p class="carte-titre"><?= icone('calendrier', 16) ?> Semaine</p>
        <ol class="points">
            <?php foreach ($jours as $date => $seances): ?>
                <?php
                $active = (bool)array_filter($seances, fn($s) => !est_repos($s));
                $classes = trim(($active ? 'seance' : '') . ($date === $aujourdhui ? ' aujourdhui' : '') . ($date < $aujourdhui ? ' passe' : ''));
                $titre = ucfirst(jour_semaine($date)) . ' : ' . ($seances ? implode(' + ', array_column($seances, 'type')) : 'rien de prévu');
                ?>
                <li class="<?= $classes ?>" title="<?= e($titre) ?>">
                    <span><?= e(strtoupper(jour_semaine($date)[0])) ?></span><i></i>
                </li>
            <?php endforeach; ?>
        </ol>
        <p class="meta bas"><?= $nb ?> séance<?= $nb > 1 ? 's' : '' ?> prévue<?= $nb > 1 ? 's' : '' ?></p>
    </a>
    <?php
}
