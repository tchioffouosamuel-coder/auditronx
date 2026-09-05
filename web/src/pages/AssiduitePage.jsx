import { useEffect, useState } from 'react'
import DataTable from '../components/DataTable'
import api from '../lib/api'
import { downloadFile } from '../lib/download'

function StatsTab() {
  const [lignes, setLignes] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    api.get('/assiduite/stats').then(({ data }) => setLignes(Array.isArray(data) ? data : [])).finally(() => setLoading(false))
  }, [])

  return (
    <DataTable
      loading={loading}
      rows={lignes}
      idKey="enseignant_id"
      columns={[
        { key: 'nom', label: 'Nom' },
        { key: 'section', label: 'Section' },
        { key: 'jours_presents', label: 'Jours présents' },
        { key: 'jours_ouvres', label: 'Jours ouvrés' },
        { key: 'taux_assiduite', label: 'Taux', render: (l) => `${l.taux_assiduite}%`, sortValue: (l) => l.taux_assiduite },
      ]}
    />
  )
}

function JournalTab() {
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10))
  const [presences, setPresences] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    setLoading(true)
    api.get('/assiduite/journal', { params: { date } }).then(({ data }) => setPresences(Array.isArray(data) ? data : [])).finally(() => setLoading(false))
  }, [date])

  return (
    <div>
      <input type="date" value={date} onChange={(e) => setDate(e.target.value)} className="mb-3 rounded-md border border-ink-100 px-3 py-1.5 text-sm" />
      <DataTable
        loading={loading}
        rows={presences}
        columns={[
          { key: 'enseignant', label: 'Enseignant', render: (p) => p.enseignant?.nom, sortValue: (p) => p.enseignant?.nom },
          {
            key: 'heure_arrivee',
            label: 'Arrivée',
            render: (p) => (p.heure_arrivee ? new Date(p.heure_arrivee).toLocaleTimeString('fr-FR') : '—'),
            sortValue: (p) => p.heure_arrivee ?? '',
          },
          {
            key: 'heure_depart',
            label: 'Départ',
            render: (p) => (p.heure_depart ? new Date(p.heure_depart).toLocaleTimeString('fr-FR') : '—'),
            sortValue: (p) => p.heure_depart ?? '',
          },
          { key: 'source', label: 'Source' },
          {
            key: 'photo',
            label: 'Photo (§hardware)',
            sortable: false,
            render: (p) => (
              <div className="flex gap-2">
                {p.photo_url_arrivee && (
                  <a href={p.photo_url_arrivee} target="_blank" rel="noreferrer" title="Photo à l'arrivée">
                    <img
                      src={p.photo_url_arrivee}
                      alt="Photo arrivée"
                      className="h-10 w-10 rounded-md border border-ink-100 object-cover transition hover:scale-150 hover:shadow-md"
                    />
                  </a>
                )}
                {p.photo_url_depart && (
                  <a href={p.photo_url_depart} target="_blank" rel="noreferrer" title="Photo au départ">
                    <img
                      src={p.photo_url_depart}
                      alt="Photo départ"
                      className="h-10 w-10 rounded-md border border-ink-100 object-cover transition hover:scale-150 hover:shadow-md"
                    />
                  </a>
                )}
                {!p.photo_url_arrivee && !p.photo_url_depart && <span className="text-ink-300">—</span>}
              </div>
            ),
          },
        ]}
      />
    </div>
  )
}

function PersonnelInactifTab() {
  const [jours, setJours] = useState(7)
  const [inactifs, setInactifs] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    setLoading(true)
    api.get('/assiduite/personnel-inactif', { params: { jours } }).then(({ data }) => setInactifs(Array.isArray(data) ? data : [])).finally(() => setLoading(false))
  }, [jours])

  return (
    <div>
      <label className="mb-3 block text-sm">
        Inactifs depuis plus de{' '}
        <input type="number" value={jours} onChange={(e) => setJours(e.target.value)} className="w-16 rounded-md border border-ink-100 px-2 py-1" /> jours
      </label>
      <DataTable
        loading={loading}
        rows={inactifs}
        idKey="enseignant_id"
        columns={[
          { key: 'nom', label: 'Nom' },
          { key: 'section', label: 'Section' },
          { key: 'derniere_presence', label: 'Dernière présence', render: (i) => i.derniere_presence ?? 'Jamais' },
        ]}
      />
    </div>
  )
}

export default function AssiduitePage() {
  const [tab, setTab] = useState('stats')

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-lg font-semibold text-ink-900">Assiduité & rapports</h1>
        <button
          onClick={() => downloadFile('/statistiques/export-zip', 'bilans-retards.zip')}
          className="rounded-md bg-brand-700 px-3 py-1.5 text-sm text-white hover:bg-brand-800"
        >
          Export ZIP (bilans PDF)
        </button>
      </div>

      <div className="mb-4 flex gap-2">
        {[
          ['stats', 'Statistiques'],
          ['journal', 'Journal des présences'],
          ['inactif', 'Personnel inactif'],
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

      {tab === 'stats' && <StatsTab />}
      {tab === 'journal' && <JournalTab />}
      {tab === 'inactif' && <PersonnelInactifTab />}
    </div>
  )
}
