import { useEffect, useState } from 'react'
import api from '../lib/api'

export default function CahierTextePage() {
  const [enseignants, setEnseignants] = useState([])
  const [enseignantId, setEnseignantId] = useState('')
  const [entrees, setEntrees] = useState([])
  const [emplois, setEmplois] = useState([])
  const [form, setForm] = useState({ emploi_du_temps_id: '', date: new Date().toISOString().slice(0, 10), contenu: '', reference_programme: '' })

  useEffect(() => {
    api.get('/personnel?per_page=500').then(({ data }) => setEnseignants(data.data ?? []))
  }, [])

  useEffect(() => {
    if (!enseignantId) return
    api.get(`/cahier-texte/${enseignantId}`).then(({ data }) => setEntrees(data.data ?? []))
    api.get('/emplois', { params: { enseignant_id: enseignantId } }).then(({ data }) => setEmplois(data.data ?? []))
  }, [enseignantId])

  async function submit(e) {
    e.preventDefault()
    await api.post('/cahier-texte', { ...form, enseignant_id: enseignantId })
    const { data } = await api.get(`/cahier-texte/${enseignantId}`)
    setEntrees(data.data ?? [])
    setForm({ emploi_du_temps_id: '', date: new Date().toISOString().slice(0, 10), contenu: '', reference_programme: '' })
  }

  return (
    <div>
      <h1 className="mb-4 text-lg font-semibold text-ink-900">Cahier de texte</h1>

      <label className="mb-4 block max-w-xs text-sm">
        <span className="mb-1 block text-ink-700">Enseignant</span>
        <select value={enseignantId} onChange={(e) => setEnseignantId(e.target.value)} className="w-full rounded-md border border-ink-100 px-3 py-2">
          <option value="">Sélectionner…</option>
          {enseignants.map((e) => (
            <option key={e.id} value={e.id}>{e.nom}</option>
          ))}
        </select>
      </label>

      {enseignantId && (
        <>
          <form onSubmit={submit} className="mb-6 grid gap-3 rounded-lg border border-ink-100 bg-white p-4 md:grid-cols-2">
            <label className="text-sm">
              <span className="mb-1 block text-ink-700">Créneau</span>
              <select
                required
                value={form.emploi_du_temps_id}
                onChange={(e) => setForm((f) => ({ ...f, emploi_du_temps_id: e.target.value }))}
                className="w-full rounded-md border border-ink-100 px-3 py-2"
              >
                <option value="">Sélectionner…</option>
                {emplois.map((em) => (
                  <option key={em.id} value={em.id}>
                    {em.classe?.nom} — {em.discipline?.nom} ({em.heure_debut})
                  </option>
                ))}
              </select>
            </label>
            <label className="text-sm">
              <span className="mb-1 block text-ink-700">Date</span>
              <input type="date" required value={form.date} onChange={(e) => setForm((f) => ({ ...f, date: e.target.value }))} className="w-full rounded-md border border-ink-100 px-3 py-2" />
            </label>
            <label className="text-sm md:col-span-2">
              <span className="mb-1 block text-ink-700">Contenu de la séance</span>
              <textarea required rows={3} value={form.contenu} onChange={(e) => setForm((f) => ({ ...f, contenu: e.target.value }))} className="w-full rounded-md border border-ink-100 px-3 py-2" />
            </label>
            <label className="text-sm md:col-span-2">
              <span className="mb-1 block text-ink-700">Référence programme</span>
              <input value={form.reference_programme} onChange={(e) => setForm((f) => ({ ...f, reference_programme: e.target.value }))} className="w-full rounded-md border border-ink-100 px-3 py-2" />
            </label>
            <button type="submit" className="rounded-md bg-brand-700 px-3 py-2 text-sm font-medium text-white hover:bg-brand-800 md:col-span-2">
              Ajouter l’entrée
            </button>
          </form>

          <div className="space-y-3">
            {entrees.map((entree) => (
              <div key={entree.id} className="rounded-lg border border-ink-100 bg-white p-4">
                <div className="mb-1 flex justify-between text-xs text-ink-300">
                  <span>{entree.date}</span>
                  <span>{entree.emploi_du_temps?.classe?.nom} — {entree.emploi_du_temps?.discipline?.nom}</span>
                </div>
                <p className="text-sm text-ink-900">{entree.contenu}</p>
                {entree.reference_programme && (
                  <p className="mt-1 text-xs text-ink-300">Réf. programme : {entree.reference_programme}</p>
                )}
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  )
}
