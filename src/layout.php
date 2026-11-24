<?php
// Socle des pages HTML : données, connexion, en-tête et pied de page, petites aides d'affichage.

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/markdown.php';
require_once __DIR__ . '/newsletters.php';
require_once __DIR__ . '/entrainement.php';

// Échappe un texte avant de l'afficher (protection XSS).
function e(?string $texte): string
{
    return htmlspecialchars($texte ?? '', ENT_QUOTES, 'UTF-8');
}

const JOURS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
              'août', 'septembre', 'octobre', 'novembre', 'décembre'];

// « dimanche 22 novembre 2026 », ou « 22 novembre 2026 » sans le jour.
// Fait à la main : l'image Docker officielle de PHP n'a pas l'extension intl.
function date_longue(string $date, bool $avec_jour = true): string
{
    $d = new DateTimeImmutable($date);
    $texte = (int)$d->format('j') . ' ' . MOIS[(int)$d->format('n') - 1] . ' ' . $d->format('Y');
    return $avec_jour ? jour_semaine($date) . ' ' . $texte : $texte;
}

function jour_semaine(string $date): string
{
    return JOURS[(int)(new DateTimeImmutable($date))->format('w')];
}

// Icônes au trait (dessins du jeu libre Lucide).
function icone(string $nom, int $taille = 20): string
{
    $dessins = [
        'course'      => '<path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/>',
        'drapeau'     => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path d="M4 22v-7"/>',
        'calendrier'  => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'cloche'      => '<path d="M10.27 21a2 2 0 0 0 3.46 0"/><path d="M3.26 15.33A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.67C19.41 13.96 18 12.5 18 8A6 6 0 0 0 6 8c0 4.5-1.41 5.96-2.74 7.33"/>',
        'bilan'       => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/>',
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

// $large : page plus large que la colonne de lecture (accueil et sa grille).
function entete(string $titre, bool $avec_menu = true, bool $large = false): void
{
    $classe = $large ? 'container large' : 'container';
    $css = '/style.css?v=' . filemtime(__DIR__ . '/../public/style.css');
    ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f5f1" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#111113" media="(prefers-color-scheme: dark)">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($titre) ?> · Board</title>
    <link rel="icon" href="/img/icone.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/img/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-title" content="Board">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600;8..60,700&display=swap">
    <link rel="stylesheet" href="<?= e($css) ?>">
</head>
<body>
<?php if ($avec_menu): ?>
<header class="barre-haut">
    <div class="<?= $classe ?>">
        <a href="/" class="logo">Board</a>
        <nav>
            <a href="/notifications.php" class="icone-bouton" title="Notifications" aria-label="Notifications"><?= icone('cloche') ?></a>
            <a href="/logout.php" class="icone-bouton" title="Se déconnecter" aria-label="Se déconnecter"><?= icone('deconnexion') ?></a>
        </nav>
    </div>
</header>
<?php endif; ?>
<main class="<?= $classe ?>">
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
