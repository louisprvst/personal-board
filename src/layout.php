<?php
// Fonctions partagées par toutes les pages.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/markdown.php';

// Échappe un texte avant de l'afficher (protection XSS).
function e(?string $texte): string
{
    return htmlspecialchars($texte ?? '', ENT_QUOTES, 'UTF-8');
}

const FUSEAU = 'Europe/Paris';
date_default_timezone_set(FUSEAU);

// « dimanche 22 novembre 2026 »
function date_longue(string $date): string
{
    // Fait à la main : l'image Docker officielle de PHP n'a pas l'extension intl.
    $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
             'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $d = new DateTimeImmutable($date);
    return $jours[(int)$d->format('w')] . ' ' . (int)$d->format('j') . ' '
        . $mois[(int)$d->format('n') - 1] . ' ' . $d->format('Y');
}

// Temps de lecture estimé (230 mots / minute), minimum 1 minute.
function temps_lecture(string $texte): int
{
    return max(1, (int)round(str_word_count(strip_tags($texte)) / 230));
}

// Icônes au trait (dessins du jeu libre Lucide).
function icone(string $nom, int $taille = 20): string
{
    $dessins = [
        'archives'    => '<rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/>',
        'deconnexion' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'gauche'      => '<path d="m15 18-6-6 6-6"/>',
        'droite'      => '<path d="m9 18 6-6-6-6"/>',
        'journal'     => '<path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/>',
    ];
    return '<svg width="' . $taille . '" height="' . $taille . '" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
        . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . $dessins[$nom] . '</svg>';
}

function entete(string $titre, bool $avec_menu = true): void
{
    $css = '/style.css?v=' . filemtime(__DIR__ . '/../public/style.css');
    ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#111113">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($titre) ?> · Board</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600;8..60,700&display=swap">
    <link rel="stylesheet" href="<?= e($css) ?>">
</head>
<body>
<?php if ($avec_menu): ?>
<header class="barre-haut">
    <div class="container">
        <a href="/" class="logo">Board</a>
        <nav>
            <a href="/archives.php" class="icone-bouton" title="Archives" aria-label="Archives"><?= icone('archives') ?></a>
            <a href="/logout.php" class="icone-bouton" title="Se déconnecter" aria-label="Se déconnecter"><?= icone('deconnexion') ?></a>
        </nav>
    </div>
</header>
<?php endif; ?>
<main class="container">
    <?php
}

function pied(): void
{
    ?>
</main>
</body>
</html>
    <?php
}

// Affiche une newsletter complète (titre, méta, contenu, navigation).
function afficher_newsletter(array $n): void
{
    $prec = date_voisine($n['date'], false);
    $suiv = date_voisine($n['date'], true);
    ?>
    <article class="newsletter">
        <header>
            <p class="surtitre"><?= icone('journal', 16) ?> <?= e(ucfirst(date_longue($n['date']))) ?> · <?= temps_lecture($n['contenu']) ?> min de lecture</p>
            <h1><?= e($n['titre']) ?></h1>
        </header>
        <div class="contenu"><?= markdown($n['contenu']) ?></div>
        <footer class="navigation">
            <?php if ($prec): ?>
                <a href="/newsletter.php?date=<?= e($prec) ?>"><?= icone('gauche', 16) ?> <?= e(date_longue($prec)) ?></a>
            <?php else: ?><span></span><?php endif; ?>
            <?php if ($suiv): ?>
                <a href="/newsletter.php?date=<?= e($suiv) ?>"><?= e(date_longue($suiv)) ?> <?= icone('droite', 16) ?></a>
            <?php endif; ?>
        </footer>
    </article>
    <?php
}
