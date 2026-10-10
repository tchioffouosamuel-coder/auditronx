import { useEffect, useState } from 'react'
import Select from 'react-select'
import api from '../lib/api'
import { confirmAction, notify } from '../lib/swal'
import DataTable from './DataTable'
import Modal from './Modal'
import PasswordInput from './PasswordInput'

/** Styles react-select alignés sur l'utilitaire `.field` (cf. index.css). */
const SELECT_STYLES = {
  control: (base, state) => ({
    ...base,
    minHeight: '2.75rem',
    borderRadius: '0.75rem',
    borderColor: state.isFocused ? 'var(--color-brand-500)' : 'var(--color-brand-200)',
    boxShadow: 'none',
    '&:hover': { borderColor: 'var(--color-brand-300)' },
  }),
  option: (base, state) => ({
    ...base,
    backgroundColor: state.isSelected
      ? 'var(--color-brand-700)'
      : state.isFocused
        ? 'var(--color-brand-50)'
        : 'white',
    color: state.isSelected ? 'white' : 'var(--color-ink-900)',
  }),
  menu: (base) => ({
    ...base,
    zIndex: 50,
    borderRadius: '0.75rem',
    overflow: 'hidden',
    border: '1px solid var(--color-brand-200)',
  }),
  placeholder: (base) => ({ ...base, color: 'var(--color-ink-300)' }),
}

/** Garde-fou : plafonne le nombre d'allers-retours si l'API pagine à l'infini. */
const MAX_PAGES = 100

/**
 * Récupère l'intégralité d'une ressource en suivant la pagination Laravel.
 *
 * La recherche, le tri et la pagination de `DataTable` travaillent sur le
 * tableau déjà en mémoire : ne lire que la première page revenait à chercher
 * dans les 25 (ou 50) premières lignes seulement — un enseignant classé après
 * la lettre B ressortait en « 0 résultat » alors qu'il existe bien en base.
 * Les listes déroulantes souffraient du même tronquage. On agrège donc toutes
 * les pages avant d'afficher.
 */
async function fetchAllPages(url) {
  const all = []
  let page = 1
  let lastPage = 1

  do {
    const { data } = await api.get(url, { params: { page } })

    if (Array.isArray(data)) {
      all.push(...data)
      break
    }

    all.push(...(data.data ?? []))
    lastPage = Number(data.last_page ?? 1)
    page += 1
  } while (page <= lastPage && page <= MAX_PAGES)

  return all
}

function Icone({ nom, className = '' }) {
  return (
    <span aria-hidden="true" className={`material-symbols-rounded text-[18px] ${className}`}>
      {nom}
    </span>
  )
}

/**
 * Table CRUD générique pilotée par un schéma de champs — évite de réécrire le
 * même boilerplate (liste, création, édition, suppression) pour chaque module
 * de gestion simple (classes, disciplines, féries, accréditations, ...).
 *
 * `fields`: [{ key, label, type: 'text'|'number'|'select', options?, optionsUrl?,
 *              optionLabel?, required? }]
 * `columns`: [{ key, label, render?(row) }] — par défaut dérivées de `fields`.
 */
export default function ResourceTable({
  title,
  subtitle,
  createLabel = 'Nouveau',
  resource,
  fields,
  columns,
  idKey = 'id',
  extraRowActions,
}) {
  const [rows, setRows] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [editing, setEditing] = useState(null) // null = fermé, {} = création, {...} = édition
  const [formValues, setFormValues] = useState({})
  const [formErrors, setFormErrors] = useState({})
  const [enregistrement, setEnregistrement] = useState(false)
  const [optionsByField, setOptionsByField] = useState({})

  const displayColumns = columns ?? fields.map((f) => ({ key: f.key, label: f.label }))

  async function load() {
    setLoading(true)
    setError(null)
    try {
      setRows(await fetchAllPages(resource))
    } catch {
      setError("Impossible de charger les données.")
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [resource])

  useEffect(() => {
    fields
      .filter((f) => f.type === 'select' && f.optionsUrl)
      .forEach((f) => {
        fetchAllPages(f.optionsUrl).then((list) => {
          setOptionsByField((prev) => ({ ...prev, [f.key]: list }))
        })
      })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  function openCreate() {
    setFormValues({})
    setFormErrors({})
    setEditing({})
  }

  function openEdit(row) {
    setFormValues(row)
    setFormErrors({})
    setEditing(row)
  }

  async function handleDelete(row) {
    if (!(await confirmAction('Confirmer la suppression ?', { confirmText: 'Supprimer' }))) return

    /*
      La suppression partait sans `catch` : un refus de l'API (contrainte de
      clé étrangère, droits insuffisants) ne laissait aucune trace à l'écran,
      la ligne restait là et on recliquait sans comprendre.
    */
    try {
      await api.delete(`${resource}/${row[idKey]}`)
    } catch (err) {
      notify(
        err.response?.data?.message ?? 'Suppression impossible. La ligne est peut-être utilisée ailleurs.',
        { title: 'Échec de la suppression', icon: 'error' },
      )
      return
    }

    load()
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setFormErrors({})
    setEnregistrement(true)
    try {
      if (editing[idKey]) {
        await api.put(`${resource}/${editing[idKey]}`, formValues)
      } else {
        await api.post(resource, formValues)
      }
      setEditing(null)
      load()
    } catch (err) {
      const erreurs = err.response?.data?.errors ?? {}
      setFormErrors(erreurs)

      // Une erreur sans détail par champ (409, 500, réseau) n'affichait rien :
      // le formulaire semblait simplement ne pas réagir au clic.
      if (Object.keys(erreurs).length === 0) {
        notify(err.response?.data?.message ?? "L'enregistrement a échoué.", {
          title: 'Échec',
          icon: 'error',
        })
      }
    } finally {
      setEnregistrement(false)
    }
  }

  return (
    <div>
      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        {title && (
          <div>
            <h1 className="page-title">{title}</h1>
            {subtitle && <p className="page-subtitle">{subtitle}</p>}
          </div>
        )}
        <button onClick={openCreate} className="btn-primary sm:ml-auto">
          <Icone nom="add" />
          {createLabel}
        </button>
      </div>

      {error && (
        <div
          role="alert"
          className="mb-4 flex items-center justify-between gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
        >
          <span className="flex items-center gap-2">
            <Icone nom="cloud_off" />
            {error}
          </span>
          {/* Un rechargement à portée de clic : sinon la seule issue est de
              recharger la page entière et de reperdre le contexte. */}
          <button type="button" onClick={load} className="btn-secondary px-2.5 py-1 text-xs">
            Réessayer
          </button>
        </div>
      )}

      <DataTable
        columns={displayColumns}
        rows={rows}
        idKey={idKey}
        loading={loading}
        renderActions={(row) => (
          <span className="inline-flex items-center gap-1">
            {extraRowActions?.(row)}
            {/*
              « Éditer » / « Suppr. » étaient deux libellés texte collés l'un à
              l'autre : cible tactile étroite et risque de cliquer la
              suppression en visant l'édition. Boutons icônes espacés, avec
              libellé accessible et couleur distincte pour l'action destructive.
            */}
            <button
              onClick={() => openEdit(row)}
              aria-label="Éditer"
              title="Éditer"
              className="rounded-lg p-1.5 text-ink-400 transition hover:bg-brand-50 hover:text-brand-700"
            >
              <Icone nom="edit" />
            </button>
            <button
              onClick={() => handleDelete(row)}
              aria-label="Supprimer"
              title="Supprimer"
              className="rounded-lg p-1.5 text-ink-400 transition hover:bg-red-50 hover:text-red-600"
            >
              <Icone nom="delete" />
            </button>
          </span>
        )}
      />

      {editing && (
        <Modal
          title={editing[idKey] ? 'Modifier' : 'Créer'}
          onClose={() => setEditing(null)}
        >
          <form onSubmit={handleSubmit}>
            {fields.map((f) =>
              f.type === 'checkbox' ? (
                <label
                  key={f.key}
                  className="mb-3 flex cursor-pointer items-center gap-2 text-sm"
                >
                  <input
                    type="checkbox"
                    checked={!!formValues[f.key]}
                    onChange={(e) => setFormValues((v) => ({ ...v, [f.key]: e.target.checked }))}
                    className="h-4 w-4 rounded border-brand-200 text-brand-700 accent-brand-700"
                  />
                  <span className="font-medium text-ink-700">{f.label}</span>
                </label>
              ) : (
                <label key={f.key} className="mb-3 block text-sm">
                  <span className="field-label">
                    {f.label}
                    {f.required && (
                      <span aria-hidden="true" className="ml-0.5 text-gold-600">
                        *
                      </span>
                    )}
                  </span>
                  {f.type === 'select' ? (
                    (() => {
                      const rawOptions = f.options ?? optionsByField[f.key] ?? []
                      const selectOptions = rawOptions.map((opt) => ({
                        value: opt.id ?? opt.value,
                        label: opt.label ?? (f.optionLabel ? opt[f.optionLabel] : opt.nom ?? opt.label),
                      }))
                      const current = formValues[f.key] ?? ''
                      const selected = selectOptions.find((o) => String(o.value) === String(current)) ?? null

                      return (
                        <Select
                          inputId={`field-${f.key}`}
                          styles={SELECT_STYLES}
                          isClearable={!f.required}
                          placeholder="Rechercher…"
                          noOptionsMessage={() => 'Aucun résultat'}
                          options={selectOptions}
                          value={selected}
                          onChange={(opt) => setFormValues((v) => ({ ...v, [f.key]: opt?.value ?? '' }))}
                        />
                      )
                    })()
                  ) : f.type === 'password' ? (
                    <PasswordInput
                      required={f.required}
                      placeholder={f.placeholder}
                      aria-invalid={formErrors[f.key] ? true : undefined}
                      value={formValues[f.key] ?? ''}
                      onChange={(e) => setFormValues((v) => ({ ...v, [f.key]: e.target.value }))}
                      className="field w-full"
                    />
                  ) : (
                    <input
                      type={f.type ?? 'text'}
                      required={f.required}
                      placeholder={f.placeholder}
                      aria-invalid={formErrors[f.key] ? true : undefined}
                      value={formValues[f.key] ?? ''}
                      onChange={(e) => setFormValues((v) => ({ ...v, [f.key]: e.target.value }))}
                      className={`field ${formErrors[f.key] ? 'border-red-300' : ''}`}
                    />
                  )}
                  {formErrors[f.key] && (
                    <span className="mt-1 flex items-center gap-1 text-xs font-medium text-red-600">
                      <Icone nom="error" className="text-[14px]" />
                      {formErrors[f.key][0]}
                    </span>
                  )}
                </label>
              ),
            )}

            <div className="mt-5 flex gap-2 border-t border-ink-100 pt-4">
              <button
                type="button"
                onClick={() => setEditing(null)}
                className="btn-secondary flex-1"
              >
                Annuler
              </button>
              {/*
                Le bouton n'avait aucun état d'envoi : sur une connexion lente
                rien ne bougeait après le clic, et un second clic créait un
                doublon.
              */}
              <button type="submit" disabled={enregistrement} className="btn-primary flex-1">
                {enregistrement && (
                  <span
                    aria-hidden="true"
                    className="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"
                  />
                )}
                {enregistrement ? 'Enregistrement…' : 'Enregistrer'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  )
}
