<?php
// Liste de toutes les newsletters reçues, de la plus récente à la plus ancienne.

require_once __DIR__ . '/../src/layout.php';
exiger_connexion();

$liste = toutes_les_newsletters();
entete('Archives');
?>
<section class="widget">
    <h1>Archives</h1>
    <?php if (!$liste): ?>
        <p class="meta">Rien pour l'instant.</p>
    <?php else: ?>
        <ul class="archives">
            <?php foreach ($liste as $n): ?>
                <li>
                    <a href="/newsletter.php?date=<?= e($n['date']) ?>">
                        <span class="date"><?= e(ucfirst(date_longue($n['date']))) ?></span>
                        <span class="titre"><?= e($n['titre']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php pied();
