import { useEffect, useState } from 'react'
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import api from '../lib/api'

function KpiCard({ label, value, tone = 'neutral' }) {
  const tones = {
    neutral: 'text-brand-800',
    green: 'text-green-600',
    red: 'text-red-600',
    amber: 'text-amber-600',
  }
  return (
    <div className="rounded-xl border border-ink-100 bg-white p-4 shadow-sm transition hover:shadow-md">
      <div className="text-xs font-medium uppercase tracking-wide text-ink-300">{label}</div>
      <div className={`mt-1.5 text-3xl font-bold tabular-nums ${tones[tone]}`}>{value}</div>
    </div>
  )
}

function PeopleList({ title, people = [], tone = 'neutral' }) {
  const colors = {
    neutral: 'text-ink-700',
    green: 'text-green-600',
    red: 'text-red-600',
    amber: 'text-amber-600',
  }

  return (
    <section className="rounded-xl border border-ink-100 bg-white p-4 shadow-sm sm:p-6">
      <h2 className={`mb-4 text-sm font-medium ${colors[tone]}`}>{title} ({people.length})</h2>
      {people.length === 0 ? (
        <p className="text-sm text-ink-300">Aucune personne.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead className="border-b border-ink-100 text-xs uppercase tracking-wide text-ink-300">
              <tr>
                <th className="px-2 py-2 font-medium">Nom</th>
                <th className="px-2 py-2 font-medium">Matricule</th>
                <th className="px-2 py-2 font-medium">Cours prévu</th>
                <th className="px-2 py-2 font-medium">Arrivée</th>
                <th className="px-2 py-2 font-medium">Départ</th>
                <th className="px-2 py-2 font-medium">Retard</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-ink-100">
              {people.map((person) => (
                <tr key={person.enseignant_id}>
                  <td className="whitespace-nowrap px-2 py-3 font-medium text-ink-800">{person.nom || '—'}</td>
                  <td className="whitespace-nowrap px-2 py-3 text-ink-500">{person.matricule || '—'}</td>
                  <td className="px-2 py-3 text-ink-500">
                    {(person.cours || []).map((course) => `${course.classe || '—'} ${course.heure_debut || ''}-${course.heure_fin || ''}`).join(' · ') || '—'}
                  </td>
                  <td className="whitespace-nowrap px-2 py-3 text-ink-500">{person.heure_arrivee || '—'}</td>
                  <td className="whitespace-nowrap px-2 py-3 text-ink-500">{person.heure_depart || '—'}</td>
                  <td className="whitespace-nowrap px-2 py-3 text-ink-500">{person.minutes_retard ? `${person.minutes_retard} min` : '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}

export default function DashboardPage() {
  const [data, setData] = useState(null)
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10))

  useEffect(() => {
    api.get('/dashboard', { params: { date } }).then(({ data }) => setData(data))
  }, [date])

  const classement = data && Array.isArray(data.classement_par_section) ? data.classement_par_section : []
  const scannes = data && Array.isArray(data.scannes) ? data.scannes : []
  const absents = data && Array.isArray(data.absents_liste) ? data.absents_liste : []
  const retardataires = data && Array.isArray(data.retardataires_liste) ? data.retardataires_liste : []

  return (
    <div>
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-lg font-semibold text-ink-900">Tableau de bord</h1>
        <input
          type="date"
          value={date}
          onChange={(e) => setDate(e.target.value)}
          className="rounded-md border border-ink-100 px-3 py-1.5 text-sm focus:border-brand-500 focus:outline-none"
        />
      </div>

      {!data && (
        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
          {[0, 1, 2, 3].map((i) => (
            <div key={i} className="h-20 animate-pulse rounded-xl border border-ink-100 bg-ink-100/50" />
          ))}
        </div>
      )}

      {data && (
        <>
          <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <KpiCard label="Effectif" value={data.effectif} />
            <KpiCard label="Présents" value={data.presents} tone="green" />
            <KpiCard label="Absents" value={data.absents} tone="red" />
            <KpiCard label="Retardataires" value={data.retardataires} tone="amber" />
          </div>

          <div className="rounded-xl border border-ink-100 bg-white p-4 shadow-sm sm:p-6">
            <h2 className="mb-4 text-sm font-medium text-ink-700">Taux d’assiduité par section</h2>
            {classement.length === 0 ? (
              <div className="flex h-[280px] items-center justify-center text-sm text-ink-300">
                Aucune donnée pour cette date.
              </div>
            ) : (
              <ResponsiveContainer width="100%" height={280}>
                <BarChart data={classement}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                  <XAxis dataKey="section" tick={{ fontSize: 12 }} />
                  <YAxis tick={{ fontSize: 12 }} unit="%" />
                  <Tooltip />
                  <Bar dataKey="taux_assiduite" fill="#0f6e49" radius={[4, 4, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            )}
          </div>

          <div className="mt-6 space-y-6">
            <PeopleList title="Déjà scannés" people={scannes} tone="green" />
            <PeopleList title="Absents selon l’emploi du temps" people={absents} tone="red" />
            <PeopleList title="Retardataires" people={retardataires} tone="amber" />
          </div>
        </>
      )}
    </div>
  )
}
