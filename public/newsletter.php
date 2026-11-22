<?php
// Lecture d'une newsletter précise : /newsletter.php?date=AAAA-MM-JJ

require_once __DIR__ . '/../src/layout.php';
exiger_connexion();

$date = $_GET['date'] ?? '';
$n = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? newsletter_du($date) : null;

if (!$n) {
    http_response_code(404);
    entete('Introuvable');
    echo '<section class="widget vide"><h2>Aucune newsletter à cette date</h2>'
       . '<p><a href="/archives.php">Voir les archives</a></p></section>';
    pied();
    exit;
}

entete($n['titre']);
afficher_newsletter($n);
pied();
