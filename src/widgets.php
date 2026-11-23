<?php
// Registre des widgets de l'accueil (grille « bento »).
//
// Ajouter un widget :
//   1. crée src/widgets/mon_widget.php avec une fonction widget_mon_widget(): void
//      qui affiche le contenu de la case : une <section class="carte"> ou, pour une case
//      cliquable, un <a class="carte lien">. Si elle n'affiche rien, la case est retirée ;
//   2. ajoute-le dans WIDGETS, à la place voulue, avec sa taille :
//      'petit' (1 × 1), 'large' (2 × 1) ou 'grand' (2 × 2), en colonnes × lignes.

const WIDGETS = [
    'bonjour'      => 'large',  // salutation et date
    'course'       => 'petit',  // compte à rebours avant la course visée
    'semaine'      => 'petit',  // la semaine d'entraînement en un coup d'œil
    'entrainement' => 'grand',  // séance du jour
    'newsletter'   => 'grand',  // newsletter du jour
];

function afficher_widgets(): void
{
    foreach (WIDGETS as $nom => $taille) {
        require_once __DIR__ . "/widgets/$nom.php";
        ob_start();
        ("widget_$nom")();
        $html = trim(ob_get_clean());
        if ($html !== '') {
            echo "<div class=\"tuile $taille\">$html</div>\n";
        }
    }
}
