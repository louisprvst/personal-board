<?php
// Widget : la newsletter du jour, ou la plus récente s'il n'y en a pas encore aujourd'hui.

function widget_newsletter(): void
{
    $aujourdhui = date('Y-m-d');
    $n = newsletter_du($aujourdhui) ?? derniere_newsletter();

    if (!$n) {
        echo '<section class="widget vide"><h2>Pas encore de newsletter</h2>'
           . '<p class="meta">La routine Claude n\'a encore rien publié. Elle apparaîtra ici.</p></section>';
        return;
    }

    if ($n['date'] !== $aujourdhui) {
        echo '<p class="bandeau">Pas encore de newsletter aujourd\'hui : voici la dernière reçue.</p>';
    }
    afficher_newsletter($n);
}
