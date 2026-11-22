<?php
// Accueil : une grille de cases (widgets), dans l'ordre défini par src/widgets.php.

require_once __DIR__ . '/../src/layout.php';
require_once __DIR__ . '/../src/widgets.php';
exiger_connexion();

entete('Accueil', true, true);
?>
<header class="accueil">
    <p class="meta"><?= e(ucfirst(date_longue(date('Y-m-d')))) ?></p>
    <h1><?= (int)date('G') < 18 ? 'Bonjour' : 'Bonsoir' ?></h1>
</header>
<div class="grille">
    <?php afficher_widgets(); ?>
</div>
<?php pied();
