import { useEffect, useState } from 'react'
import DataTable from '../components/DataTable'
import ResourceTable from '../components/ResourceTable'
import api from '../lib/api'

function FicheTab() {
  const [fiche, setFiche] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    api.get('/fiche-progression').then(({ data }) => setFiche(data)).finally(() => setLoading(false))
  }, [])

  return (
    <DataTable
      loading={loading}
      rows={fiche}
      getRowKey={(f, i) => i}
      columns={[
        { key: 'classe', label: 'Classe' },
        { key: 'discipline', label: 'Discipline' },
        { key: 'annee_scolaire', label: 'Année' },
        { key: 'nb_seances_prevues', label: 'Séances prévues' },
        { key: 'nb_seances_realisees', label: 'Réalisées' },
        {
          key: 'taux_avancement',
          label: 'Taux',
          render: (f) => <span className={f.en_retard ? 'text-red-600' : 'text-green-600'}>{f.taux_avancement}%</span>,
          sortValue: (f) => f.taux_avancement,
        },
      ]}
    />
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

      {tab === 'fiche' && <FicheTab />}

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
