<?php
// Notifications push web (Web Push), en PHP pur avec OpenSSL, sans bibliothèque.
//
// Principe : chaque appareil abonné (table abonnements_push) fournit une adresse chez son
// service de push (Apple, Google, Mozilla) et deux clés. Pour le notifier, on chiffre le
// message pour cet appareil (RFC 8291) et on signe la requête avec nos clés VAPID (RFC 8292).
//
// Clés VAPID : variables VAPID_PUBLIC et VAPID_PRIVATE (base64url, format standard :
// clé publique brute de 65 octets, clé privée brute de 32 octets). Voir le README.

require_once __DIR__ . '/db.php';

// ---------- Abonnements ----------

function enregistrer_abonnement(string $endpoint, string $p256dh, string $auth): void
{
    db()->prepare('INSERT INTO abonnements_push (endpoint, p256dh, auth) VALUES (?, ?, ?)
        ON CONFLICT(endpoint) DO UPDATE SET p256dh = excluded.p256dh, auth = excluded.auth')
        ->execute([$endpoint, $p256dh, $auth]);
}

function supprimer_abonnement(string $endpoint): void
{
    db()->prepare('DELETE FROM abonnements_push WHERE endpoint = ?')->execute([$endpoint]);
}

function push_configure(): bool
{
    return secret('VAPID_PUBLIC') !== null && secret('VAPID_PRIVATE') !== null;
}

// Envoie une notification à tous les appareils abonnés. Ne lève jamais d'exception :
// une notification ratée ne doit pas faire échouer la publication qui l'a déclenchée.
// Renvoie le nombre d'appareils notifiés.
function notifier_tous(string $titre, string $texte, string $url = '/'): int
{
    if (!push_configure()) return 0;
    $message = json_encode(['titre' => $titre, 'texte' => $texte, 'url' => $url], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ok = 0;
    foreach (db()->query('SELECT * FROM abonnements_push')->fetchAll() as $abo) {
        try {
            $statut = envoyer_push($abo, $message);
            if ($statut === 404 || $statut === 410) {
                supprimer_abonnement($abo['endpoint']); // appareil désabonné ou abonnement expiré
            } elseif ($statut >= 200 && $statut < 300) {
                $ok++;
            } else {
                error_log("Push : réponse $statut de " . parse_url($abo['endpoint'], PHP_URL_HOST));
            }
        } catch (Throwable $e) {
            error_log('Push : ' . $e->getMessage());
        }
    }
    return $ok;
}

// ---------- Envoi ----------

// Chiffre et envoie un message à un abonnement. Renvoie le code HTTP du service de push.
function envoyer_push(array $abo, string $message): int
{
    $corps = chiffrer_message($message, b64url_decode($abo['p256dh']), b64url_decode($abo['auth']));

    $contexte = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => implode("\r\n", [
            'Authorization: ' . entete_vapid($abo['endpoint']),
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: 86400',         // le service garde le message 24 h si l'appareil est hors ligne
            'Urgency: normal',
        ]),
        'content' => $corps,
        'ignore_errors' => true, // pour lire le code HTTP même en cas d'erreur
        'timeout' => 10,
    ]]);
    @file_get_contents($abo['endpoint'], false, $contexte);

    // $http_response_header est rempli par file_get_contents.
    if (!isset($http_response_header[0]) || !preg_match('/\s(\d{3})\s?/', $http_response_header[0], $m)) {
        throw new RuntimeException('pas de réponse du service de push');
    }
    return (int)$m[1];
}

// En-tête d'autorisation VAPID : un jeton JWT signé (ES256) + notre clé publique.
function entete_vapid(string $endpoint): string
{
    $port = parse_url($endpoint, PHP_URL_PORT);
    $origine = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST) . ($port ? ":$port" : '');
    $sujet = secret('VAPID_SUJET') ?? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

    $entete = b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $contenu = b64url_encode(json_encode(['aud' => $origine, 'exp' => time() + 12 * 3600, 'sub' => $sujet], JSON_UNESCAPED_SLASHES));
    $a_signer = "$entete.$contenu";

    $publique = b64url_decode(secret('VAPID_PUBLIC'));
    $privee = cle_privee_pem(b64url_decode(secret('VAPID_PRIVATE')), $publique);
    if (!openssl_sign($a_signer, $signature_der, $privee, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('signature VAPID impossible');
    }
    return "vapid t=$a_signer." . b64url_encode(signature_brute($signature_der)) . ', k=' . secret('VAPID_PUBLIC');
}

// ---------- Chiffrement (RFC 8291, encodage aes128gcm de la RFC 8188) ----------

// $cle_ephemere et $sel ne servent qu'aux tests (vecteurs de la RFC) : en temps normal,
// une nouvelle paire de clés et un nouveau sel sont tirés à chaque message.
function chiffrer_message(string $message, string $ua_publique, string $ua_auth, ?OpenSSLAsymmetricKey $cle_ephemere = null, ?string $sel = null): string
{
    $cle_ephemere ??= openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $sel ??= random_bytes(16);
    $as_publique = cle_publique_brute($cle_ephemere);

    // Secret partagé (ECDH) entre notre clé éphémère et la clé de l'appareil.
    $secret_ecdh = openssl_pkey_derive(cle_publique_pem($ua_publique), $cle_ephemere, 32);
    if ($secret_ecdh === false) {
        throw new RuntimeException('clé publique de l\'abonnement invalide');
    }

    $ikm = hash_hkdf('sha256', $secret_ecdh, 32, "WebPush: info\0" . $ua_publique . $as_publique, $ua_auth);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $sel);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $sel);

    // Un seul enregistrement : le message suivi du délimiteur de fin (0x02).
    $chiffre = openssl_encrypt($message . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

    // En-tête : sel (16) | taille d'enregistrement (4) | longueur de la clé (1) | clé publique (65).
    return $sel . pack('N', 4096) . chr(strlen($as_publique)) . $as_publique . $chiffre . $tag;
}

// ---------- Outils de format ----------

function b64url_encode(string $octets): string
{
    return rtrim(strtr(base64_encode($octets), '+/', '-_'), '=');
}

function b64url_decode(string $texte): string
{
    return base64_decode(strtr($texte, '-_', '+/'), true) ?: '';
}

// Clé publique P-256 au format brut non compressé : 0x04 | x (32) | y (32).
function cle_publique_brute(OpenSSLAsymmetricKey $cle): string
{
    $ec = openssl_pkey_get_details($cle)['ec'];
    return "\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
}

// Clé publique brute → PEM (structure SubjectPublicKeyInfo d'une clé P-256).
function cle_publique_pem(string $brute): OpenSSLAsymmetricKey
{
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $brute;
    $cle = openssl_pkey_get_public(pem('PUBLIC KEY', $der));
    if ($cle === false) throw new RuntimeException('clé publique invalide');
    return $cle;
}

// Clé privée brute (32 octets) → PEM (structure ECPrivateKey de la RFC 5915).
function cle_privee_pem(string $d, string $publique): OpenSSLAsymmetricKey
{
    $der = hex2bin('30770201010420') . $d . hex2bin('a00a06082a8648ce3d030107a144034200') . $publique;
    $cle = openssl_pkey_get_private(pem('EC PRIVATE KEY', $der));
    if ($cle === false) throw new RuntimeException('clé privée VAPID invalide');
    return $cle;
}

function pem(string $type, string $der): string
{
    return "-----BEGIN $type-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END $type-----\n";
}

// OpenSSL renvoie une signature ECDSA au format DER (SEQUENCE de deux INTEGER r et s) ;
// le JWT attend r et s bruts, 32 octets chacun.
function signature_brute(string $der): string
{
    $pos = 2; // SEQUENCE + longueur
    $brut = '';
    for ($i = 0; $i < 2; $i++) {
        $longueur = ord($der[$pos + 1]);
        $entier = ltrim(substr($der, $pos + 2, $longueur), "\0");
        $brut .= str_pad($entier, 32, "\0", STR_PAD_LEFT);
        $pos += 2 + $longueur;
    }
    return $brut;
}

// Génère une paire de clés VAPID (à lancer une fois, voir le README).
function generer_cles_vapid(): array
{
    $cle = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $d = str_pad(openssl_pkey_get_details($cle)['ec']['d'], 32, "\0", STR_PAD_LEFT);
    return ['VAPID_PUBLIC' => b64url_encode(cle_publique_brute($cle)), 'VAPID_PRIVATE' => b64url_encode($d)];
}
