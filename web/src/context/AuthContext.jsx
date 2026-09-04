import { createContext, useContext, useEffect, useState } from 'react'
import api from '../lib/api'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => {
    const raw = localStorage.getItem('auditron_user')
    if (!raw) return null
    try {
      return JSON.parse(raw)
    } catch {
      // Valeur corrompue (ex: "undefined" littéral stocké par erreur) : on
      // repart d'un état déconnecté plutôt que de faire planter le rendu.
      localStorage.removeItem('auditron_user')
      return null
    }
  })
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const token = localStorage.getItem('auditron_token')
    if (!token) {
      setLoading(false)
      return
    }
    api
      .get('/me')
      .then(({ data }) => {
        if (!data) throw new Error('/me a renvoyé une réponse vide')
        setUser(data)
        localStorage.setItem('auditron_user', JSON.stringify(data))
      })
      .catch(() => {
        localStorage.removeItem('auditron_token')
        localStorage.removeItem('auditron_user')
        setUser(null)
      })
      .finally(() => setLoading(false))
  }, [])

  async function login(email, password) {
    const { data } = await api.post('/login', { email, password })
    if (!data?.token || !data?.user) throw new Error('/login a renvoyé une réponse invalide')
    localStorage.setItem('auditron_token', data.token)
    localStorage.setItem('auditron_user', JSON.stringify(data.user))
    setUser(data.user)
  }

  async function logout() {
    try {
      await api.post('/logout')
    } finally {
      localStorage.removeItem('auditron_token')
      localStorage.removeItem('auditron_user')
      setUser(null)
    }
  }

  /** Accréditation à périmètre total (groupe '*') — direction/administration. */
  const isAccesTotal = !user?.accreditation || user.accreditation.groupe === '*'

  return (
    <AuthContext.Provider value={{ user, loading, login, logout, isAccesTotal }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  return useContext(AuthContext)
}
