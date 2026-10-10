import axios from 'axios'

/**
 * Client du portail éditeur (`/api/central`).
 *
 * Volontairement distinct de `api.js` : pas d'en-tête `X-Tenant` (ces routes
 * travaillent sur la base centrale) et un jeton stocké sous une autre clé, de
 * sorte qu'une session éditeur et une session d'établissement puissent
 * coexister dans le même navigateur sans s'écraser — cas quotidien du support
 * qui reproduit un problème chez un client.
 */
const apiCentral = axios.create({
  baseURL: `${import.meta.env.VITE_API_BASE_URL || '/api'}/central`,
  headers: { Accept: 'application/json' },
})

export const CLE_JETON_PLATEFORME = 'auditron_plateforme_token'
export const CLE_COMPTE_PLATEFORME = 'auditron_plateforme_user'

apiCentral.interceptors.request.use((config) => {
  const token = localStorage.getItem(CLE_JETON_PLATEFORME)
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

apiCentral.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem(CLE_JETON_PLATEFORME)
      localStorage.removeItem(CLE_COMPTE_PLATEFORME)

      if (!window.location.pathname.startsWith('/plateforme/connexion')) {
        window.location.href = '/plateforme/connexion'
      }
    }

    return Promise.reject(error)
  },
)

export default apiCentral
