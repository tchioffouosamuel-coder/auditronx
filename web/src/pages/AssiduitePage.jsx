import { useEffect, useState } from 'react'
import api from '../lib/api'
import { downloadFile } from '../lib/download'

function StatsTab() {
  const [lignes, setLignes] = useState([])

  useEffect(() => {
    api.get('/assiduite/stats').then(({ data }) => setLignes(data))
  }, [])

  return (
    <table className="min-w-full divide-y divide-ink-100 text-sm">
      <thead className="bg-ink-50">
        <tr>
          <th className="px-4 py-2 text-left font-medium text-ink-500">Nom</th>
          <th className="px-4 py-2 text-left font-medium text-ink-500">Section</th>
          <th className="px-4 py-2 text-left font-medium text-ink-500">Jours présents</th>
          <th className="px-4 py-2 text-left font-medium text-ink-500">Jours ouvrés</th>
          <th className="px-4 py-2 text-left font-medium text-ink-500">Taux</th>
        </tr>
      </thead>
      <tbody className="divide-y divide-ink-100">
        {lignes.map((l) => (
          <tr key={l.enseignant_id} className="hover:bg-ink-50">
            <td className="px-4 py-2">{l.nom}</td>
            <td className="px-4 py-2">{l.section}</td>
            <td className="px-4 py-2">{l.jours_presents}</td>
            <td className="px-4 py-2">{l.jours_ouvres}</td>
            <td className="px-4 py-2">{l.taux_assiduite}%</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

function JournalTab() {
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10))
  const [presences, setPresences] = useState([])

  useEffect(() => {
    api.get('/assiduite/journal', { params: { date } }).then(({ data }) => setPresences(data))
  }, [date])

  return (
    <div>
      <input type="date" value={date} onChange={(e) => setDate(e.target.value)} className="mb-3 rounded-md border border-ink-100 px-3 py-1.5 text-sm" />
      <table className="min-w-full divide-y divide-ink-100 text-sm">
        <thead className="bg-ink-50">
          <tr>
            <th className="px-4 py-2 text-left font-medium text-ink-500">Enseignant</th>
            <th className="px-4 py-2 text-left font-medium text-ink-500">Arrivée</th>
            <th className="px-4 py-2 text-left font-medium text-ink-500">Départ</th>
            <th className="px-4 py-2 text-left font-medium text-ink-500">Source</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-ink-100">
          {presences.map((p) => (
            <tr key={p.id} className="hover:bg-ink-50">
              <td className="px-4 py-2">{p.enseignant?.nom}</td>
              <td className="px-4 py-2">{p.heure_arrivee ? new Date(p.heure_arrivee).toLocaleTimeString('fr-FR') : '—'}</td>
              <td className="px-4 py-2">{p.heure_depart ? new Date(p.heure_depart).toLocaleTimeString('fr-FR') : '—'}</td>
              <td className="px-4 py-2">{p.source}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function PersonnelInactifTab() {
  const [jours, setJours] = useState(7)
  const [inactifs, setInactifs] = useState([])

  useEffect(() => {
    api.get('/assiduite/personnel-inactif', { params: { jours } }).then(({ data }) => setInactifs(data))
  }, [jours])

  return (
    <div>
      <label className="mb-3 block text-sm">
        Inactifs depuis plus de{' '}
        <input type="number" value={jours} onChange={(e) => setJours(e.target.value)} className="w-16 rounded-md border border-ink-100 px-2 py-1" /> jours
      </label>
      <table className="min-w-full divide-y divide-ink-100 text-sm">
        <thead className="bg-ink-50">
          <tr>
            <th className="px-4 py-2 text-left font-medium text-ink-500">Nom</th>
            <th className="px-4 py-2 text-left font-medium text-ink-500">Section</th>
            <th className="px-4 py-2 text-left font-medium text-ink-500">Dernière présence</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-ink-100">
          {inactifs.map((i) => (
            <tr key={i.enseignant_id} className="hover:bg-ink-50">
              <td className="px-4 py-2">{i.nom}</td>
              <td className="px-4 py-2">{i.section}</td>
              <td className="px-4 py-2">{i.derniere_presence ?? 'Jamais'}</td>
            </tr>
          ))}
        </tbody>
      </table>
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

      <div className="overflow-x-auto rounded-lg border border-ink-100 bg-white p-4">
        {tab === 'stats' && <StatsTab />}
        {tab === 'journal' && <JournalTab />}
        {tab === 'inactif' && <PersonnelInactifTab />}
      </div>
    </div>
  )
}
