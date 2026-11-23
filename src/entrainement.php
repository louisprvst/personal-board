<?php
// Plan de course à pied, publié par le chat Claude via le connecteur MCP (public/mcp.php).
// Une ligne par semaine (clé : le lundi). Seules la semaine en cours et les suivantes
// sont gardées : les semaines terminées sont supprimées à chaque publication.

require_once __DIR__ . '/db.php';

// Lundi de la semaine qui contient $date.
function lundi_de(string $date): string
{
    return (new DateTimeImmutable($date))->modify('monday this week')->format('Y-m-d');
}

// $date décalée de $jours jours (négatif pour reculer).
function decaler(string $date, int $jours): string
{
    return (new DateTimeImmutable($date))->modify("$jours days")->format('Y-m-d');
}

function enregistrer_semaine(string $lundi, string $objectif, array $seances, ?string $course_nom, ?string $course_date): void
{
    db()->prepare('INSERT INTO semaines_entrainement (lundi, objectif, seances, course_nom, course_date)
        VALUES (?, ?, ?, ?, ?)
        ON CONFLICT(lundi) DO UPDATE SET objectif = excluded.objectif, seances = excluded.seances,
        course_nom = excluded.course_nom, course_date = excluded.course_date, modifie_le = CURRENT_TIMESTAMP')
        ->execute([$lundi, $objectif, json_encode($seances, JSON_UNESCAPED_UNICODE), $course_nom, $course_date]);

    db()->prepare('DELETE FROM semaines_entrainement WHERE lundi < ?')->execute([lundi_de(date('Y-m-d'))]);
}

// La semaine qui commence le lundi $lundi, séances décodées, ou null.
function semaine_entrainement(string $lundi): ?array
{
    $stmt = db()->prepare('SELECT * FROM semaines_entrainement WHERE lundi = ?');
    $stmt->execute([$lundi]);
    $s = $stmt->fetch();
    if (!$s) return null;
    $s['seances'] = json_decode($s['seances'], true) ?: [];
    return $s;
}

// Séances d'une semaine groupées par jour, du lundi au dimanche : [date => [séances]].
function seances_par_jour(?array $semaine, string $lundi): array
{
    $jours = [];
    for ($i = 0; $i < 7; $i++) {
        $jours[decaler($lundi, $i)] = [];
    }
    foreach ($semaine['seances'] ?? [] as $s) {
        $jours[$s['date']][] = $s;
    }
    return $jours;
}

// Séances prévues à une date donnée (souvent une seule, parfois aucune).
function seances_du(string $date): array
{
    $lundi = lundi_de($date);
    return seances_par_jour(semaine_entrainement($lundi), $lundi)[$date];
}

function est_repos(array $seance): bool
{
    return stripos($seance['type'], 'repos') === 0;
}

// Course visée par le plan publié : ['nom', 'date', 'jours'] (jours restants), ou null.
function course_visee(): ?array
{
    $s = db()->query('SELECT course_nom, course_date FROM semaines_entrainement
        WHERE course_date IS NOT NULL ORDER BY lundi LIMIT 1')->fetch();
    if (!$s) return null;
    $jours = (int)(new DateTimeImmutable(date('Y-m-d')))->diff(new DateTimeImmutable($s['course_date']))->format('%r%a');
    return $jours < 0 ? null : ['nom' => $s['course_nom'], 'date' => $s['course_date'], 'jours' => $jours];
}
