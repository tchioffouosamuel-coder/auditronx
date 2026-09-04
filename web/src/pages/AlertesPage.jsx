import { useEffect, useState } from 'react'
import api from '../lib/api'

export default function AlertesPage() {
  const [alertes, setAlertes] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    api.get('/absences/alertes').then(({ data }) => {
      setAlertes(data.data ?? [])
      setLoading(false)
    })
  }, [])

  return (
    <div>
      <h1 className="mb-4 text-lg font-semibold text-ink-900">Alertes d’absences répétées</h1>
      <div className="overflow-x-auto rounded-lg border border-ink-100 bg-white">
        <table className="min-w-full divide-y divide-ink-100 text-sm">
          <thead className="bg-ink-50">
            <tr>
              <th className="px-4 py-2 text-left font-medium text-ink-500">Enseignant</th>
              <th className="px-4 py-2 text-left font-medium text-ink-500">Envoyée le</th>
              <th className="px-4 py-2 text-left font-medium text-ink-500">Canal</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-ink-100">
            {loading && (
              <tr><td colSpan={3} className="px-4 py-6 text-center text-ink-300">Chargement…</td></tr>
            )}
            {!loading && alertes.length === 0 && (
              <tr><td colSpan={3} className="px-4 py-6 text-center text-ink-300">Aucune alerte.</td></tr>
            )}
            {alertes.map((a) => (
              <tr key={a.id} className="hover:bg-ink-50">
                <td className="px-4 py-2">{a.enseignant?.nom}</td>
                <td className="px-4 py-2">{new Date(a.sent_at).toLocaleString('fr-FR')}</td>
                <td className="px-4 py-2">{a.canal}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
