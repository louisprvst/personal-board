<?php
// API de publication, utilisée par la routine Claude.
//
//   POST /api/newsletter.php
//   Authorization: Bearer <API_TOKEN>
//   Content-Type: application/json
//   {"titre": "…", "contenu": "… (Markdown)", "date": "AAAA-MM-JJ"}   ← date facultative (défaut : aujourd'hui, Paris)
//
// Republier à une date existante remplace la newsletter de ce jour.
// Seules les 7 plus récentes sont gardées (NEWSLETTERS_GARDEES dans src/db.php).

require_once __DIR__ . '/../../src/secrets.php';
require_once __DIR__ . '/../../src/db.php';
date_default_timezone_set('Europe/Paris');

header('Content-Type: application/json; charset=utf-8');

function repondre(int $code, array $donnees): never
{
    http_response_code($code);
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    repondre(405, ['erreur' => 'Méthode non autorisée, utilise POST.']);
}

$jeton = secret('API_TOKEN');
if ($jeton === null) {
    repondre(503, ['erreur' => 'API_TOKEN non configuré sur le serveur.']);
}

// Le serveur intégré de PHP expose l'en-tête via getallheaders().
$entetes = array_change_key_case(getallheaders(), CASE_LOWER);
$auth = $entetes['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+(\S+)$/', $auth, $m) || !hash_equals($jeton, $m[1])) {
    sleep(1);
    repondre(401, ['erreur' => 'Jeton invalide.']);
}

$corps = file_get_contents('php://input');
if (strlen($corps) > 500_000) {
    repondre(413, ['erreur' => 'Contenu trop volumineux (500 Ko max).']);
}
$donnees = json_decode($corps, true);
if (!is_array($donnees)) {
    repondre(400, ['erreur' => 'JSON invalide.']);
}

$titre = trim((string)($donnees['titre'] ?? ''));
$contenu = trim((string)($donnees['contenu'] ?? ''));
$date = (string)($donnees['date'] ?? date('Y-m-d'));

$erreurs = [];
if ($titre === '' || preg_match_all('/./u', $titre) > 200) $erreurs[] = 'titre requis (200 caractères max)';
if ($contenu === '') $erreurs[] = 'contenu requis';
$d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) $erreurs[] = 'date au format AAAA-MM-JJ';
if ($erreurs) {
    repondre(422, ['erreur' => 'Données invalides : ' . implode(', ', $erreurs) . '.']);
}

$existait = newsletter_du($date) !== null;
$supprimees = enregistrer_newsletter($date, $titre, $contenu);

repondre($existait ? 200 : 201, [
    'ok' => true,
    'date' => $date,
    'remplacee' => $existait,
    'supprimees' => $supprimees,
]);
