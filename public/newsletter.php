<?php
// Lecture d'une newsletter : /newsletter.php?date=AAAA-MM-JJ

require_once __DIR__ . '/../src/layout.php';
exiger_connexion();

$date = $_GET['date'] ?? '';
$n = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? newsletter_du($date) : null;

if (!$n) {
    http_response_code(404);
    entete('Introuvable');
    ?>
    <section class="carte vide">
        <h2>Aucune newsletter à cette date</h2>
        <p><a href="/archives.php">Voir les archives</a></p>
    </section>
    <?php
    pied();
    exit;
}

$prec = date_voisine($n['date'], false);
$suiv = date_voisine($n['date'], true);

entete($n['titre']);
?>
<article>
    <header class="entete-page">
        <p class="surtitre"><?= icone('journal', 16) ?> <?= e(ucfirst(date_longue($n['date']))) ?> · <?= temps_lecture($n['contenu']) ?> min de lecture</p>
        <h1><?= e($n['titre']) ?></h1>
    </header>
    <div class="contenu"><?= markdown($n['contenu']) ?></div>
</article>
<footer class="navigation">
    <?php if ($prec): ?>
        <a href="/newsletter.php?date=<?= e($prec) ?>"><?= icone('gauche', 16) ?> <?= e(date_longue($prec)) ?></a>
    <?php else: ?><span></span><?php endif; ?>
    <?php if ($suiv): ?>
        <a href="/newsletter.php?date=<?= e($suiv) ?>"><?= e(date_longue($suiv)) ?> <?= icone('droite', 16) ?></a>
    <?php endif; ?>
</footer>
<p class="lien-archives"><a href="/archives.php"><?= icone('archives', 16) ?> Toutes les newsletters</a></p>
<?php pied();
