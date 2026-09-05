import { useEffect, useState } from 'react'
import DataTable from '../components/DataTable'
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

      <DataTable
        loading={loading}
        emptyMessage="Aucun cours ce jour."
        rows={cours}
        idKey="emploi_du_temps_id"
        columns={[
          { key: 'horaire', label: 'Horaire', render: (c) => `${c.heure_debut} – ${c.heure_fin}`, sortValue: (c) => c.heure_debut },
          { key: 'enseignant', label: 'Enseignant' },
          { key: 'classe', label: 'Classe' },
          { key: 'discipline', label: 'Discipline' },
          {
            key: 'status',
            label: 'Statut',
            render: (c) => (
              <span className={c.status === 'fait' ? 'text-green-600' : 'text-ink-300'}>
                {c.status === 'fait' ? 'Fait' : 'Non fait'}
              </span>
            ),
            searchValue: (c) => (c.status === 'fait' ? 'Fait' : 'Non fait'),
            sortValue: (c) => c.status,
          },
        ]}
        renderActions={(c) => (
          <button onClick={() => toggle(c)} className="text-ink-500 hover:text-ink-900">
            Basculer
          </button>
        )}
      />
    </div>
  )
}
