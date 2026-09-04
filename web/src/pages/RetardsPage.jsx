import { useEffect, useState } from 'react'
import api from '../lib/api'
import { downloadFile } from '../lib/download'

function startOfMonth() {
  const d = new Date()
  return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10)
}

function endOfMonth() {
  const d = new Date()
  return new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().slice(0, 10)
}

export default function RetardsPage() {
  const [debut, setDebut] = useState(startOfMonth())
  const [fin, setFin] = useState(endOfMonth())
  const [tolerance, setTolerance] = useState(10)
  const [lignes, setLignes] = useState([])
  const [loading, setLoading] = useState(true)

  function load() {
    setLoading(true)
    Promise.all([
      api.get('/retards', { params: { debut, fin } }),
      api.get('/retards/parametres'),
    ]).then(([r, p]) => {
      setLignes(r.data)
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
      <h1 className="mb-4 text-lg font-semibold text-ink-900">Retards & bilans</h1>

      <div className="mb-6 flex flex-wrap items-end gap-4 rounded-lg border border-ink-100 bg-white p-4">
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Début</span>
          <input type="date" value={debut} onChange={(e) => setDebut(e.target.value)} className="rounded-md border border-ink-100 px-3 py-1.5" />
        </label>
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Fin</span>
          <input type="date" value={fin} onChange={(e) => setFin(e.target.value)} className="rounded-md border border-ink-100 px-3 py-1.5" />
        </label>
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Tolérance (minutes)</span>
          <div className="flex gap-2">
            <input
              type="number"
              value={tolerance}
              onChange={(e) => setTolerance(e.target.value)}
              className="w-24 rounded-md border border-ink-100 px-3 py-1.5"
            />
            <button onClick={saveTolerance} className="rounded-md border border-ink-100 px-3 py-1.5 text-ink-700 hover:bg-ink-50">
              Enregistrer
            </button>
          </div>
        </label>
        <button onClick={downloadBilanCumule} className="ml-auto rounded-md bg-brand-700 px-3 py-1.5 text-sm text-white hover:bg-brand-800">
          Télécharger le bilan cumulé (PDF)
        </button>
      </div>

      <div className="overflow-x-auto rounded-lg border border-ink-100 bg-white">
        <table className="min-w-full divide-y divide-ink-100 text-sm">
          <thead className="bg-ink-50">
            <tr>
              <th className="px-4 py-2 text-left font-medium text-ink-500">Nom</th>
              <th className="px-4 py-2 text-left font-medium text-ink-500">Matricule</th>
              <th className="px-4 py-2 text-left font-medium text-ink-500">Section</th>
              <th className="px-4 py-2 text-left font-medium text-ink-500">Jours de retard</th>
              <th className="px-4 py-2 text-left font-medium text-ink-500">Minutes cumulées</th>
              <th className="px-4 py-2" />
            </tr>
          </thead>
          <tbody className="divide-y divide-ink-100">
            {loading && (
              <tr><td colSpan={6} className="px-4 py-6 text-center text-ink-300">Chargement…</td></tr>
            )}
            {!loading && lignes.map((l) => (
              <tr key={l.enseignant_id} className="hover:bg-ink-50">
                <td className="px-4 py-2">{l.nom}</td>
                <td className="px-4 py-2">{l.matricule}</td>
                <td className="px-4 py-2">{l.section}</td>
                <td className="px-4 py-2">{l.jours_retard}</td>
                <td className="px-4 py-2">{l.minutes_retard_total}</td>
                <td className="px-4 py-2 text-right">
                  <button
                    onClick={() =>
                      downloadFile(`/retards/bilan/${l.enseignant_id}?debut=${debut}&fin=${fin}`, `bilan-${l.matricule}.pdf`)
                    }
                    className="text-ink-500 hover:text-ink-900"
                  >
                    Fiche PDF
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
