import { useEffect, useState } from 'react'
import DataTable from '../components/DataTable'
import api from '../lib/api'
import { downloadFile } from '../lib/download'
import { endOfMonthIso, startOfMonthIso } from '../lib/datetime'

export default function PonctualitePage() {
  const [debut, setDebut] = useState(startOfMonthIso)
  const [fin, setFin] = useState(endOfMonthIso)
  const [coefficient, setCoefficient] = useState(30)
  const [tolerance, setTolerance] = useState(10)
  const [classement, setClassement] = useState([])
  const [loading, setLoading] = useState(true)

  function load() {
    setLoading(true)
    Promise.all([
      api.get('/retards/classement-ponctualite', { params: { debut, fin } }),
      api.get('/retards/parametres'),
    ]).then(([r, p]) => {
      setClassement(Array.isArray(r.data) ? r.data : [])
      setCoefficient(p.data.coefficient_minutes)
      setTolerance(p.data.tolerance_minutes)
      setLoading(false)
    })
  }

  useEffect(load, [debut, fin])

  async function saveCoefficient() {
    await api.put('/retards/parametres', {
      tolerance_minutes: tolerance,
      coefficient_minutes: Number(coefficient),
    })
    load()
  }

  function downloadClassement() {
    downloadFile(
      `/retards/bilan-cumule?debut=${debut}&fin=${fin}&tri=ponctualite`,
      `classement-ponctualite-${debut}-${fin}.pdf`,
    )
  }

  return (
    <div>
      <h1 className="page-title mb-4">Classement de ponctualité</h1>
      <p className="mb-4 max-w-3xl text-sm text-ink-600">
        Classe les enseignants selon leur taux de ponctualité (jours prévus / jours
        ponctuels). À nombre égal de jours ponctuels, un coefficient régressif appliqué
        aux minutes de retard cumulées départage le classement.
      </p>

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
          <span className="mb-1 block text-ink-700">Coefficient régressif (minutes)</span>
          <div className="flex gap-2">
            <input
              type="number"
              min="1"
              value={coefficient}
              onChange={(e) => setCoefficient(e.target.value)}
              className="field w-24 py-2"
            />
            <button onClick={saveCoefficient} className="btn-secondary">
              Enregistrer
            </button>
          </div>
        </label>
        <button onClick={downloadClassement} className="btn-primary ml-auto">
          Télécharger le classement (PDF)
        </button>
      </div>

      <DataTable
        loading={loading}
        rows={classement}
        idKey="enseignant_id"
        columns={[
          { key: 'nom', label: 'Nom' },
          { key: 'matricule', label: 'Matricule' },
          { key: 'section', label: 'Section' },
          { key: 'jours_prevus', label: 'Jours prévus' },
          { key: 'jours_ponctuels', label: 'Jours ponctuels' },
          { key: 'taux_ponctualite', label: 'Taux ponctualité', render: (l) => `${l.taux_ponctualite}%` },
          { key: 'total_retard_minutes', label: 'Minutes retard cumulées' },
          { key: 'score_ponctualite', label: 'Score' },
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
