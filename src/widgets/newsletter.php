<?php
// Widget : case « Newsletter » de l'accueil.
// Affiche la newsletter du jour (ou la dernière reçue) en résumé ; un clic ouvre l'article complet.

function widget_newsletter(): void
{
    $aujourdhui = date('Y-m-d');
    $n = newsletter_du($aujourdhui) ?? derniere_newsletter();

    if (!$n) {
        ?>
        <section class="widget carte-widget vide">
            <p class="widget-titre"><?= icone('journal', 16) ?> Newsletter</p>
            <p class="meta">Rien de publié pour l'instant. La routine Claude la déposera ici chaque matin.</p>
        </section>
        <?php
        return;
    }

    $du_jour = $n['date'] === $aujourdhui;
    ?>
    <a class="widget carte-widget lien-widget" href="/newsletter.php?date=<?= e($n['date']) ?>">
        <p class="widget-titre">
            <?= icone('journal', 16) ?> Newsletter
            <?php if ($du_jour): ?><span class="pastille">Nouveau</span><?php endif; ?>
        </p>
        <p class="meta"><?= e(ucfirst(date_longue($n['date']))) ?> · <?= temps_lecture($n['contenu']) ?> min</p>
        <h2><?= e($n['titre']) ?></h2>
        <?php if ($resume = resume_markdown($n['contenu'])): ?>
            <p class="resume"><?= e($resume) ?></p>
        <?php endif; ?>
        <span class="lire">Lire <?= icone('droite', 16) ?></span>
    </a>
    <?php
}
