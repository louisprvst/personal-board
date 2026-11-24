<?php
// Widget : compte à rebours avant la course visée par le plan d'entraînement.
// N'affiche rien tant qu'aucune course n'est indiquée dans le plan.

function widget_course(): void
{
    $course = course_visee();
    if (!$course) return;
    ?>
    <a class="carte lien" href="/entrainement.php">
        <p class="carte-titre"><?= icone('drapeau', 16) ?> <?= e($course['nom'] ?? 'Course') ?></p>
        <p class="grand-chiffre"><?= $course['jours'] === 0 ? 'Jour J' : $course['jours'] ?></p>
        <p class="meta bas"><?= $course['jours'] === 0 ? "C'est aujourd'hui" : e(date_longue($course['date'], false)) ?></p>
    </a>
    <?php
}
