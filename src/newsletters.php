<?php
// Newsletters du matin, publiées par la routine Claude via public/api/newsletter.php.
// Une par jour : republier à la même date remplace la précédente.

require_once __DIR__ . '/db.php';

// Seules les plus récentes sont gardées : les autres sont supprimées à chaque publication.
const NEWSLETTERS_GARDEES = 7;

// Enregistre la newsletter et renvoie le nombre d'anciennes supprimées.
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

// Date de la newsletter précédente ou suivante, pour la navigation.
function date_voisine(string $date, bool $suivante): ?string
{
    $stmt = db()->prepare($suivante
        ? 'SELECT date FROM newsletters WHERE date > ? ORDER BY date ASC LIMIT 1'
        : 'SELECT date FROM newsletters WHERE date < ? ORDER BY date DESC LIMIT 1');
    $stmt->execute([$date]);
    return $stmt->fetchColumn() ?: null;
}

function toutes_les_newsletters(): array
{
    return db()->query('SELECT date, titre FROM newsletters ORDER BY date DESC')->fetchAll();
}

// Temps de lecture estimé (230 mots / minute), minimum 1 minute.
function temps_lecture(string $texte): int
{
    return max(1, (int)round(str_word_count($texte) / 230));
}
