<?php
// Connecteur Claude (serveur MCP, transport « Streamable HTTP » sans état).
//
// Adresse à ajouter dans claude.ai (Paramètres → Connecteurs → connecteur personnalisé) :
//     https://<ton-domaine>/mcp.php/<MCP_SECRET>
// Le secret dans l'adresse sert d'authentification : garde-la pour toi.
//
// Outils exposés :
//   - publier_semaine : enregistre le plan d'entraînement d'une semaine (remplace l'existant)
//   - lire_semaine    : relit le plan publié pour une semaine (passée, en cours ou future)
//   - lire_historique : relit les dernières semaines passées
//   - publier_bilan   : ajoute ou remplace le bilan d'une semaine déjà publiée

require_once __DIR__ . '/../src/entrainement.php';

const VERSIONS_MCP = ['2025-11-25', '2025-06-18', '2025-03-26'];

// ---------- Transport ----------

function reponse_http(int $code, ?array $corps = null): never
{
    http_response_code($code);
    if ($corps !== null) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

function erreur_rpc($id, int $code, string $message): array
{
    return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
}

$secret = secret('MCP_SECRET');
if ($secret === null) {
    reponse_http(503, ['erreur' => 'MCP_SECRET non configuré sur le serveur.']);
}
$fourni = trim($_SERVER['PATH_INFO'] ?? '', '/');
if (!hash_equals($secret, $fourni)) {
    sleep(1);
    reponse_http(404); // on ne révèle pas l'existence du connecteur
}

// Sans état : pas de flux SSE (GET) ni de session à fermer (DELETE).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    reponse_http(405);
}

$message = json_decode(file_get_contents('php://input'), true);
if (!is_array($message) || ($message['jsonrpc'] ?? null) !== '2.0' || !isset($message['method'])) {
    reponse_http(400, erreur_rpc(null, -32700, 'Requête JSON-RPC invalide.'));
}

// Une notification (sans id) n'attend pas de réponse.
if (!array_key_exists('id', $message)) {
    reponse_http(202);
}

$id = $message['id'];
$params = $message['params'] ?? [];

switch ($message['method']) {
    case 'initialize':
        $demandee = $params['protocolVersion'] ?? '';
        reponse_http(200, ['jsonrpc' => '2.0', 'id' => $id, 'result' => [
            'protocolVersion' => in_array($demandee, VERSIONS_MCP, true) ? $demandee : VERSIONS_MCP[0],
            'capabilities' => ['tools' => (object)[]],
            'serverInfo' => ['name' => 'personal-board', 'version' => '1.0.0'],
            'instructions' => "Dashboard personnel de l'utilisateur. Quand tu prépares son plan "
                . "d'entraînement de course à pied pour une semaine, publie-le avec publier_semaine "
                . "(une entrée par jour, du lundi au dimanche, repos compris). Il le lira sur son dashboard. "
                . "Les semaines passées restent consultables avec lire_semaine et lire_historique, "
                . "à comparer avec ce qu'il a réellement couru sur Strava. En fin de semaine, "
                . "ajoute ton bilan avec publier_bilan.",
        ]]);

    case 'ping':
        reponse_http(200, ['jsonrpc' => '2.0', 'id' => $id, 'result' => (object)[]]);

    case 'tools/list':
        reponse_http(200, ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['tools' => outils()]]);

    case 'tools/call':
        $nom = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];
        try {
            $texte = match ($nom) {
                'publier_semaine' => outil_publier_semaine($args),
                'lire_semaine'    => outil_lire_semaine($args),
                'lire_historique' => outil_lire_historique($args),
                'publier_bilan'   => outil_publier_bilan($args),
                default => throw new InvalidArgumentException("Outil inconnu : $nom"),
            };
            $resultat = ['content' => [['type' => 'text', 'text' => $texte]]];
        } catch (InvalidArgumentException $e) {
            // Erreur de saisie : renvoyée au modèle pour qu'il corrige son appel.
            $resultat = ['content' => [['type' => 'text', 'text' => $e->getMessage()]], 'isError' => true];
        }
        reponse_http(200, ['jsonrpc' => '2.0', 'id' => $id, 'result' => $resultat]);

    default:
        reponse_http(200, erreur_rpc($id, -32601, 'Méthode inconnue : ' . $message['method']));
}

// ---------- Outils ----------

function outils(): array
{
    $date = ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$'];
    return [
        [
            'name' => 'publier_semaine',
            'title' => "Publier le plan d'entraînement de la semaine",
            'description' => "Publie sur le dashboard le plan de course à pied d'une semaine (lundi → dimanche), "
                . "passée, en cours ou future. Remplace le plan déjà publié pour cette semaine ; pour une "
                . "semaine passée déjà publiée, il faut en plus remplacer: true. Donne une séance par jour, y compris "
                . "les jours de repos (type « Repos »). Le détail doit être autonome : allures, durées, "
                . "récupérations, consignes de FC, conseils de nutrition éventuels.",
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'lundi' => $date + ['description' => 'Date du lundi de la semaine (AAAA-MM-JJ).'],
                    'objectif' => ['type' => 'string', 'description' => 'Objectif de la semaine en 1 à 3 phrases.'],
                    'seances' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'description' => 'Les séances de la semaine, dans l\'ordre.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'date' => $date + ['description' => 'Jour de la séance, dans la semaine indiquée.'],
                                'type' => ['type' => 'string', 'description' => 'Titre court, ex. « Seuil en 2 blocs », « Repos ».'],
                                'detail' => ['type' => 'string', 'description' => 'Contenu complet de la séance.'],
                            ],
                            'required' => ['date', 'type'],
                        ],
                    ],
                    'course_nom' => ['type' => 'string', 'description' => 'Course visée, ex. « 10 km » (facultatif).'],
                    'course_date' => $date + ['description' => 'Date de la course visée (facultatif).'],
                    'remplacer' => ['type' => 'boolean',
                        'description' => 'Obligatoire (true) pour écraser une semaine passée déjà publiée. Inutile sinon.'],
                ],
                'required' => ['lundi', 'seances'],
            ],
        ],
        [
            'name' => 'lire_semaine',
            'title' => "Lire le plan d'entraînement publié",
            'description' => 'Renvoie le plan publié pour une semaine, passée, en cours ou future '
                . '(par défaut la semaine en cours).',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'lundi' => $date + ['description' => 'Lundi de la semaine voulue (facultatif).'],
                ],
            ],
            'annotations' => ['readOnlyHint' => true],
        ],
        [
            'name' => 'publier_bilan',
            'title' => 'Publier le bilan de la semaine',
            'description' => "Ajoute (ou remplace) le bilan d'une semaine déjà publiée, en cours ou passée. "
                . "Il s'affiche sur le dashboard au-dessus du plan de la semaine. Markdown accepté : "
                . "**gras**, *italique*, listes « - », titres « # », liens [texte](https://…). "
                . "Exemple de contenu : séances faites ou non, km, allures et FC observées sur Strava, "
                . "sensations, points de vigilance, ce qui change la semaine suivante.",
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'lundi' => $date + ['description' => 'Lundi de la semaine concernée (AAAA-MM-JJ).'],
                    'bilan' => ['type' => 'string', 'description' => 'Le bilan, en Markdown.'],
                ],
                'required' => ['lundi', 'bilan'],
            ],
        ],
        [
            'name' => 'lire_historique',
            'title' => "Lire l'historique des plans",
            'description' => 'Renvoie les plans des dernières semaines passées (avant la semaine en cours), '
                . 'de la plus récente à la plus ancienne. Utile pour faire le bilan et construire la progression.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'nombre' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20,
                        'description' => 'Nombre de semaines à renvoyer (4 par défaut, 20 au maximum).'],
                ],
            ],
            'annotations' => ['readOnlyHint' => true],
        ],
    ];
}

function verifier_date($valeur, string $champ): string
{
    $d = is_string($valeur) ? DateTimeImmutable::createFromFormat('!Y-m-d', $valeur) : false;
    if (!$d || $d->format('Y-m-d') !== $valeur) {
        throw new InvalidArgumentException("$champ doit être une date AAAA-MM-JJ.");
    }
    return $valeur;
}

function outil_publier_semaine(array $a): string
{
    $lundi = verifier_date($a['lundi'] ?? null, 'lundi');
    if ((new DateTimeImmutable($lundi))->format('N') !== '1') {
        throw new InvalidArgumentException("$lundi n'est pas un lundi.");
    }
    $dimanche = decaler($lundi, 6);
    $existante = semaine_entrainement($lundi);
    if ($existante && $dimanche < date('Y-m-d') && ($a['remplacer'] ?? false) !== true) {
        throw new InvalidArgumentException("La semaine du $lundi est terminée et déjà publiée. Pour l'écraser, "
            . "relance avec remplacer: true (le bilan éventuel est conservé). Pour seulement ajouter un bilan, utilise publier_bilan.");
    }

    if (!is_array($a['seances'] ?? null) || !$a['seances']) {
        throw new InvalidArgumentException('seances doit contenir au moins une séance.');
    }
    $seances = [];
    foreach ($a['seances'] as $i => $s) {
        $date = verifier_date($s['date'] ?? null, "seances[$i].date");
        if ($date < $lundi || $date > $dimanche) {
            throw new InvalidArgumentException("seances[$i].date ($date) n'est pas dans la semaine du $lundi au $dimanche.");
        }
        $type = trim((string)($s['type'] ?? ''));
        if ($type === '') {
            throw new InvalidArgumentException("seances[$i].type est requis.");
        }
        $seances[] = ['date' => $date, 'type' => $type, 'detail' => trim((string)($s['detail'] ?? ''))];
    }
    usort($seances, fn($x, $y) => strcmp($x['date'], $y['date'])); // tri stable : l'ordre d'une même journée est gardé

    $course_nom = trim((string)($a['course_nom'] ?? '')) ?: null;
    $course_date = isset($a['course_date']) && $a['course_date'] !== '' ? verifier_date($a['course_date'], 'course_date') : null;

    enregistrer_semaine($lundi, trim((string)($a['objectif'] ?? '')), $seances, $course_nom, $course_date);

    return sprintf('Plan %s pour la semaine du %s au %s (%d séance%s). Il est visible sur le dashboard.',
        $existante ? 'remplacé' : 'publié', $lundi, $dimanche, count($seances), count($seances) > 1 ? 's' : '');
}

function outil_publier_bilan(array $a): string
{
    $lundi = lundi_de(verifier_date($a['lundi'] ?? null, 'lundi'));
    $bilan = trim((string)($a['bilan'] ?? ''));
    if ($bilan === '') {
        throw new InvalidArgumentException('bilan est requis.');
    }
    if ($lundi > lundi_de(date('Y-m-d'))) {
        throw new InvalidArgumentException("La semaine du $lundi n'a pas encore commencé : pas de bilan possible.");
    }
    $semaine = semaine_entrainement($lundi);
    if (!$semaine) {
        throw new InvalidArgumentException("Aucun plan publié pour la semaine du $lundi : publie d'abord le plan avec publier_semaine.");
    }
    enregistrer_bilan($lundi, $bilan);
    return sprintf('Bilan %s pour la semaine du %s. Il est visible sur le dashboard.',
        $semaine['bilan'] ? 'remplacé' : 'publié', $lundi);
}

function outil_lire_semaine(array $a): string
{
    $lundi = isset($a['lundi']) ? lundi_de(verifier_date($a['lundi'], 'lundi')) : lundi_de(date('Y-m-d'));
    $s = semaine_entrainement($lundi);
    return $s ? json_encode(semaine_pour_claude($s), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
              : "Aucun plan publié pour la semaine du $lundi.";
}

function outil_lire_historique(array $a): string
{
    $nombre = $a['nombre'] ?? 4;
    if (!is_int($nombre) || $nombre < 1 || $nombre > 20) {
        throw new InvalidArgumentException('nombre doit être un entier entre 1 et 20.');
    }
    $semaines = semaines_avant(lundi_de(date('Y-m-d')), $nombre);
    return $semaines ? json_encode(array_map('semaine_pour_claude', $semaines), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                     : 'Aucune semaine passée enregistrée.';
}

// Ce qu'on renvoie au modèle pour une semaine (le dimanche est ajouté pour lever toute ambiguïté).
function semaine_pour_claude(array $s): array
{
    return [
        'lundi' => $s['lundi'],
        'dimanche' => decaler($s['lundi'], 6),
        'objectif' => $s['objectif'],
        'seances' => $s['seances'],
        'course_nom' => $s['course_nom'],
        'course_date' => $s['course_date'],
        'bilan' => $s['bilan'],
        'modifie_le' => $s['modifie_le'],
    ];
}
