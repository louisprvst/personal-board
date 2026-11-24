// Service worker : reçoit les notifications push et les affiche.
// Pas de cache hors ligne : le dashboard est toujours chargé depuis le serveur.

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// Message envoyé par src/push.php : {"titre", "texte", "url"}.
self.addEventListener('push', (event) => {
    let message = { titre: 'Board', texte: '', url: '/' };
    try {
        message = { ...message, ...event.data.json() };
    } catch (e) {
        // message vide ou illisible : on affiche quand même une notification générique
    }
    event.waitUntil(self.registration.showNotification(message.titre, {
        body: message.texte,
        icon: '/img/icone-192.png',
        badge: '/img/icone-192.png',
        data: { url: message.url },
    }));
});

// Appui sur la notification : ouvre la page concernée (ou réutilise une fenêtre déjà ouverte).
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url || '/', self.location.origin).href;
    event.waitUntil((async () => {
        const fenetres = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const fenetre of fenetres) {
            if ('focus' in fenetre) {
                await fenetre.focus();
                return fenetre.navigate(url);
            }
        }
        return self.clients.openWindow(url);
    })());
});
