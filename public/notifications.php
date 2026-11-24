<?php
// Activation des notifications push sur l'appareil (logique dans public/notifications.js).

require_once __DIR__ . '/../src/layout.php';
require_once __DIR__ . '/../src/push.php';
exiger_connexion();

entete('Notifications');
?>
<header class="entete-page">
    <p class="surtitre"><?= icone('cloche', 16) ?> Réglages</p>
    <h1>Notifications</h1>
</header>

<section class="carte" id="notifications" data-cle="<?= e(secret('VAPID_PUBLIC')) ?>">
    <?php if (!push_configure()): ?>
        <p class="erreur">Les notifications ne sont pas configurées sur le serveur (clés VAPID manquantes).</p>
    <?php else: ?>
        <p id="etat" class="etat-notif">Vérification…</p>
        <div class="boutons">
            <button type="button" id="activer" hidden>Activer sur cet appareil</button>
            <button type="button" id="tester" hidden>Envoyer une notification de test</button>
            <button type="button" id="desactiver" class="secondaire" hidden>Désactiver sur cet appareil</button>
        </div>
    <?php endif; ?>
</section>

<section class="carte aide">
    <h2>Tu seras prévenu quand</h2>
    <ul>
        <li>la newsletter du matin est publiée ;</li>
        <li>ton coach publie le plan d'une nouvelle semaine ;</li>
        <li>ton coach publie le bilan de ta semaine.</li>
    </ul>
    <h2>Sur iPhone</h2>
    <p>Les notifications ne fonctionnent que depuis l'icône de l'écran d'accueil (iOS 16.4 ou plus récent) :
        dans Safari, Partager → <em>Sur l'écran d'accueil</em>, puis ouvre Board depuis l'icône.
        Pour les régler ensuite : Réglages → Notifications → Board.</p>
</section>

<script src="/notifications.js?v=<?= filemtime(__DIR__ . '/notifications.js') ?>" defer></script>
<?php pied();
