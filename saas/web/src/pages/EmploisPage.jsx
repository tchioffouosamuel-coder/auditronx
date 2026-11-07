import { useCallback, useEffect, useMemo, useState } from 'react'
import LoadingState from '../components/LoadingState'
import Modal from '../components/Modal'
import SearchSelect from '../components/SearchSelect'
import SpreadsheetActions from '../components/SpreadsheetActions'
import api from '../lib/api'
import fetchAllPages from '../lib/fetchAllPages'
import { confirmAction, notify } from '../lib/swal'

export const JOURS = [
  { value: 1, label: 'Lundi' },
  { value: 2, label: 'Mardi' },
  { value: 3, label: 'Mercredi' },
  { value: 4, label: 'Jeudi' },
  { value: 5, label: 'Vendredi' },
  { value: 6, label: 'Samedi' },
  { value: 7, label: 'Dimanche' },
]

export const nomJour = (jour) => JOURS.find((j) => j.value === Number(jour))?.label ?? 'Jour inconnu'

/** Une colonne SQL `time` revient en « 08:10:00 » : on retombe sur « HH:MM ». */
export const toHeureMinute = (valeur) => (valeur ? String(valeur).slice(0, 5) : '')

export const toHeureAffichee = (valeur) => toHeureMinute(valeur).replace(':', 'h')

const plageAffichee = (debut, fin) => `${toHeureAffichee(debut)}–${toHeureAffichee(fin)}`

/** Durée d'une période, en minutes — même convention que le calcul des périodes à rattraper côté API (BilanIndividuel, RetardsController). */
export const DUREE_PERIODE_MINUTES = 40

/** Heure de fin à partir d'une heure de début et d'un nombre de périodes : évite de saisir une fin incohérente avec le début. */
export function finApresPeriodes(heureDebut, nombrePeriodes) {
  if (!heureDebut || !nombrePeriodes) return ''
  const [h, m] = heureDebut.split(':').map(Number)
  const total = h * 60 + m + Number(nombrePeriodes) * DUREE_PERIODE_MINUTES
  const heure = Math.floor(total / 60) % 24
  const minute = total % 60
  return `${String(heure).padStart(2, '0')}:${String(minute).padStart(2, '0')}`
}

/** Nombre de périodes entre deux heures (arrondi, minimum 1) — pour pré-remplir le formulaire en édition. */
export function nombrePeriodesEntre(heureDebut, heureFin) {
  if (!heureDebut || !heureFin) return 1
  const [h1, m1] = heureDebut.split(':').map(Number)
  const [h2, m2] = heureFin.split(':').map(Number)
  const diff = h2 * 60 + m2 - (h1 * 60 + m1)
  return Math.max(1, Math.round(diff / DUREE_PERIODE_MINUTES))
}

const nomClasse = (creneau) => creneau.classe?.nom ?? `Classe #${creneau.classe_id}`
const nomDiscipline = (creneau) => creneau.discipline?.nom ?? `Matière #${creneau.discipline_id}`
const nomEnseignant = (creneau) => creneau.enseignant?.nom ?? `Enseignant #${creneau.enseignant_id}`

/** Désignation complète d'une séance, telle qu'elle est lue dans une alerte. */
const designation = (creneau) =>
  `${nomDiscipline(creneau)} · ${nomClasse(creneau)} · ${nomEnseignant(creneau)}`

/**
 * Périodes de cours de l'établissement (configurées dans Configuration). Le
 * couple début/fin est porté par la période : les deux listes indépendantes
 * d'avant laissaient composer des créneaux impossibles (début 15h30, fin
 * 08h10).
 */
function usePeriodes() {
  const [periodes, setPeriodes] = useState([])

  useEffect(() => {
    api
      .get('/periodes-cours')
      .then(({ data }) => setPeriodes(data.data ?? []))
      .catch(() => setPeriodes([]))
  }, [])

  return periodes
}

/* -------------------------------------------------------------------------- */
/* Formulaire d'un créneau                                                     */
/* -------------------------------------------------------------------------- */

function CreneauModal({ creneau, periodes, options, onClose, onSaved }) {
  const [valeurs, setValeurs] = useState(() => ({
    enseignant_id: creneau?.enseignant_id ?? '',
    classe_id: creneau?.classe_id ?? '',
    discipline_id: creneau?.discipline_id ?? '',
    jour: creneau?.jour ?? '',
    heure_debut: toHeureMinute(creneau?.heure_debut),
    heure_fin: toHeureMinute(creneau?.heure_fin),
    salle: creneau?.salle ?? '',
    type_cours: creneau?.type_cours ?? '',
  }))
  const [erreurs, setErreurs] = useState({})
  const [enregistrement, setEnregistrement] = useState(false)

  const optionsPeriodes = periodes.map((p) => ({
    value: `${p.heure_debut}|${p.heure_fin}`,
    label: p.libelle_complet ?? plageAffichee(p.heure_debut, p.heure_fin),
  }))

  const periodeCourante = valeurs.heure_debut ? `${valeurs.heure_debut}|${valeurs.heure_fin}` : ''

  // Un créneau saisi avant une reconfiguration de la grille peut ne tomber sur
  // aucune période : on le montre tel quel plutôt que de vider la liste et de
  // laisser croire que l'horaire est perdu.
  const horsPeriode = periodeCourante && !optionsPeriodes.some((o) => o.value === periodeCourante)

  async function soumettre(e) {
    e.preventDefault()
    setErreurs({})
    setEnregistrement(true)
    try {
      if (creneau?.id) {
        await api.put(`/emplois/${creneau.id}`, valeurs)
      } else {
        await api.post('/emplois', valeurs)
      }
      onSaved()
    } catch (error) {
      setErreurs(error.response?.data?.errors ?? {})
    } finally {
      setEnregistrement(false)
    }
  }

  const champSelect = (cle, label, liste, requis = true) => (
    <label className="mb-3 block text-sm">
      <span className="mb-1 block text-ink-700">{label}</span>
      <SearchSelect
        id={`creneau-${cle}`}
        required={requis}
        options={liste}
        value={valeurs[cle]}
        onChange={(value) => setValeurs((v) => ({ ...v, [cle]: value }))}
      />
      {erreurs[cle] && <span className="mt-1 block text-xs text-red-600">{erreurs[cle][0]}</span>}
    </label>
  )

  return (
    <Modal title={creneau?.id ? 'Modifier le créneau' : 'Nouveau créneau'} onClose={onClose}>
      <form onSubmit={soumettre}>
        {champSelect('enseignant_id', 'Enseignant', options.enseignants)}
        {champSelect('classe_id', 'Classe', options.classes)}
        {champSelect('discipline_id', 'Discipline', options.disciplines)}
        {champSelect('jour', 'Jour', JOURS)}

        <label className="mb-3 block text-sm">
          <span className="mb-1 block text-ink-700">Période de cours</span>
          <SearchSelect
            id="creneau-periode"
            required
            options={
              horsPeriode
                ? [
                    ...optionsPeriodes,
                    {
                      value: periodeCourante,
                      label: `${plageAffichee(valeurs.heure_debut, valeurs.heure_fin)} (hors période)`,
                    },
                  ]
                : optionsPeriodes
            }
            value={periodeCourante}
            onChange={(value) => {
              const [debut = '', fin = ''] = String(value ?? '').split('|')
              setValeurs((v) => ({ ...v, heure_debut: debut, heure_fin: fin }))
            }}
          />
          {(erreurs.heure_debut || erreurs.heure_fin) && (
            <span className="mt-1 block text-xs text-red-600">
              {(erreurs.heure_debut ?? erreurs.heure_fin)[0]}
            </span>
          )}
          {periodes.length === 0 && (
            <span className="mt-1 block text-xs text-ink-500">
              Aucune période configurée. Définissez la grille horaire dans Configuration.
            </span>
          )}
        </label>

        {['salle', 'type_cours'].map((cle) => (
          <label key={cle} className="mb-3 block text-sm">
            <span className="mb-1 block text-ink-700">{cle === 'salle' ? 'Salle' : 'Type de cours'}</span>
            <input
              value={valeurs[cle] ?? ''}
              onChange={(e) => setValeurs((v) => ({ ...v, [cle]: e.target.value }))}
              className="field w-full"
            />
            {erreurs[cle] && <span className="mt-1 block text-xs text-red-600">{erreurs[cle][0]}</span>}
          </label>
        ))}

        <button
          type="submit"
          disabled={enregistrement}
          className="btn-primary mt-2 w-full"
        >
          {enregistrement ? 'Enregistrement…' : 'Enregistrer'}
        </button>
      </form>
    </Modal>
  )
}

/* -------------------------------------------------------------------------- */
/* Vues groupées                                                               */
/* -------------------------------------------------------------------------- */

function CreneauLigne({ creneau, libelle, onEdit, onDelete }) {
  return (
    <div className="flex items-center justify-between gap-3 border-t border-ink-100 px-4 py-2 text-sm first:border-t-0">
      <div className="min-w-0">
        <div className="truncate font-medium text-ink-900">
          {plageAffichee(creneau.heure_debut, creneau.heure_fin)} · {libelle}
        </div>
        <div className="truncate text-xs text-ink-500">
          {[creneau.salle, creneau.type_cours].filter(Boolean).join(' · ') || '—'}
        </div>
      </div>
      <div className="shrink-0 whitespace-nowrap">
        <button onClick={() => onEdit(creneau)} className="mr-3 text-ink-500 hover:text-ink-900">
          Éditer
        </button>
        <button onClick={() => onDelete(creneau)} className="text-red-500 hover:text-red-700">
          Suppr.
        </button>
      </div>
    </div>
  )
}

/**
 * Regroupement commun aux deux onglets : une entité (classe ou enseignant),
 * ses jours, ses séances. Le regroupement se fait sur l'identifiant et non sur
 * le nom — après une édition l'API renvoie le créneau sans ses relations, et
 * grouper par nom aurait créé une entrée « Classe #12 » à côté de la vraie.
 */
function GroupedSchedule({ creneaux, cleGroupe, nomGroupe, libelleSeance, recherche, onEdit, onDelete }) {
  const [filtre, setFiltre] = useState('')

  const groupes = useMemo(() => {
    const parGroupe = new Map()
    const noms = new Map()

    for (const creneau of creneaux) {
      const cle = String(creneau[cleGroupe] ?? '—')
      if (!parGroupe.has(cle)) parGroupe.set(cle, [])
      parGroupe.get(cle).push(creneau)

      const nom = nomGroupe(creneau)
      if (nom && !nom.startsWith('Classe #') && !nom.startsWith('Enseignant #')) noms.set(cle, nom)
    }

    return [...parGroupe.entries()]
      .map(([cle, items]) => ({ cle, nom: noms.get(cle) ?? nomGroupe(items[0]), items }))
      .sort((a, b) => a.nom.localeCompare(b.nom, 'fr'))
  }, [creneaux, cleGroupe, nomGroupe])

  const visibles = filtre.trim()
    ? groupes.filter((g) => g.nom.toLowerCase().includes(filtre.trim().toLowerCase()))
    : groupes

  return (
    <div>
      <input
        value={filtre}
        onChange={(e) => setFiltre(e.target.value)}
        placeholder={recherche}
        className="field mb-4 w-full max-w-sm"
      />

      {visibles.length === 0 ? (
        <p className="text-sm text-ink-500">Aucun résultat.</p>
      ) : (
        <div className="space-y-3">
          {visibles.map((groupe) => {
            const parJour = new Map()
            for (const creneau of groupe.items) {
              const jour = Number(creneau.jour) || 0
              if (!parJour.has(jour)) parJour.set(jour, [])
              parJour.get(jour).push(creneau)
            }
            const jours = [...parJour.keys()].sort((a, b) => a - b)

            return (
              <details key={groupe.cle} className="card">
                <summary className="cursor-pointer px-4 py-3 text-sm font-semibold text-ink-900">
                  {groupe.nom}
                  <span className="ml-2 font-normal text-ink-500">
                    {groupe.items.length} séance{groupe.items.length > 1 ? 's' : ''}
                  </span>
                </summary>
                {jours.map((jour) => (
                  <div key={jour}>
                    <div className="bg-ink-50 px-4 py-1.5 text-xs font-semibold tracking-wide text-ink-700 uppercase">
                      {nomJour(jour)}
                    </div>
                    {parJour
                      .get(jour)
                      .slice()
                      .sort((a, b) => String(a.heure_debut).localeCompare(String(b.heure_debut)))
                      .map((creneau) => (
                        <CreneauLigne
                          key={creneau.id}
                          creneau={creneau}
                          libelle={libelleSeance(creneau)}
                          onEdit={onEdit}
                          onDelete={onDelete}
                        />
                      ))}
                  </div>
                ))}
              </details>
            )
          })}
        </div>
      )}
    </div>
  )
}

/* -------------------------------------------------------------------------- */
/* Chevauchements                                                              */
/* -------------------------------------------------------------------------- */

const libelleMotifs = (motifs) =>
  motifs
    .map((motif) => (motif === 'enseignant' ? 'même enseignant' : 'même classe'))
    .join(' et ')

/**
 * Sheet de résolution : les deux séances en présence, côte à côte, et pour
 * chacune l'arbitrage possible — la déplacer (horaire, jour, salle) ou la
 * supprimer. L'alerte seule ne suffisait pas : elle laissait l'administrateur
 * retrouver à la main, dans la grille, lequel des deux cours déplacer.
 */
function ResolutionModal({ conflit, onClose, onEdit, onDelete }) {
  const carte = (creneau, titre) => (
    <div className="card p-3">
      <div className="mb-1 text-xs font-semibold tracking-wide text-ink-500 uppercase">{titre}</div>
      <div className="text-sm font-semibold text-ink-900">{nomDiscipline(creneau)}</div>
      <dl className="mt-2 space-y-1 text-sm text-ink-700">
        <div className="flex justify-between gap-3">
          <dt className="text-ink-500">Classe</dt>
          <dd className="text-right font-medium">{nomClasse(creneau)}</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt className="text-ink-500">Enseignant</dt>
          <dd className="text-right font-medium">{nomEnseignant(creneau)}</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt className="text-ink-500">Horaire</dt>
          <dd className="text-right font-medium">{plageAffichee(creneau.heure_debut, creneau.heure_fin)}</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt className="text-ink-500">Salle</dt>
          <dd className="text-right font-medium">{creneau.salle || '—'}</dd>
        </div>
      </dl>
      <div className="mt-3 flex gap-2">
        <button
          onClick={() => onEdit(creneau)}
          className="btn-secondary flex-1"
        >
          Déplacer
        </button>
        <button
          onClick={() => onDelete(creneau)}
          className="flex-1 rounded-md bg-red-600 py-1.5 text-sm text-white hover:bg-red-700"
        >
          Supprimer
        </button>
      </div>
    </div>
  )

  return (
    <Modal title="Résoudre le chevauchement" onClose={onClose}>
      <p className="mb-4 text-sm text-ink-700">
        {nomJour(conflit.jour)} de {plageAffichee(conflit.heure_debut, conflit.heure_fin)}, ces deux séances se
        superposent ({libelleMotifs(conflit.motifs)}). Déplacez-en une sur une autre période, ou supprimez
        celle qui est en trop.
      </p>
      <div className="space-y-3">
        {carte(conflit.a, 'Séance 1')}
        {carte(conflit.b, 'Séance 2')}
      </div>
    </Modal>
  )
}

function ConflitsTab({ conflits, chargement, onEdit, onDelete }) {
  const [ouvert, setOuvert] = useState(null)

  if (chargement) return <LoadingState />

  if (conflits.length === 0) {
    return (
      <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
        Aucun chevauchement : chaque enseignant et chaque classe n’a qu’une séance à la fois.
      </div>
    )
  }

  return (
    <div className="space-y-3">
      {conflits.map((conflit, index) => (
        <div
          key={`${conflit.a.id}-${conflit.b.id}`}
          className="rounded-lg border border-amber-200 bg-amber-50 p-4"
        >
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0 text-sm">
              <div className="font-semibold text-amber-900">
                {nomJour(conflit.jour)} · {plageAffichee(conflit.heure_debut, conflit.heure_fin)} ·{' '}
                {libelleMotifs(conflit.motifs)}
              </div>
              <ul className="mt-2 space-y-1 text-amber-900">
                <li>
                  <span className="font-medium">{designation(conflit.a)}</span>{' '}
                  <span className="text-amber-700">
                    ({plageAffichee(conflit.a.heure_debut, conflit.a.heure_fin)}
                    {conflit.a.salle ? ` · ${conflit.a.salle}` : ''})
                  </span>
                </li>
                <li>
                  <span className="font-medium">{designation(conflit.b)}</span>{' '}
                  <span className="text-amber-700">
                    ({plageAffichee(conflit.b.heure_debut, conflit.b.heure_fin)}
                    {conflit.b.salle ? ` · ${conflit.b.salle}` : ''})
                  </span>
                </li>
              </ul>
            </div>
            <button
              onClick={() => setOuvert(index)}
              className="shrink-0 rounded-md bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700"
            >
              Résoudre
            </button>
          </div>
        </div>
      ))}

      {ouvert !== null && conflits[ouvert] && (
        <ResolutionModal
          conflit={conflits[ouvert]}
          onClose={() => setOuvert(null)}
          onEdit={(creneau) => {
            setOuvert(null)
            onEdit(creneau)
          }}
          onDelete={async (creneau) => {
            setOuvert(null)
            await onDelete(creneau)
          }}
        />
      )}
    </div>
  )
}

/* -------------------------------------------------------------------------- */

export default function EmploisPage() {
  const [tab, setTab] = useState('classes')
  const [creneaux, setCreneaux] = useState([])
  const [conflits, setConflits] = useState([])
  const [chargement, setChargement] = useState(true)
  const [erreur, setErreur] = useState(null)
  const [edition, setEdition] = useState(null) // null = fermé, {} = création
  const [options, setOptions] = useState({ enseignants: [], classes: [], disciplines: [] })
  const periodes = usePeriodes()

  const charger = useCallback(async () => {
    setChargement(true)
    setErreur(null)
    try {
      const [liste, { data }] = await Promise.all([
        fetchAllPages('/emplois'),
        api.get('/emplois/conflits'),
      ])
      setCreneaux(liste)
      setConflits(data.data ?? [])
    } catch {
      setErreur('Impossible de charger les emplois du temps.')
    } finally {
      setChargement(false)
    }
  }, [])

  useEffect(() => {
    charger()
  }, [charger])

  useEffect(() => {
    Promise.all([
      fetchAllPages('/personnel?per_page=500'),
      fetchAllPages('/classes'),
      fetchAllPages('/disciplines'),
    ]).then(([enseignants, classes, disciplines]) => {
      const enOptions = (liste) => liste.map((item) => ({ value: item.id, label: item.nom }))
      setOptions({
        enseignants: enOptions(enseignants),
        classes: enOptions(classes),
        disciplines: enOptions(disciplines),
      })
    })
  }, [])

  const supprimer = useCallback(
    async (creneau) => {
      const confirme = await confirmAction(
        `Supprimer ${designation(creneau)}, ${nomJour(creneau.jour)} de ${plageAffichee(
          creneau.heure_debut,
          creneau.heure_fin,
        )} ?`,
        { confirmText: 'Supprimer' },
      )
      if (!confirme) return
      try {
        await api.delete(`/emplois/${creneau.id}`)
        await charger()
      } catch {
        notify('La suppression a échoué.', { icon: 'error' })
      }
    },
    [charger],
  )

  const onglets = [
    ['classes', 'Par classe'],
    ['enseignants', 'Par enseignant'],
    ['conflits', `Chevauchements${conflits.length ? ` (${conflits.length})` : ''}`],
  ]

  return (
    <div>
      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="page-title">Emplois du temps</h1>
        <button
          onClick={() => setEdition({})}
          className="btn-primary self-start sm:ml-auto"
        >
          + Nouveau
        </button>
      </div>

      <SpreadsheetActions entity="emplois" label="emplois du temps" onImported={charger} />

      {conflits.length > 0 && tab !== 'conflits' && (
        <button
          onClick={() => setTab('conflits')}
          className="mb-4 block w-full rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-left text-sm text-amber-900 hover:bg-amber-100"
        >
          ⚠ {conflits.length} chevauchement{conflits.length > 1 ? 's' : ''} détecté
          {conflits.length > 1 ? 's' : ''} — voir le détail et résoudre.
        </button>
      )}

      <div className="mb-4 flex flex-wrap gap-2">
        {onglets.map(([cle, label]) => (
          <button
            key={cle}
            onClick={() => setTab(cle)}
            className={`${
              tab === cle ? 'btn-primary' : 'btn-secondary'
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      {erreur && <div className="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{erreur}</div>}

      {chargement && tab !== 'conflits' ? (
        <LoadingState />
      ) : tab === 'classes' ? (
        <GroupedSchedule
          creneaux={creneaux}
          cleGroupe="classe_id"
          nomGroupe={nomClasse}
          libelleSeance={(creneau) => `${nomDiscipline(creneau)} · ${nomEnseignant(creneau)}`}
          recherche="Rechercher une classe…"
          onEdit={setEdition}
          onDelete={supprimer}
        />
      ) : tab === 'enseignants' ? (
        <GroupedSchedule
          creneaux={creneaux}
          cleGroupe="enseignant_id"
          nomGroupe={nomEnseignant}
          libelleSeance={(creneau) => `${nomDiscipline(creneau)} · ${nomClasse(creneau)}`}
          recherche="Rechercher un enseignant…"
          onEdit={setEdition}
          onDelete={supprimer}
        />
      ) : (
        <ConflitsTab
          conflits={conflits}
          chargement={chargement}
          onEdit={setEdition}
          onDelete={supprimer}
        />
      )}

      {edition && (
        <CreneauModal
          creneau={edition.id ? edition : null}
          periodes={periodes}
          options={options}
          onClose={() => setEdition(null)}
          onSaved={() => {
            setEdition(null)
            charger()
          }}
        />
      )}
    </div>
  )
}
