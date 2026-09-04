import api from './api'

/** Télécharge un fichier depuis l'API authentifiée (Bearer token) — une navigation classique ne l'enverrait pas. */
export async function downloadFile(url, filename) {
  const response = await api.get(url, { responseType: 'blob' })
  const objectUrl = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = objectUrl
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(objectUrl)
}
