import { useEffect, useState } from 'react'
import api from '../lib/api'

export default function ValidationPage() {
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10))
  const [cours, setCours] = useState([])
  const [loading, setLoading] = useState(true)

  function load() {
    setLoading(true)
    api.get('/presences/validation', { params: { date } }).then(({ data }) => {
      setCours(Array.isArray(data.cours) ? data.cours : [])
      setLoading(false)
    })
  }

  useEffect(load, [date])

  async function toggle(c) {
    await api.post('/presences/validation/toggle', { emploi_du_temps_id: c.emploi_du_temps_id, date })
    load()
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-lg font-semibold text-ink-900">Validation des présences</h1>
        <input type="date" value={date} onChange={(e) => setDate(e.target.value)} className="rounded-md border border-ink-100 px-3 py-1.5 text-sm" />
      </div>

      <div className="overflow-x-auto rounded-lg border border-ink-100 bg-white">
        <table className="min-w-full divide-y divide-ink-100 text-sm">
          <thead className="bg-ink-50">
            <tr>
              <th className="px-4 py-2 text-left font-medium text-ink-500">Horaire</th>
              <th className="px-4 py-2 text-left font-medium text-ink-500">Enseignant</th>
              <th className="px-4 py-2 text-left font-medium text-ink-500">Classe</th>
              <th className="px-4 py-2 text-left font-medium text-ink-500">Discipline</th>
              <th className="px-4 py-2 text-left font-medium text-ink-500">Statut</th>
              <th className="px-4 py-2" />
            </tr>
          </thead>
          <tbody className="divide-y divide-ink-100">
            {loading && (
              <tr><td colSpan={6} className="px-4 py-6 text-center text-ink-300">Chargement…</td></tr>
            )}
            {!loading && cours.length === 0 && (
              <tr><td colSpan={6} className="px-4 py-6 text-center text-ink-300">Aucun cours ce jour.</td></tr>
            )}
            {cours.map((c) => (
              <tr key={c.emploi_du_temps_id} className="hover:bg-ink-50">
                <td className="px-4 py-2">{c.heure_debut} – {c.heure_fin}</td>
                <td className="px-4 py-2">{c.enseignant}</td>
                <td className="px-4 py-2">{c.classe}</td>
                <td className="px-4 py-2">{c.discipline}</td>
                <td className="px-4 py-2">
                  <span className={c.status === 'fait' ? 'text-green-600' : 'text-ink-300'}>
                    {c.status === 'fait' ? 'Fait' : 'Non fait'}
                  </span>
                </td>
                <td className="px-4 py-2 text-right">
                  <button onClick={() => toggle(c)} className="text-ink-500 hover:text-ink-900">
                    Basculer
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
