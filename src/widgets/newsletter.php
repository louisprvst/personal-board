<?php
// Widget : la newsletter du jour (ou la dernière reçue) en résumé ; un clic ouvre l'article.

function widget_newsletter(): void
{
    $n = newsletter_du(date('Y-m-d')) ?? derniere_newsletter();
    if (!$n) {
        ?>
        <section class="carte">
            <p class="carte-titre"><?= icone('journal', 16) ?> Newsletter</p>
            <p class="meta">Rien de publié pour l'instant. La routine Claude la déposera ici chaque matin.</p>
        </section>
        <?php
        return;
    }
    ?>
    <a class="carte lien" href="/newsletter.php?date=<?= e($n['date']) ?>">
        <p class="carte-titre">
            <?= icone('journal', 16) ?> Newsletter
            <?php if ($n['date'] === date('Y-m-d')): ?><span class="pastille">Nouveau</span><?php endif; ?>
        </p>
        <div class="remplit">
            <p class="meta"><?= e(ucfirst(date_longue($n['date']))) ?> · <?= temps_lecture($n['contenu']) ?> min</p>
            <h2><?= e($n['titre']) ?></h2>
            <?php if ($resume = resume_markdown($n['contenu'])): ?>
                <p class="resume"><?= e($resume) ?></p>
            <?php endif; ?>
        </div>
        <p class="meta bas separe">Lire <?= icone('droite', 16) ?></p>
    </a>
    <?php
}
