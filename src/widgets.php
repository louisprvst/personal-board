<?php
// Registre des widgets du dashboard.
//
// Ajouter un widget :
//   1. crée src/widgets/mon_widget.php qui définit une fonction widget_mon_widget(): void
//      (elle affiche son HTML, idéalement dans un <section class="widget">) ;
//   2. ajoute 'mon_widget' dans WIDGETS ci-dessous, à la place voulue.

const WIDGETS = [
    'newsletter', // newsletter du jour (pleine largeur)
];

function afficher_widgets(): void
{
    foreach (WIDGETS as $nom) {
        require_once __DIR__ . "/widgets/$nom.php";
        ("widget_$nom")();
    }
}
