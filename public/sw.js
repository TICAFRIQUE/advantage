/*
 * Service worker ADVANTAGE : rend l'application installable sur mobile.
 *
 * Aucune page ni donnée n'est mise en cache : les pages authentifiées sont en
 * « no-store » (données personnelles, jetons CSRF) et la plateforme exige une
 * connexion. Seule la page « hors ligne » est conservée, affichée quand le
 * réseau manque au lieu de l'erreur du navigateur.
 */
const CACHE = 'advantage-v1';
const HORS_LIGNE = '/hors-ligne.html';

self.addEventListener('install', (evenement) => {
    evenement.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.addAll([HORS_LIGNE, '/images/icones/icone-192.png']))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (evenement) => {
    evenement.waitUntil(
        caches.keys()
            .then((cles) => Promise.all(cles.filter((cle) => cle !== CACHE).map((cle) => caches.delete(cle))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (evenement) => {
    // Navigation uniquement : toujours le réseau, la page « hors ligne » en secours.
    if (evenement.request.mode !== 'navigate' || evenement.request.method !== 'GET') {
        return;
    }

    evenement.respondWith(fetch(evenement.request).catch(() => caches.match(HORS_LIGNE)));
});
