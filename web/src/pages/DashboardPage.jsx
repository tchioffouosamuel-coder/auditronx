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
    <div className="rounded-xl border border-ink-100 bg-white p-4 shadow-sm">
      <div className="text-xs uppercase tracking-wide text-ink-300">{label}</div>
      <div className={`mt-1 text-2xl font-bold ${tones[tone]}`}>{value}</div>
    </div>
  )
}

export default function DashboardPage() {
  const [data, setData] = useState(null)
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10))

  useEffect(() => {
    api.get('/dashboard', { params: { date } }).then(({ data }) => setData(data))
  }, [date])

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-lg font-semibold text-ink-900">Tableau de bord</h1>
        <input
          type="date"
          value={date}
          onChange={(e) => setDate(e.target.value)}
          className="rounded-md border border-ink-100 px-3 py-1.5 text-sm"
        />
      </div>

      {!data && <div className="text-ink-300">Chargement…</div>}

      {data && (
        <>
          <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <KpiCard label="Effectif" value={data.effectif} />
            <KpiCard label="Présents" value={data.presents} tone="green" />
            <KpiCard label="Absents" value={data.absents} tone="red" />
            <KpiCard label="Retardataires" value={data.retardataires} tone="amber" />
          </div>

          <div className="rounded-lg border border-ink-100 bg-white p-4">
            <h2 className="mb-4 text-sm font-medium text-ink-700">Taux d’assiduité par section</h2>
            <ResponsiveContainer width="100%" height={280}>
              <BarChart data={data.classement_par_section}>
                <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                <XAxis dataKey="section" tick={{ fontSize: 12 }} />
                <YAxis tick={{ fontSize: 12 }} unit="%" />
                <Tooltip />
                <Bar dataKey="taux_assiduite" fill="#0f6e49" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </>
      )}
    </div>
  )
}
