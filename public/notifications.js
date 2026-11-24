// Abonnement de l'appareil aux notifications push (page /notifications.php).

const zone = document.getElementById('notifications');
const etat = document.getElementById('etat');
const boutons = {
    activer: document.getElementById('activer'),
    tester: document.getElementById('tester'),
    desactiver: document.getElementById('desactiver'),
};

// Clé VAPID publique (base64url) → octets, format attendu par pushManager.subscribe.
function octets(base64url) {
    const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - base64url.length % 4) % 4);
    return Uint8Array.from(atob(base64), (c) => c.charCodeAt(0));
}

async function api(corps) {
    const reponse = await fetch('/api/push.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(corps),
    });
    const donnees = await reponse.json().catch(() => ({}));
    if (!reponse.ok) throw new Error(donnees.erreur || `Erreur ${reponse.status}`);
    return donnees;
}

function afficher(message, visibles = []) {
    etat.textContent = message;
    for (const [nom, bouton] of Object.entries(boutons)) bouton.hidden = !visibles.includes(nom);
}

async function enregistrement() {
    await navigator.serviceWorker.register('/sw.js');
    return navigator.serviceWorker.ready;
}

async function rafraichir() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        const ios = /iPhone|iPad/.test(navigator.userAgent);
        afficher(ios
            ? "Ouvre Board depuis l'icône de l'écran d'accueil pour activer les notifications (voir plus bas)."
            : 'Ce navigateur ne prend pas en charge les notifications push.');
        return;
    }
    if (Notification.permission === 'denied') {
        afficher("Les notifications sont bloquées pour Board. Autorise-les dans les réglages de l'appareil ou du navigateur.");
        return;
    }
    const abonnement = await (await enregistrement()).pushManager.getSubscription();
    if (abonnement) {
        await api({ action: 'abonner', abonnement: abonnement.toJSON() }); // resynchronise le serveur
        afficher('✓ Notifications activées sur cet appareil.', ['tester', 'desactiver']);
    } else {
        afficher('Notifications désactivées sur cet appareil.', ['activer']);
    }
}

boutons.activer?.addEventListener('click', async () => {
    try {
        // Sur iPhone, la demande d'autorisation doit suivre directement un appui.
        if (await Notification.requestPermission() !== 'granted') return rafraichir();
        const reg = await enregistrement();
        const abonnement = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: octets(zone.dataset.cle),
        });
        await api({ action: 'abonner', abonnement: abonnement.toJSON() });
        await rafraichir();
    } catch (e) {
        afficher(`Impossible d'activer les notifications : ${e.message}`, ['activer']);
    }
});

boutons.tester?.addEventListener('click', async () => {
    try {
        const { appareils } = await api({ action: 'tester' });
        afficher(`Notification envoyée à ${appareils} appareil${appareils > 1 ? 's' : ''}.`, ['tester', 'desactiver']);
    } catch (e) {
        afficher(`Échec de l'envoi : ${e.message}`, ['tester', 'desactiver']);
    }
});

boutons.desactiver?.addEventListener('click', async () => {
    try {
        const abonnement = await (await enregistrement()).pushManager.getSubscription();
        if (abonnement) {
            await api({ action: 'desabonner', endpoint: abonnement.endpoint });
            await abonnement.unsubscribe();
        }
        await rafraichir();
    } catch (e) {
        afficher(`Impossible de désactiver : ${e.message}`, ['tester', 'desactiver']);
    }
});

if (etat) rafraichir().catch((e) => afficher(`Erreur : ${e.message}`));
