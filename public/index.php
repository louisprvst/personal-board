<?php
// Accueil : la grille de widgets définie dans src/widgets.php.

require_once __DIR__ . '/../src/layout.php';
require_once __DIR__ . '/../src/widgets.php';
exiger_connexion();

entete('Accueil', large: true);
?>
<div class="bento">
    <?php afficher_widgets(); ?>
</div>
<?php pied();
