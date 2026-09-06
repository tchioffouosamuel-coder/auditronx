const DB_NAME = 'auditron-px'
const STORE = 'auth'

function openDb() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, 1)
    req.onupgradeneeded = () => req.result.createObjectStore(STORE)
    req.onsuccess = () => resolve(req.result)
    req.onerror = () => reject(req.error)
  })
}

/**
 * Miroir du token d'auth dans IndexedDB, en plus de localStorage (voir
 * AuthContext) : c'est le seul stockage accessible à un service worker
 * (public/firebase-messaging-sw.js), qui en a besoin pour appeler l'API
 * directement depuis `notificationclick` — Valider/Refuser une demande
 * d'activation (§otp-approval) doit marcher même onglet/app fermé.
 */
export async function saveAuthToken(token) {
  try {
    const db = await openDb()
    db.transaction(STORE, 'readwrite').objectStore(STORE).put(token, 'token')
  } catch {
    // Best-effort : au pire l'action depuis la notification échoue et
    // l'admin agit depuis l'onglet backoffice (tableau de secours).
  }
}

export async function clearAuthToken() {
  try {
    const db = await openDb()
    db.transaction(STORE, 'readwrite').objectStore(STORE).delete('token')
  } catch {
    // Best-effort, cf. saveAuthToken.
  }
}
