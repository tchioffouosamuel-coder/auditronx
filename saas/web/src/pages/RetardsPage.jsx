import { useEffect, useState } from 'react'
import DataTable from '../components/DataTable'
import api from '../lib/api'
import { downloadFile } from '../lib/download'
import { endOfMonthIso, startOfMonthIso } from '../lib/datetime'

export default function RetardsPage() {
  const [debut, setDebut] = useState(startOfMonthIso)
  const [fin, setFin] = useState(endOfMonthIso)
  const [tolerance, setTolerance] = useState(10)
  const [lignes, setLignes] = useState([])
  const [loading, setLoading] = useState(true)

  function load() {
    setLoading(true)
    Promise.all([
      api.get('/retards', { params: { debut, fin } }),
      api.get('/retards/parametres'),
    ]).then(([r, p]) => {
      setLignes(Array.isArray(r.data) ? r.data : [])
      setTolerance(p.data.tolerance_minutes)
      setLoading(false)
    })
  }

  useEffect(load, [debut, fin])

  async function saveTolerance() {
    await api.put('/retards/parametres', { tolerance_minutes: Number(tolerance) })
    load()
  }

  function downloadBilanCumule() {
    downloadFile(`/retards/bilan-cumule?debut=${debut}&fin=${fin}`, `bilan-retards-${debut}-${fin}.pdf`)
  }

  return (
    <div>
      <h1 className="page-title mb-4">Retards & bilans</h1>

      <div className="card mb-6 flex flex-wrap items-end gap-4 p-4">
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Début</span>
          <input type="date" value={debut} onChange={(e) => setDebut(e.target.value)} className="field py-2" />
        </label>
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Fin</span>
          <input type="date" value={fin} onChange={(e) => setFin(e.target.value)} className="field py-2" />
        </label>
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Tolérance (minutes)</span>
          <div className="flex gap-2">
            <input
              type="number"
              value={tolerance}
              onChange={(e) => setTolerance(e.target.value)}
              className="field w-24 py-2"
            />
            <button onClick={saveTolerance} className="btn-secondary">
              Enregistrer
            </button>
          </div>
        </label>
        <button onClick={downloadBilanCumule} className="btn-primary ml-auto">
          Télécharger le bilan cumulé (PDF)
        </button>
      </div>

      <DataTable
        loading={loading}
        rows={lignes}
        idKey="enseignant_id"
        columns={[
          { key: 'nom', label: 'Nom' },
          { key: 'matricule', label: 'Matricule' },
          { key: 'section', label: 'Section' },
          { key: 'jours_retard', label: 'Jours de retard' },
          { key: 'minutes_retard_total', label: 'Minutes cumulées' },
        ]}
        renderActions={(l) => (
          <button
            onClick={() => downloadFile(`/retards/bilan/${l.enseignant_id}?debut=${debut}&fin=${fin}`, `bilan-${l.matricule}.pdf`)}
            className="text-ink-500 hover:text-ink-900"
          >
            Fiche PDF
          </button>
        )}
      />
    </div>
  )
}
