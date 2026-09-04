import { useEffect, useState } from 'react'
import ResourceTable from '../components/ResourceTable'
import api from '../lib/api'

function FicheTab() {
  const [fiche, setFiche] = useState([])

  useEffect(() => {
    api.get('/fiche-progression').then(({ data }) => setFiche(data))
  }, [])

  return (
    <table className="min-w-full divide-y divide-ink-100 text-sm">
      <thead className="bg-ink-50">
        <tr>
          <th className="px-4 py-2 text-left font-medium text-ink-500">Classe</th>
          <th className="px-4 py-2 text-left font-medium text-ink-500">Discipline</th>
          <th className="px-4 py-2 text-left font-medium text-ink-500">Année</th>
          <th className="px-4 py-2 text-left font-medium text-ink-500">Séances prévues</th>
          <th className="px-4 py-2 text-left font-medium text-ink-500">Réalisées</th>
          <th className="px-4 py-2 text-left font-medium text-ink-500">Taux</th>
        </tr>
      </thead>
      <tbody className="divide-y divide-ink-100">
        {fiche.map((f, i) => (
          <tr key={i} className="hover:bg-ink-50">
            <td className="px-4 py-2">{f.classe}</td>
            <td className="px-4 py-2">{f.discipline}</td>
            <td className="px-4 py-2">{f.annee_scolaire}</td>
            <td className="px-4 py-2">{f.nb_seances_prevues}</td>
            <td className="px-4 py-2">{f.nb_seances_realisees}</td>
            <td className="px-4 py-2">
              <span className={f.en_retard ? 'text-red-600' : 'text-green-600'}>{f.taux_avancement}%</span>
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

export default function FicheProgressionPage() {
  const [tab, setTab] = useState('fiche')

  return (
    <div>
      <h1 className="mb-4 text-lg font-semibold text-ink-900">Fiche de progression</h1>

      <div className="mb-4 flex gap-2">
        {[
          ['fiche', 'Fiche de progression'],
          ['programmes', 'Programmes officiels'],
        ].map(([key, label]) => (
          <button
            key={key}
            onClick={() => setTab(key)}
            className={`rounded-md px-3 py-1.5 text-sm ${
              tab === key ? 'bg-brand-700 text-white' : 'bg-white text-ink-700 border border-ink-100'
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      {tab === 'fiche' && (
        <div className="overflow-x-auto rounded-lg border border-ink-100 bg-white p-4">
          <FicheTab />
        </div>
      )}

      {tab === 'programmes' && (
        <ResourceTable
          title=""
          resource="/programmes"
          columns={[
            { key: 'classe', label: 'Classe', render: (r) => r.classe?.nom },
            { key: 'discipline', label: 'Discipline', render: (r) => r.discipline?.nom },
            { key: 'annee_scolaire', label: 'Année' },
            { key: 'nb_seances_prevues', label: 'Séances prévues' },
          ]}
          fields={[
            { key: 'classe_id', label: 'Classe', type: 'select', optionsUrl: '/classes', required: true },
            { key: 'discipline_id', label: 'Discipline', type: 'select', optionsUrl: '/disciplines', required: true },
            { key: 'annee_scolaire', label: 'Année scolaire (ex. 2026-2027)', required: true },
            { key: 'nb_seances_prevues', label: 'Séances prévues', type: 'number', required: true },
          ]}
        />
      )}
    </div>
  )
}
