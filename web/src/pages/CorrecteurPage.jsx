import { useEffect, useState } from 'react'
import DataTable from '../components/DataTable'
import api from '../lib/api'
import Modal from '../components/Modal'

const LABELS = {
  depart_manquant: 'Départ non pointé',
  pointage_manquant: 'Aucun pointage (cours prévu)',
}

export default function CorrecteurPage() {
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10))
  const [anomalies, setAnomalies] = useState([])
  const [loading, setLoading] = useState(true)
  const [correction, setCorrection] = useState(null)
  const [form, setForm] = useState({ heure_arrivee: '', heure_depart: '', motif: '' })

  function load() {
    setLoading(true)
    api.get('/presences/anomalies', { params: { date } }).then(({ data }) => {
      setAnomalies(Array.isArray(data.anomalies) ? data.anomalies : [])
      setLoading(false)
    })
  }

  useEffect(load, [date])

  function openCorrection(anomalie) {
    setForm({ heure_arrivee: '', heure_depart: '', motif: '' })
    setCorrection(anomalie)
  }

  async function submit(e) {
    e.preventDefault()
    await api.post('/presences/corriger', {
      corrections: [
        {
          enseignant_id: correction.enseignant_id,
          date,
          heure_arrivee: form.heure_arrivee || undefined,
          heure_depart: form.heure_depart || undefined,
          motif: form.motif,
        },
      ],
    })
    setCorrection(null)
    load()
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-lg font-semibold text-ink-900">Correcteur de présences</h1>
        <input type="date" value={date} onChange={(e) => setDate(e.target.value)} className="rounded-md border border-ink-100 px-3 py-1.5 text-sm" />
      </div>

      <DataTable
        loading={loading}
        emptyMessage="Aucune anomalie ce jour."
        rows={anomalies}
        getRowKey={(a, i) => i}
        columns={[
          { key: 'nom', label: 'Enseignant' },
          { key: 'type', label: 'Anomalie', render: (a) => LABELS[a.type] ?? a.type },
        ]}
        renderActions={(a) => (
          <button onClick={() => openCorrection(a)} className="text-ink-500 hover:text-ink-900">
            Corriger
          </button>
        )}
      />

      {correction && (
        <Modal title={`Corriger — ${correction.nom}`} onClose={() => setCorrection(null)}>
          <form onSubmit={submit}>
            <label className="mb-3 block text-sm">
              <span className="mb-1 block text-ink-700">Heure d’arrivée</span>
              <input
                type="time"
                value={form.heure_arrivee}
                onChange={(e) => setForm((f) => ({ ...f, heure_arrivee: e.target.value }))}
                className="w-full rounded-md border border-ink-100 px-3 py-2"
              />
            </label>
            <label className="mb-3 block text-sm">
              <span className="mb-1 block text-ink-700">Heure de départ</span>
              <input
                type="time"
                value={form.heure_depart}
                onChange={(e) => setForm((f) => ({ ...f, heure_depart: e.target.value }))}
                className="w-full rounded-md border border-ink-100 px-3 py-2"
              />
            </label>
            <label className="mb-4 block text-sm">
              <span className="mb-1 block text-ink-700">Motif (obligatoire)</span>
              <input
                required
                value={form.motif}
                onChange={(e) => setForm((f) => ({ ...f, motif: e.target.value }))}
                className="w-full rounded-md border border-ink-100 px-3 py-2"
              />
            </label>
            <button type="submit" className="w-full rounded-md bg-brand-700 py-2 text-sm font-medium text-white hover:bg-brand-800">
              Enregistrer la correction
            </button>
          </form>
        </Modal>
      )}
    </div>
  )
}
