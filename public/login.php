<?php
// Page de connexion.

require_once __DIR__ . '/../src/layout.php';

// On ne redirige que vers une adresse interne (« / » mais pas « // »).
$retour = $_GET['retour'] ?? '/';
if (!str_starts_with($retour, '/') || str_starts_with($retour, '//')) {
    $retour = '/';
}

if (est_connecte()) {
    header('Location: ' . $retour);
    exit;
}

$erreur = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (connecter($_POST['mot_de_passe'] ?? '')) {
        header('Location: ' . $retour);
        exit;
    }
    $erreur = true;
}

entete('Connexion', false);
?>
<section class="carte connexion">
    <p class="logo">Board</p>
    <?php if ($erreur): ?>
        <p class="erreur">Mot de passe incorrect.</p>
    <?php endif; ?>
    <form method="post">
        <label>Mot de passe
            <input type="password" name="mot_de_passe" required autofocus
                   <?= $erreur ? 'aria-invalid="true"' : '' ?>>
        </label>
        <button type="submit">Entrer</button>
    </form>
</section>
<?php pied();
