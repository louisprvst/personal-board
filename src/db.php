<?php
// Base SQLite. En local : data/board.sqlite. Sur Railway : DB_PATH (ex. /data/board.sqlite
// sur le volume). Un chemin relatif part de la racine du projet.
// Les tables sont créées automatiquement au premier lancement.

require_once __DIR__ . '/secrets.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $chemin = secret('DB_PATH') ?? 'data/board.sqlite';
        if ($chemin[0] !== '/') {
            $chemin = __DIR__ . '/../' . $chemin;
        }
        if (!is_dir(dirname($chemin))) {
            mkdir(dirname($chemin), 0775, true);
        }
        $pdo = new PDO('sqlite:' . $chemin);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Une newsletter par jour : republier à la même date remplace la précédente.
        $pdo->exec('CREATE TABLE IF NOT EXISTS newsletters (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            date       TEXT NOT NULL UNIQUE,   -- AAAA-MM-JJ
            titre      TEXT NOT NULL,
            contenu    TEXT NOT NULL,          -- Markdown
            cree_le    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            modifie_le TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
    }
    return $pdo;
}

// Nombre de newsletters conservées : à chaque publication, les plus anciennes sont supprimées.
const NEWSLETTERS_GARDEES = 7;

// Enregistre la newsletter, puis supprime celles qui dépassent la limite.
// Renvoie le nombre de newsletters supprimées.
function enregistrer_newsletter(string $date, string $titre, string $contenu): int
{
    db()->prepare('INSERT INTO newsletters (date, titre, contenu) VALUES (?, ?, ?)
        ON CONFLICT(date) DO UPDATE SET titre = excluded.titre, contenu = excluded.contenu,
        modifie_le = CURRENT_TIMESTAMP')
        ->execute([$date, $titre, $contenu]);

    $stmt = db()->prepare('DELETE FROM newsletters WHERE date NOT IN (
        SELECT date FROM newsletters ORDER BY date DESC LIMIT ?)');
    $stmt->execute([NEWSLETTERS_GARDEES]);
    return $stmt->rowCount();
}

function newsletter_du(string $date): ?array
{
    $stmt = db()->prepare('SELECT * FROM newsletters WHERE date = ?');
    $stmt->execute([$date]);
    return $stmt->fetch() ?: null;
}

function derniere_newsletter(): ?array
{
    return db()->query('SELECT * FROM newsletters ORDER BY date DESC LIMIT 1')->fetch() ?: null;
}

// Dates voisines, pour la navigation précédente / suivante.
function date_voisine(string $date, bool $suivante): ?string
{
    $sql = $suivante
        ? 'SELECT date FROM newsletters WHERE date > ? ORDER BY date ASC LIMIT 1'
        : 'SELECT date FROM newsletters WHERE date < ? ORDER BY date DESC LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute([$date]);
    return $stmt->fetchColumn() ?: null;
}

function toutes_les_newsletters(): array
{
    return db()->query('SELECT date, titre, length(contenu) AS taille FROM newsletters ORDER BY date DESC')
        ->fetchAll();
}
