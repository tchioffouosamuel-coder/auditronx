// Service worker FCM — notifications de validation OTP (§otp-approval).
//
// Un service worker ne peut pas lire les variables d'env Vite (import.meta.env) :
// les valeurs ci-dessous doivent être tenues à jour à la main, en miroir de
// web/.env* (VITE_FIREBASE_*) et VITE_API_BASE_URL. Ce ne sont pas des secrets
// (identifiants publics du projet Firebase, cf. doc Firebase), donc les coder
// en dur ici est sans risque.
importScripts('https://www.gstatic.com/firebasejs/11.0.2/firebase-app-compat.js')
importScripts('https://www.gstatic.com/firebasejs/11.0.2/firebase-messaging-compat.js')

firebase.initializeApp({
  apiKey: 'AIzaSyAPYw3lrz4AoI5z8-qr8f-Hc5TLho96V8g', // Firebase console > Paramètres du projet > Général > Vos applications > Web
  authDomain: 'auditronx.firebaseapp.com',
  projectId: 'auditronx',
  messagingSenderId: '1026215057369',
  appId: '1:1026215057369:web:b73412634a5c5c0a256bc8',
})

const API_BASE_URL = 'https://api-ltm.auditronx.com/public/api' // = VITE_API_BASE_URL (voir web/.env.production)

const messaging = firebase.messaging()

// Message *data-only* (voir PushNotificationService::sendToAdmins côté API) :
// FCM ne l'affiche pas tout seul, on construit la notification nous-mêmes
// pour pouvoir y ajouter les actions Valider/Refuser (façon "activité
// suspecte" Google) — un message avec bloc `notification` classique ne
// supporte pas de boutons d'action.
messaging.onBackgroundMessage((payload) => {
  const data = payload.data || {}
  if (data.type !== 'otp_approval') return

  self.registration.showNotification(data.title || "Demande d'activation", {
    body: data.body || `${data.enseignant_nom} demande un code d'accès — code : ${data.code}`,
    icon: '/logo.png',
    tag: `otp-approval-${data.activation_request_id}`,
    data,
    requireInteraction: true,
    actions: [
      { action: 'approve', title: 'Valider' },
      { action: 'reject', title: 'Refuser' },
    ],
  })
})

// Clic sur Valider/Refuser : appelle l'API directement depuis le service
// worker, sans passer par une page — ça doit marcher même onglet/app fermés
// (§otp-approval). Le token d'auth est relu depuis IndexedDB (un service
// worker n'a pas accès à localStorage) ; voir src/lib/authTokenStore.js, tenu
// à jour par AuthContext à chaque connexion/déconnexion.
self.addEventListener('notificationclick', (event) => {
  const data = event.notification.data || {}
  event.notification.close()

  if (!['approve', 'reject'].includes(event.action) || !data.activation_request_id) return

  event.waitUntil(
    getStoredAuthToken().then((token) => {
      if (!token) return
      return fetch(`${API_BASE_URL}/devices/activation-requests/${data.activation_request_id}/${event.action}`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
      })
    }),
  )
})

function getStoredAuthToken() {
  return new Promise((resolve) => {
    const req = indexedDB.open('auditron-px', 1)
    req.onupgradeneeded = () => req.result.createObjectStore('auth')
    req.onsuccess = () => {
      try {
        const getReq = req.result.transaction('auth', 'readonly').objectStore('auth').get('token')
        getReq.onsuccess = () => resolve(getReq.result || null)
        getReq.onerror = () => resolve(null)
      } catch {
        resolve(null)
      }
    }
    req.onerror = () => resolve(null)
  })
}
