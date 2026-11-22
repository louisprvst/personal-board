<?php
// Protection du dashboard par un mot de passe unique (même principe que Recette_Amelie).
//
// Après une connexion réussie, on dépose un cookie « expiration.signature ».
// La signature est un HMAC calculé avec le hash du mot de passe : impossible de
// fabriquer un faux cookie sans le connaître, et changer le mot de passe
// déconnecte automatiquement tous les appareils.

require_once __DIR__ . '/secrets.php';

const COOKIE_AUTH = 'personal_board';
const DUREE_CONNEXION = 60 * 24 * 3600; // 60 jours

function hash_mot_de_passe(): string
{
    $hash = secret('MOT_DE_PASSE_HASH');
    if ($hash === null) {
        http_response_code(500);
        exit('Mot de passe non défini : lance « php bin/creer-env.php » (en local) ou définis MOT_DE_PASSE_HASH (sur Railway).');
    }
    return $hash;
}

// Vrai si la page est servie en HTTPS, y compris derrière le proxy de Railway.
function en_https(): bool
{
    return !empty($_SERVER['HTTPS'])
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function signature(string $expiration): string
{
    return hash_hmac('sha256', $expiration, hash_mot_de_passe());
}

function est_connecte(): bool
{
    $morceaux = explode('.', $_COOKIE[COOKIE_AUTH] ?? '', 2);
    if (count($morceaux) !== 2) return false;
    [$expiration, $sig] = $morceaux;
    return ctype_digit($expiration)
        && (int)$expiration > time()
        && hash_equals(signature($expiration), $sig);
}

function poser_cookie(string $valeur, int $expiration): void
{
    setcookie(COOKIE_AUTH, $valeur, [
        'expires'  => $expiration,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => en_https(),
    ]);
}

function connecter(string $mot_de_passe): bool
{
    if (!password_verify($mot_de_passe, hash_mot_de_passe())) {
        sleep(1); // ralentit les essais en série
        return false;
    }
    $expiration = (string)(time() + DUREE_CONNEXION);
    poser_cookie($expiration . '.' . signature($expiration), (int)$expiration);
    return true;
}

function deconnecter(): void
{
    poser_cookie('', time() - 3600);
}

// À appeler en haut de chaque page protégée.
function exiger_connexion(): void
{
    if (!est_connecte()) {
        header('Location: /login.php?retour=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
        exit;
    }
}
