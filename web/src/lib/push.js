import { initializeApp } from 'firebase/app'
import { getMessaging, getToken, isSupported, onMessage } from 'firebase/messaging'
import api from './api'

const firebaseConfig = {
  apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
  authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
  projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
  messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
  appId: import.meta.env.VITE_FIREBASE_APP_ID,
}

let messagingInstance = null

function getMessagingInstance() {
  if (!messagingInstance) messagingInstance = getMessaging(initializeApp(firebaseConfig))
  return messagingInstance
}

/**
 * Active les notifications de validation OTP (§otp-approval) pour l'admin
 * connecté : permission navigateur, enregistrement du service worker FCM,
 * puis envoi du token à l'API (voir AuthController::updateFcmToken).
 *
 * Best-effort et silencieux : pas de config Fireber/VAPID (voir README), pas
 * de support navigateur, permission refusée... l'admin garde de toute façon
 * le tableau "Demandes d'activation" du backoffice comme secours.
 */
export async function registerAdminPush() {
  if (!import.meta.env.VITE_FIREBASE_API_KEY || !import.meta.env.VITE_FIREBASE_VAPID_KEY) return
  if (!('serviceWorker' in navigator) || !('Notification' in window)) return

  try {
    if (!(await isSupported())) return

    const permission = await Notification.requestPermission()
    if (permission !== 'granted') return

    const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js')
    const messaging = getMessagingInstance()

    const token = await getToken(messaging, {
      vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY,
      serviceWorkerRegistration: registration,
    })
    if (token) await api.post('/me/fcm-token', { fcm_token: token })

    // Onglet au premier plan : FCM n'affiche pas de notification système pour
    // un message data-only (voir PushNotificationService::sendToAdmins côté
    // API) — on la construit nous-mêmes, avec les mêmes actions Valider/Refuser
    // qu'en arrière-plan (firebase-messaging-sw.js).
    onMessage(messaging, (payload) => showApprovalNotification(registration, payload.data))
  } catch (e) {
    console.warn('push: échec activation des notifications admin', e)
  }
}

function showApprovalNotification(registration, data) {
  if (!data || data.type !== 'otp_approval') return

  registration.showNotification(data.title ?? "Demande d'activation", {
    body: data.body ?? `${data.enseignant_nom} demande un code d'accès — code : ${data.code}`,
    icon: '/logo.png',
    tag: `otp-approval-${data.activation_request_id}`,
    data,
    requireInteraction: true,
    actions: [
      { action: 'approve', title: 'Valider' },
      { action: 'reject', title: 'Refuser' },
    ],
  })
}
