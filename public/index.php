<?php
// Dashboard : affiche les widgets dans l'ordre défini par src/widgets.php.

require_once __DIR__ . '/../src/layout.php';
require_once __DIR__ . '/../src/widgets.php';
exiger_connexion();

entete('Aujourd\'hui');
afficher_widgets();
pied();
