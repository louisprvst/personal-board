<?php
// Plan d'entraînement : /entrainement.php (semaine en cours) ou ?lundi=AAAA-MM-JJ.

require_once __DIR__ . '/../src/layout.php';
exiger_connexion();

$aujourdhui = date('Y-m-d');
$courant = lundi_de($aujourdhui);
$demande = $_GET['lundi'] ?? '';
$lundi = preg_match('/^\d{4}-\d{2}-\d{2}$/', $demande) ? lundi_de($demande) : $courant;

$semaine = semaine_entrainement($lundi);
$precedent = decaler($lundi, -7);
$suivant = decaler($lundi, 7);
$a_precedente = $precedent >= $courant && semaine_entrainement($precedent);
$a_suivante = (bool)semaine_entrainement($suivant);
$course = course_visee();

entete('Entraînement');
?>
<header class="entete-page">
    <p class="surtitre"><?= icone('course', 16) ?> Entraînement
        <?php if ($course): ?> · J-<?= $course['jours'] ?><?= $course['nom'] ? ' · ' . e($course['nom']) : '' ?><?php endif; ?></p>
    <h1><?= $lundi === $courant ? 'Cette semaine' : ($lundi > $courant ? 'Semaine suivante' : 'Semaine passée') ?></h1>
    <p class="meta">Du <?= e(date_longue($lundi)) ?> au <?= e(date_longue(decaler($lundi, 6))) ?></p>
</header>

<?php if (!$semaine): ?>
    <section class="carte vide">
        <h2>Aucun plan publié</h2>
        <p class="meta">Demande à ton chat Claude de publier la semaine avec le connecteur du dashboard.</p>
    </section>
<?php else: ?>
    <?php if ($semaine['objectif'] !== ''): ?>
        <p class="objectif-semaine"><?= e($semaine['objectif']) ?></p>
    <?php endif; ?>

    <ol class="jours">
        <?php foreach (seances_par_jour($semaine, $lundi) as $date => $seances): ?>
            <?php $etat = $date === $aujourdhui ? 'aujourdhui' : ($date < $aujourdhui ? 'passe' : ''); ?>
            <li class="jour <?= $etat ?>">
                <p class="jour-date">
                    <?= e(ucfirst(jour_semaine($date))) ?>
                    <span><?= e(date('d/m', strtotime($date))) ?></span>
                    <?php if ($etat === 'aujourdhui'): ?><span class="pastille">Aujourd'hui</span><?php endif; ?>
                </p>
                <?php if (!$seances): ?>
                    <p class="jour-type repos">—</p>
                <?php endif; ?>
                <?php foreach ($seances as $s): ?>
                    <p class="jour-type <?= est_repos($s) ? 'repos' : '' ?>"><?= e($s['type']) ?></p>
                    <?php if ($s['detail'] !== ''): ?><p class="detail-seance"><?= e($s['detail']) ?></p><?php endif; ?>
                <?php endforeach; ?>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<footer class="navigation">
    <?php if ($a_precedente): ?>
        <a href="/entrainement.php?lundi=<?= e($precedent) ?>"><?= icone('gauche', 16) ?> Semaine précédente</a>
    <?php else: ?><span></span><?php endif; ?>
    <?php if ($a_suivante): ?>
        <a href="/entrainement.php?lundi=<?= e($suivant) ?>">Semaine suivante <?= icone('droite', 16) ?></a>
    <?php endif; ?>
</footer>
<?php pied();
