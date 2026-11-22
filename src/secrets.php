<?php
// Lit les secrets : variables d'environnement (Railway) ou fichier .env à la racine (en local).
//
// Format du .env : une ligne NOM=valeur. Les guillemets autour de la valeur sont facultatifs.
// La valeur est prise telle quelle (aucune interprétation des « $ »), donc le hash
// du mot de passe se colle directement, et la même valeur se colle ensuite sur Railway.
// Une variable déjà définie dans l'environnement est prioritaire sur le .env.

function charger_env(string $fichier): void
{
    if (!is_file($fichier)) return;
    foreach (file($fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || $ligne[0] === '#' || !str_contains($ligne, '=')) continue;
        [$nom, $valeur] = array_map('trim', explode('=', $ligne, 2));
        if (preg_match('/^([\'"])(.*)\1$/', $valeur, $m)) {
            $valeur = $m[2];
        }
        if (getenv($nom) === false) {
            putenv("$nom=$valeur");
        }
    }
}

charger_env(__DIR__ . '/../.env');

// Renvoie la valeur d'un secret, ou null s'il n'est défini nulle part.
function secret(string $nom): ?string
{
    $valeur = getenv($nom);
    return ($valeur === false || $valeur === '') ? null : $valeur;
}
