<?php
// Gestion des abonnements push de l'appareil connecté (utilisée par public/notifications.php).
//
//   POST /api/push.php   (Content-Type: application/json, session connectée)
//   {"action": "abonner", "abonnement": {"endpoint", "keys": {"p256dh", "auth"}}}
//   {"action": "desabonner", "endpoint": "…"}
//   {"action": "tester"}

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/push.php';

header('Content-Type: application/json; charset=utf-8');

function repondre(int $code, array $donnees): never
{
    http_response_code($code);
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    repondre(405, ['erreur' => 'Méthode non autorisée.']);
}
if (!est_connecte()) {
    repondre(401, ['erreur' => 'Non connecté.']);
}
// Un formulaire d'un autre site ne peut pas envoyer de JSON sans y être autorisé (CORS).
if (!str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    repondre(415, ['erreur' => 'JSON attendu.']);
}
if (!push_configure()) {
    repondre(503, ['erreur' => 'Notifications non configurées : VAPID_PUBLIC et VAPID_PRIVATE manquent sur le serveur.']);
}

$donnees = json_decode(file_get_contents('php://input'), true);

switch ($donnees['action'] ?? '') {
    case 'abonner':
        $abo = $donnees['abonnement'] ?? [];
        $endpoint = (string)($abo['endpoint'] ?? '');
        $p256dh = (string)($abo['keys']['p256dh'] ?? '');
        $auth = (string)($abo['keys']['auth'] ?? '');
        $cle = b64url_decode($p256dh);
        if (!str_starts_with($endpoint, 'https://') || strlen($cle) !== 65 || $cle[0] !== "\x04"
            || strlen(b64url_decode($auth)) !== 16) {
            repondre(422, ['erreur' => 'Abonnement invalide.']);
        }
        enregistrer_abonnement($endpoint, $p256dh, $auth);
        repondre(200, ['ok' => true]);

    case 'desabonner':
        supprimer_abonnement((string)($donnees['endpoint'] ?? ''));
        repondre(200, ['ok' => true]);

    case 'tester':
        $n = notifier_tous('Board', 'Les notifications fonctionnent.', '/');
        repondre(200, ['ok' => true, 'appareils' => $n]);

    default:
        repondre(400, ['erreur' => 'Action inconnue.']);
}
