<?php
// Connexion à la base SQLite et création des tables.
// En local : data/board.sqlite. Sur Railway : DB_PATH (ex. /data/board.sqlite sur le volume).
// Un chemin relatif part de la racine du projet.

require_once __DIR__ . '/secrets.php';

// Fuseau de référence pour toutes les dates (« aujourd'hui », lundi de la semaine…).
date_default_timezone_set('Europe/Paris');

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

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

    // Une newsletter par jour (voir src/newsletters.php).
    $pdo->exec('CREATE TABLE IF NOT EXISTS newsletters (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        date       TEXT NOT NULL UNIQUE,   -- AAAA-MM-JJ
        titre      TEXT NOT NULL,
        contenu    TEXT NOT NULL,          -- Markdown
        cree_le    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        modifie_le TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    // Une semaine d'entraînement par ligne (voir src/entrainement.php).
    $pdo->exec('CREATE TABLE IF NOT EXISTS semaines_entrainement (
        lundi       TEXT PRIMARY KEY,      -- AAAA-MM-JJ, premier jour de la semaine
        objectif    TEXT NOT NULL DEFAULT \'\',
        seances     TEXT NOT NULL,         -- JSON : [{"date", "type", "detail"}, ...]
        course_nom  TEXT,                  -- course visée, ex. « 10 km »
        course_date TEXT,                  -- AAAA-MM-JJ
        bilan       TEXT,                  -- bilan du coach en Markdown, ajouté après coup
        modifie_le  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    // Migrations : colonnes ajoutées après la création de la table sur une base existante.
    $colonnes = $pdo->query('PRAGMA table_info(semaines_entrainement)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('bilan', $colonnes, true)) {
        $pdo->exec('ALTER TABLE semaines_entrainement ADD COLUMN bilan TEXT');
    }

    return $pdo;
}
