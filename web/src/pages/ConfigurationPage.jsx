import { useCallback, useEffect, useState } from 'react'
import LoadingState from '../components/LoadingState'
import SearchSelect from '../components/SearchSelect'
import api from '../lib/api'
import fetchAllPages from '../lib/fetchAllPages'
import { confirmAction, notify } from '../lib/swal'
import { JOURS, nomJour, toHeureAffichee, toHeureMinute } from './EmploisPage'

/** Messages de validation Laravel aplatis en une phrase. */
function messageErreur(error, defaut) {
  const erreurs = error.response?.data?.errors
  return erreurs ? Object.values(erreurs).flat().join(' ') : defaut
}

const CARTE = 'rounded-lg border border-ink-100 bg-white p-4 sm:p-6'

/* -------------------------------------------------------------------------- */
/* Horaires du personnel administratif                                         */
/* -------------------------------------------------------------------------- */

function HorairesAdministratifs() {
  const [jours, setJours] = useState([1, 2, 3, 4, 5])
  const [heureDebut, setHeureDebut] = useState('07:30')
  const [heureFin, setHeureFin] = useState('15:30')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    api.get('/parametres/horaires-administratifs').then(({ data }) => {
      setJours(data.jours)
      setHeureDebut(data.heure_debut)
      setHeureFin(data.heure_fin)
      setLoading(false)
    })
  }, [])

  function toggleJour(jour) {
    setJours((actuels) => (actuels.includes(jour) ? actuels.filter((j) => j !== jour) : [...actuels, jour].sort()))
  }

  async function save(e) {
    e.preventDefault()
    setSaving(true)
    try {
      await api.put('/parametres/horaires-administratifs', { jours, heure_debut: heureDebut, heure_fin: heureFin })
      notify('Horaires du personnel administratif enregistrés.', { icon: 'success' })
    } catch (error) {
      notify(messageErreur(error, "L'enregistrement a échoué."), { icon: 'error' })
    } finally {
      setSaving(false)
    }
  }

  if (loading) return <LoadingState />

  return (
    <form onSubmit={save} className={`max-w-2xl ${CARTE}`}>
      <h2 className="text-sm font-medium text-ink-900">Horaires du personnel administratif</h2>
      <p className="mt-1 mb-4 text-sm text-ink-500">
        Le personnel de la section « Administration » n’est pas évalué selon un emploi du temps : il est attendu
        chaque jour coché ci-dessous, sur la plage horaire indiquée. Présences, absences, retards et départs
        anticipés sont calculés à partir de ces valeurs. Un emploi du temps spécial, s’il existe, prime sur ces
        horaires.
      </p>

      <fieldset className="mb-4">
        <legend className="mb-2 text-sm text-ink-700">Jours de présence</legend>
        <div className="flex flex-wrap gap-x-4 gap-y-2">
          {JOURS.map((jour) => (
            <label key={jour.value} className="flex items-center gap-2 text-sm text-ink-700">
              <input type="checkbox" checked={jours.includes(jour.value)} onChange={() => toggleJour(jour.value)} />
              {jour.label}
            </label>
          ))}
        </div>
      </fieldset>

      <div className="mb-4 flex flex-wrap gap-4">
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Début de journée</span>
          <input
            type="time"
            required
            value={heureDebut}
            onChange={(e) => setHeureDebut(e.target.value)}
            className="rounded-md border border-ink-100 px-3 py-1.5"
          />
        </label>
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Fin de journée</span>
          <input
            type="time"
            required
            value={heureFin}
            onChange={(e) => setHeureFin(e.target.value)}
            className="rounded-md border border-ink-100 px-3 py-1.5"
          />
        </label>
      </div>

      <button
        type="submit"
        disabled={saving || jours.length === 0}
        className="rounded-md bg-brand-700 px-3 py-1.5 text-sm text-white hover:bg-brand-800 disabled:opacity-50"
      >
        {saving ? 'Enregistrement…' : 'Enregistrer'}
      </button>
    </form>
  )
}

/* -------------------------------------------------------------------------- */
/* Périodes de cours                                                           */
/* -------------------------------------------------------------------------- */

const PERIODE_VIDE = { ordre: '', libelle: '', heure_debut: '', heure_fin: '' }

/**
 * Grille horaire de l'établissement. Ce sont ces périodes — et elles seules —
 * qui alimentent la liste déroulante « Période de cours » à la saisie d'un
 * créneau, côté web comme côté mobile.
 */
function PeriodesCours() {
  const [periodes, setPeriodes] = useState([])
  const [loading, setLoading] = useState(true)
  const [form, setForm] = useState(PERIODE_VIDE)
  const [edition, setEdition] = useState(null) // id en cours de modification
  const [saving, setSaving] = useState(false)

  const charger = useCallback(async () => {
    setLoading(true)
    try {
      const { data } = await api.get('/periodes-cours')
      setPeriodes(data.data ?? [])
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    charger()
  }, [charger])

  function reinitialiser() {
    setForm(PERIODE_VIDE)
    setEdition(null)
  }

  function modifier(periode) {
    setEdition(periode.id)
    setForm({
      ordre: periode.ordre,
      libelle: periode.libelle ?? '',
      heure_debut: toHeureMinute(periode.heure_debut),
      heure_fin: toHeureMinute(periode.heure_fin),
    })
  }

  async function enregistrer(e) {
    e.preventDefault()
    setSaving(true)
    try {
      if (edition) {
        await api.put(`/periodes-cours/${edition}`, form)
      } else {
        await api.post('/periodes-cours', form)
      }
      reinitialiser()
      await charger()
    } catch (error) {
      notify(messageErreur(error, "L'enregistrement a échoué."), { icon: 'error' })
    } finally {
      setSaving(false)
    }
  }

  async function supprimer(periode) {
    const libelle = periode.libelle_complet ?? toHeureAffichee(periode.heure_debut)
    if (!(await confirmAction(`Supprimer la période ${libelle} ?`, { confirmText: 'Supprimer' }))) return
    try {
      await api.delete(`/periodes-cours/${periode.id}`)
      await charger()
    } catch (error) {
      // L'API refuse de supprimer une période encore portée par des créneaux,
      // et dit combien : le message est plus utile que « échec ».
      notify(messageErreur(error, 'La suppression a échoué.'), { icon: 'error' })
    }
  }

  return (
    <div className={CARTE}>
      <h2 className="text-sm font-medium text-ink-900">Périodes de cours</h2>
      <p className="mt-1 mb-4 text-sm text-ink-500">
        La grille horaire de l’établissement. Chaque période est un couple début/fin : c’est cette liste qui est
        proposée à la saisie d’un emploi du temps.
      </p>

      {loading ? (
        <LoadingState />
      ) : (
        <div className="mb-4 overflow-x-auto">
          <table className="w-full min-w-[32rem] text-sm">
            <thead>
              <tr className="border-b border-ink-100 text-left text-xs tracking-wide text-ink-500 uppercase">
                <th className="py-2 pr-3">Ordre</th>
                <th className="py-2 pr-3">Libellé</th>
                <th className="py-2 pr-3">Début</th>
                <th className="py-2 pr-3">Fin</th>
                <th className="py-2" />
              </tr>
            </thead>
            <tbody>
              {periodes.length === 0 ? (
                <tr>
                  <td colSpan={5} className="py-3 text-sm text-ink-500">
                    Aucune période configurée. Ajoutez-en ci-dessous.
                  </td>
                </tr>
              ) : (
                periodes.map((periode) => (
                  <tr key={periode.id} className="border-b border-ink-50">
                    <td className="py-2 pr-3 text-ink-700">{periode.ordre}</td>
                    <td className="py-2 pr-3 text-ink-900">{periode.libelle || '—'}</td>
                    <td className="py-2 pr-3 text-ink-700">{toHeureAffichee(periode.heure_debut)}</td>
                    <td className="py-2 pr-3 text-ink-700">{toHeureAffichee(periode.heure_fin)}</td>
                    <td className="py-2 text-right whitespace-nowrap">
                      <button onClick={() => modifier(periode)} className="mr-3 text-ink-500 hover:text-ink-900">
                        Éditer
                      </button>
                      <button onClick={() => supprimer(periode)} className="text-red-500 hover:text-red-700">
                        Suppr.
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}

      <form onSubmit={enregistrer} className="flex flex-wrap items-end gap-3 border-t border-ink-100 pt-4">
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Ordre</span>
          <input
            type="number"
            min="1"
            max="99"
            required
            value={form.ordre}
            onChange={(e) => setForm((f) => ({ ...f, ordre: e.target.value }))}
            className="w-20 rounded-md border border-ink-100 px-3 py-1.5"
          />
        </label>
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Libellé</span>
          <input
            placeholder="P1, Récréation…"
            value={form.libelle}
            onChange={(e) => setForm((f) => ({ ...f, libelle: e.target.value }))}
            className="w-40 rounded-md border border-ink-100 px-3 py-1.5"
          />
        </label>
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Début</span>
          <input
            type="time"
            required
            value={form.heure_debut}
            onChange={(e) => setForm((f) => ({ ...f, heure_debut: e.target.value }))}
            className="rounded-md border border-ink-100 px-3 py-1.5"
          />
        </label>
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Fin</span>
          <input
            type="time"
            required
            value={form.heure_fin}
            onChange={(e) => setForm((f) => ({ ...f, heure_fin: e.target.value }))}
            className="rounded-md border border-ink-100 px-3 py-1.5"
          />
        </label>
        <button
          type="submit"
          disabled={saving}
          className="rounded-md bg-brand-700 px-3 py-1.5 text-sm text-white hover:bg-brand-800 disabled:opacity-50"
        >
          {edition ? 'Modifier' : 'Ajouter'}
        </button>
        {edition && (
          <button type="button" onClick={reinitialiser} className="px-2 py-1.5 text-sm text-ink-500 hover:text-ink-900">
            Annuler
          </button>
        )}
      </form>
    </div>
  )
}

/* -------------------------------------------------------------------------- */
/* Emplois du temps spéciaux                                                   */
/* -------------------------------------------------------------------------- */

const PLAGE_VIDE = { enseignant_id: '', jour: '', heure_debut: '', heure_fin: '' }

/**
 * Emplois du temps spéciaux : un membre du personnel dont l'activité à l'école
 * est générale — elle ne se découpe pas en cours — est attendu sur une plage
 * par jour. Son assiduité se calcule sur cette plage, et sa fiche ne porte
 * aucune période à rattraper. L'attribution est libre : n'importe quel membre
 * du personnel peut en recevoir un, et en avoir un suffit à y être soumis.
 */
function EmploisSpeciaux() {
  const [plages, setPlages] = useState([])
  const [personnel, setPersonnel] = useState([])
  const [loading, setLoading] = useState(true)
  const [form, setForm] = useState(PLAGE_VIDE)
  const [edition, setEdition] = useState(null)
  const [saving, setSaving] = useState(false)

  const charger = useCallback(async () => {
    setLoading(true)
    try {
      setPlages(await fetchAllPages('/emplois-speciaux'))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    charger()
    fetchAllPages('/personnel?per_page=500').then((liste) =>
      setPersonnel(liste.map((item) => ({ value: item.id, label: item.nom }))),
    )
  }, [charger])

  function reinitialiser() {
    setForm(PLAGE_VIDE)
    setEdition(null)
  }

  function modifier(plage) {
    setEdition(plage.id)
    setForm({
      enseignant_id: plage.enseignant_id,
      jour: plage.jour,
      heure_debut: toHeureMinute(plage.heure_debut),
      heure_fin: toHeureMinute(plage.heure_fin),
    })
  }

  async function enregistrer(e) {
    e.preventDefault()
    setSaving(true)
    try {
      if (edition) {
        await api.put(`/emplois-speciaux/${edition}`, form)
      } else {
        await api.post('/emplois-speciaux', form)
      }
      reinitialiser()
      await charger()
    } catch (error) {
      notify(messageErreur(error, "L'enregistrement a échoué."), { icon: 'error' })
    } finally {
      setSaving(false)
    }
  }

  async function supprimer(plage) {
    const qui = plage.enseignant?.nom ?? `#${plage.enseignant_id}`
    if (!(await confirmAction(`Supprimer la plage du ${nomJour(plage.jour).toLowerCase()} de ${qui} ?`, { confirmText: 'Supprimer' })))
      return
    await api.delete(`/emplois-speciaux/${plage.id}`)
    await charger()
  }

  // Une personne, ses jours : la même lecture que sur sa fiche d'assiduité.
  const parPersonne = new Map()
  for (const plage of plages) {
    const cle = String(plage.enseignant_id)
    if (!parPersonne.has(cle)) {
      parPersonne.set(cle, { nom: plage.enseignant?.nom ?? `Personnel #${plage.enseignant_id}`, plages: [] })
    }
    parPersonne.get(cle).plages.push(plage)
  }
  const groupes = [...parPersonne.values()].sort((a, b) => a.nom.localeCompare(b.nom, 'fr'))

  return (
    <div className={CARTE}>
      <h2 className="text-sm font-medium text-ink-900">Emplois du temps spéciaux</h2>
      <p className="mt-1 mb-4 text-sm text-ink-500">
        Pour un membre du personnel dont l’activité à l’école est générale et ne se découpe pas en cours. Il est
        attendu, chaque jour déclaré, entre l’heure de début et l’heure de fin indiquées — c’est sur cette base que
        son assiduité est calculée, et sa fiche ne porte aucune période de cours à rattraper. Une seule plage par
        jour et par personne.
      </p>

      {loading ? (
        <LoadingState />
      ) : groupes.length === 0 ? (
        <p className="mb-4 text-sm text-ink-500">Aucun emploi du temps spécial.</p>
      ) : (
        <div className="mb-4 space-y-3">
          {groupes.map((groupe) => (
            <div key={groupe.nom} className="rounded-md border border-ink-100">
              <div className="border-b border-ink-100 px-3 py-2 text-sm font-semibold text-ink-900">{groupe.nom}</div>
              {groupe.plages
                .slice()
                .sort((a, b) => a.jour - b.jour)
                .map((plage) => (
                  <div
                    key={plage.id}
                    className="flex items-center justify-between gap-3 border-t border-ink-50 px-3 py-2 text-sm first:border-t-0"
                  >
                    <span className="text-ink-700">
                      {nomJour(plage.jour)} · {toHeureAffichee(plage.heure_debut)}–
                      {toHeureAffichee(plage.heure_fin)}
                    </span>
                    <span className="shrink-0 whitespace-nowrap">
                      <button onClick={() => modifier(plage)} className="mr-3 text-ink-500 hover:text-ink-900">
                        Éditer
                      </button>
                      <button onClick={() => supprimer(plage)} className="text-red-500 hover:text-red-700">
                        Suppr.
                      </button>
                    </span>
                  </div>
                ))}
            </div>
          ))}
        </div>
      )}

      <form onSubmit={enregistrer} className="flex flex-wrap items-end gap-3 border-t border-ink-100 pt-4">
        <label className="min-w-[14rem] flex-1 text-sm">
          <span className="mb-1 block text-ink-700">Membre du personnel</span>
          <SearchSelect
            id="special-enseignant"
            required
            options={personnel}
            value={form.enseignant_id}
            onChange={(value) => setForm((f) => ({ ...f, enseignant_id: value }))}
          />
        </label>
        <label className="min-w-[10rem] text-sm">
          <span className="mb-1 block text-ink-700">Jour</span>
          <SearchSelect
            id="special-jour"
            required
            options={JOURS}
            value={form.jour}
            onChange={(value) => setForm((f) => ({ ...f, jour: value }))}
          />
        </label>
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Début</span>
          <input
            type="time"
            required
            value={form.heure_debut}
            onChange={(e) => setForm((f) => ({ ...f, heure_debut: e.target.value }))}
            className="rounded-md border border-ink-100 px-3 py-1.5"
          />
        </label>
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Fin</span>
          <input
            type="time"
            required
            value={form.heure_fin}
            onChange={(e) => setForm((f) => ({ ...f, heure_fin: e.target.value }))}
            className="rounded-md border border-ink-100 px-3 py-1.5"
          />
        </label>
        <button
          type="submit"
          disabled={saving || !form.enseignant_id || !form.jour}
          className="rounded-md bg-brand-700 px-3 py-1.5 text-sm text-white hover:bg-brand-800 disabled:opacity-50"
        >
          {edition ? 'Modifier' : 'Ajouter'}
        </button>
        {edition && (
          <button type="button" onClick={reinitialiser} className="px-2 py-1.5 text-sm text-ink-500 hover:text-ink-900">
            Annuler
          </button>
        )}
      </form>
    </div>
  )
}

/* -------------------------------------------------------------------------- */

export default function ConfigurationPage() {
  const [tab, setTab] = useState('periodes')

  const onglets = [
    ['periodes', 'Périodes de cours'],
    ['speciaux', 'Emplois du temps spéciaux'],
    ['administratif', 'Horaires administratifs'],
  ]

  return (
    <div>
      <h1 className="mb-4 text-lg font-semibold text-ink-900">Configuration</h1>

      <div className="mb-4 flex flex-wrap gap-2">
        {onglets.map(([cle, label]) => (
          <button
            key={cle}
            onClick={() => setTab(cle)}
            className={`rounded-md px-3 py-1.5 text-sm ${
              tab === cle ? 'bg-brand-700 text-white' : 'border border-ink-100 bg-white text-ink-700'
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      {tab === 'periodes' && <PeriodesCours />}
      {tab === 'speciaux' && <EmploisSpeciaux />}
      {tab === 'administratif' && <HorairesAdministratifs />}
    </div>
  )
}
