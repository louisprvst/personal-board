<?php
// Widget : salutation et date du jour.

function widget_bonjour(): void
{
    $heure = (int)date('G');
    $salut = $heure < 5 || $heure >= 18 ? 'Bonsoir' : 'Bonjour';
    ?>
    <section class="carte bonjour">
        <p class="meta"><?= e(ucfirst(date_longue(date('Y-m-d')))) ?></p>
        <h1><?= $salut ?></h1>
    </section>
    <?php
}
