import axios from 'axios'

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
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auditron_token')
      localStorage.removeItem('auditron_user')
      if (!window.location.pathname.startsWith('/login')) {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  },
)

export default api
