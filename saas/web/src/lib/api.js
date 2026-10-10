import axios from 'axios'
import { codeEtablissement, oublierEtablissement } from './tenant'

// Une seule URL pour tous les établissements abonnés : l'API est unique, et
// l'établissement est désigné par l'en-tête `X-Tenant` ajouté ci-dessous.
// En dev, '/api' passe par le proxy Vite (vite.config.js) vers l'API distante.
// En prod, le build est servi en statique sans proxy : il faut l'URL absolue,
// injectée à la compilation via VITE_API_BASE_URL (voir .env.production).
const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auditron_token')
  if (token) config.headers.Authorization = `Bearer ${token}`

  const etablissement = codeEtablissement()
  if (etablissement) config.headers['X-Tenant'] = etablissement

  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const statut = error.response?.status
    const motif = error.response?.data?.erreur

    if (statut === 401) {
      localStorage.removeItem('auditron_token')
      localStorage.removeItem('auditron_user')
      redirigerVersConnexion()
    }

    // 402 : abonnement suspendu. 404 + `etablissement_inconnu` : code devenu
    // invalide (établissement supprimé, ou code saisi à la main erroné). Dans
    // les deux cas, rester sur l'application n'a plus de sens — et garder le
    // code en mémoire enfermerait l'utilisateur dans une erreur qu'il ne peut
    // pas corriger depuis l'interface.
    if (statut === 402 || motif === 'etablissement_inconnu' || motif === 'etablissement_absent') {
      const message =
        statut === 402
          ? "L’abonnement de cet établissement est suspendu. Contactez Auditron."
          : 'Établissement inconnu : vérifiez le code de votre établissement.'

      oublierEtablissement()
      redirigerVersConnexion(message)
    }

    return Promise.reject(error)
  },
)

function redirigerVersConnexion(message = null) {
  if (window.location.pathname.startsWith('/login')) return

  const suffixe = message ? `?motif=${encodeURIComponent(message)}` : ''
  window.location.href = `/login${suffixe}`
}

export default api
