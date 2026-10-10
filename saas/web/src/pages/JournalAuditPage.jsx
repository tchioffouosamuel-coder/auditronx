import { useEffect, useMemo, useState } from 'react'
import DataTable from '../components/DataTable'
import Modal from '../components/Modal'
import api from '../lib/api'
import { formatDateTime, startOfMonthIso, todayIso } from '../lib/datetime'

/** Entrées rapatriées au maximum, pour que la recherche porte sur tout le journal. */
const TAILLE_PAGE = 500
const MAX_PAGES = 40

/**
 * Traduction des identifiants d'action en libellés lisibles. Les identifiants
 * restent techniques en base (stables, filtrables) ; seule l'étiquette change.
 */
const VERBES = {
  cree: 'Création',
  modifie: 'Modification',
  supprime: 'Suppression',
  supprime_definitivement: 'Suppression définitive',
  restaure: 'Restauration',
  refuse: 'Tentative refusée',
  post: 'Action',
  put: 'Action',
  patch: 'Action',
  delete: 'Suppression',
  get: 'Consultation',
}

const SUJETS = {
  enseignant: 'Personnel',
  user: 'Compte',
  accreditation: 'Accréditation',
  classe: 'Classe',
  discipline: 'Discipline',
  emploi_du_temps: 'Emploi du temps',
  ferie: 'Jour férié',
  signalement: 'Signalement',
  presence: 'Présence',
  cours_validation: 'Validation de cours',
  device: 'Appareil',
  access_point: "Point d'accès",
  qr_point: 'Point QR',
  firmware: 'Firmware',
  parametre: 'Paramètre',
  programme: 'Programme',
  cahier_texte_entree: 'Cahier de texte',
  login: 'Connexion',
  logout: 'Déconnexion',
  spreadsheet: 'Export / import',
  personnel: 'Personnel',
}

function libelleAction(action) {
  const morceaux = String(action ?? '').split('.')
  const verbe = VERBES[morceaux.at(-1)] ?? morceaux.at(-1)
  const sujet = SUJETS[morceaux[0]] ?? morceaux[0]

  return `${verbe} — ${sujet}`
}

const COULEUR_VERBE = {
  Suppression: 'bg-red-50 text-red-700',
  'Suppression définitive': 'bg-red-100 text-red-800',
  'Tentative refusée': 'bg-amber-50 text-amber-800',
  Création: 'bg-emerald-50 text-emerald-700',
  Restauration: 'bg-emerald-50 text-emerald-700',
  Modification: 'bg-blue-50 text-blue-700',
}

function valeurLisible(valeur) {
  if (valeur === null || valeur === undefined || valeur === '') return '—'
  if (typeof valeur === 'boolean') return valeur ? 'Oui' : 'Non'
  if (typeof valeur === 'object') return JSON.stringify(valeur)
  return String(valeur)
}

function DetailEntree({ entree, onClose }) {
  const changements = Object.entries(entree.changements ?? {})

  return (
    <Modal title={libelleAction(entree.action)} onClose={onClose}>
      <dl className="mb-4 grid grid-cols-3 gap-x-3 gap-y-2 text-sm">
        <dt className="text-ink-500">Quand</dt>
        <dd className="col-span-2 text-ink-900">{formatDateTime(entree.created_at)}</dd>

        <dt className="text-ink-500">Qui</dt>
        <dd className="col-span-2 text-ink-900">
          {entree.auteur_nom ?? 'Inconnu'}
          {entree.auteur_email && <span className="text-ink-500"> · {entree.auteur_email}</span>}
          {entree.auteur_accreditation && (
            <span className="text-ink-500"> · {entree.auteur_accreditation}</span>
          )}
        </dd>

        <dt className="text-ink-500">Sur quoi</dt>
        <dd className="col-span-2 text-ink-900">{entree.sujet_libelle ?? '—'}</dd>

        <dt className="text-ink-500">Depuis</dt>
        <dd className="col-span-2 text-ink-900">{entree.ip ?? '—'}</dd>

        <dt className="text-ink-500">Requête</dt>
        <dd className="col-span-2 break-all text-ink-700">
          {entree.methode} {entree.route ?? entree.url}
          {entree.statut ? ` · ${entree.statut}` : ''}
        </dd>

        <dt className="text-ink-500">Identifiant</dt>
        <dd className="col-span-2 font-mono text-xs text-ink-500">{entree.action}</dd>
      </dl>

      {changements.length > 0 && (
        <div className="mb-4">
          <div className="mb-2 text-sm font-medium text-ink-900">Valeurs</div>
          <div className="overflow-hidden rounded-lg border border-ink-100">
            <table className="min-w-full divide-y divide-ink-100 text-sm">
              <thead className="bg-ink-50 text-left text-xs text-ink-500">
                <tr>
                  <th className="px-3 py-2">Champ</th>
                  <th className="px-3 py-2">Avant</th>
                  <th className="px-3 py-2">Après</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-ink-100">
                {changements.map(([champ, valeur]) => (
                  <tr key={champ}>
                    <td className="px-3 py-2 font-medium text-ink-700">{champ}</td>
                    <td className="px-3 py-2 text-ink-500">
                      {valeurLisible(valeur?.avant ?? (typeof valeur === 'object' ? undefined : valeur))}
                    </td>
                    <td className="px-3 py-2 text-ink-900">
                      {valeurLisible(valeur?.apres ?? (typeof valeur === 'object' ? undefined : valeur))}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {entree.contexte && (
        <div>
          <div className="mb-2 text-sm font-medium text-ink-900">Contexte</div>
          <pre className="max-h-60 overflow-auto rounded-lg bg-ink-50 p-3 text-xs text-ink-700">
            {JSON.stringify(entree.contexte, null, 2)}
          </pre>
        </div>
      )}
    </Modal>
  )
}

export default function JournalAuditPage() {
  const [entrees, setEntrees] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [debut, setDebut] = useState(startOfMonthIso)
  const [fin, setFin] = useState(todayIso)
  const [selection, setSelection] = useState(null)

  useEffect(() => {
    let annule = false

    async function charger() {
      setLoading(true)
      setError(null)
      try {
        const toutes = []
        let page = 1
        let lastPage = 1

        do {
          const { data } = await api.get('/audit-logs', {
            params: { debut, fin, page, per_page: TAILLE_PAGE },
          })
          toutes.push(...(data.data ?? []))
          lastPage = Number(data.last_page ?? 1)
          page += 1
        } while (page <= lastPage && page <= MAX_PAGES)

        if (!annule) setEntrees(toutes)
      } catch (err) {
        if (!annule) {
          setError(
            err.response?.status === 403
              ? "Le journal d'audit est réservé aux accréditations à accès total."
              : "Impossible de charger le journal.",
          )
        }
      } finally {
        if (!annule) setLoading(false)
      }
    }

    charger()

    return () => {
      annule = true
    }
  }, [debut, fin])

  const colonnes = useMemo(
    () => [
      {
        key: 'created_at',
        label: 'Quand',
        render: (e) => formatDateTime(e.created_at),
        sortValue: (e) => e.created_at,
        searchValue: (e) => formatDateTime(e.created_at),
      },
      {
        key: 'auteur_nom',
        label: 'Qui',
        render: (e) => e.auteur_nom ?? 'Inconnu',
        searchValue: (e) => `${e.auteur_nom ?? ''} ${e.auteur_email ?? ''}`,
      },
      {
        key: 'action',
        label: 'Action',
        render: (e) => {
          const libelle = libelleAction(e.action)
          const verbe = libelle.split(' — ')[0]

          return (
            <span
              className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                COULEUR_VERBE[verbe] ?? 'bg-ink-50 text-ink-700'
              }`}
            >
              {libelle}
            </span>
          )
        },
        searchValue: (e) => `${libelleAction(e.action)} ${e.action}`,
        sortValue: (e) => e.action,
      },
      { key: 'sujet_libelle', label: 'Sur quoi', render: (e) => e.sujet_libelle ?? '—' },
      { key: 'auteur_accreditation', label: 'Accréditation', render: (e) => e.auteur_accreditation ?? '—' },
      { key: 'ip', label: 'Depuis', render: (e) => e.ip ?? '—' },
    ],
    [],
  )

  return (
    <div>
      <h1 className="page-title mb-1">Journal d’audit</h1>
      <p className="mb-4 max-w-3xl text-sm text-ink-500">
        Trace de toutes les actions ayant modifié les données, ainsi que des exports et des
        tentatives refusées. Le journal est en lecture seule : il ne peut être ni modifié ni purgé
        depuis le backoffice.
      </p>

      <div className="mb-4 flex flex-wrap items-end gap-3">
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Du</span>
          <input
            type="date"
            value={debut}
            onChange={(e) => setDebut(e.target.value)}
            className="field py-2"
          />
        </label>
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Au</span>
          <input
            type="date"
            value={fin}
            onChange={(e) => setFin(e.target.value)}
            className="field py-2"
          />
        </label>
      </div>

      {error && <div className="mb-4 rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{error}</div>}

      <DataTable
        loading={loading}
        rows={entrees}
        columns={colonnes}
        pageSize={25}
        emptyMessage="Aucune action sur cette période."
        searchPlaceholder="Nom, action, fiche, adresse IP…"
        actionsLabel=""
        renderActions={(entree) => (
          <button
            onClick={() => setSelection(entree)}
            className="text-brand-700 hover:text-brand-900"
          >
            Détail
          </button>
        )}
      />

      {selection && <DetailEntree entree={selection} onClose={() => setSelection(null)} />}
    </div>
  )
}
