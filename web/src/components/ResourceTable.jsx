import { useEffect, useState } from 'react'
import api from '../lib/api'
import fetchAllPages from '../lib/fetchAllPages'
import { confirmAction } from '../lib/swal'
import DataTable from './DataTable'
import Modal from './Modal'
import PasswordInput from './PasswordInput'
import SearchSelect from './SearchSelect'

/**
 * Table CRUD générique pilotée par un schéma de champs — évite de réécrire le
 * même boilerplate (liste, création, édition, suppression) pour chaque module
 * de gestion simple (classes, disciplines, féries, accréditations, ...).
 *
 * `fields`: [{ key, label, type: 'text'|'number'|'select', options?, optionsUrl?,
 *              optionLabel?, required?, toForm?(value) }]
 * `columns`: [{ key, label, render?(row) }] — par défaut dérivées de `fields`.
 *
 * `toForm` normalise la valeur lue sur la ligne avant de pré-remplir le
 * formulaire d'édition : une colonne SQL `time` revient en « 08:10:00 » alors
 * que l'API n'accepte que « 08:10 », et une liste déroulante ne retrouvait
 * donc pas son option.
 */
export default function ResourceTable({ title, resource, fields, columns, idKey = 'id', extraRowActions }) {
  const [rows, setRows] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [editing, setEditing] = useState(null) // null = fermé, {} = création, {...} = édition
  const [formValues, setFormValues] = useState({})
  const [formErrors, setFormErrors] = useState({})
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
    setFormValues(
      fields.reduce(
        (acc, f) => (f.toForm ? { ...acc, [f.key]: f.toForm(row[f.key]) } : acc),
        { ...row },
      ),
    )
    setFormErrors({})
    setEditing(row)
  }

  async function handleDelete(row) {
    if (!(await confirmAction('Confirmer la suppression ?', { confirmText: 'Supprimer' }))) return
    await api.delete(`${resource}/${row[idKey]}`)
    load()
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setFormErrors({})
    try {
      if (editing[idKey]) {
        await api.put(`${resource}/${editing[idKey]}`, formValues)
      } else {
        await api.post(resource, formValues)
      }
      setEditing(null)
      load()
    } catch (err) {
      setFormErrors(err.response?.data?.errors ?? {})
    }
  }

  return (
    <div>
      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        {title && <h1 className="text-lg font-semibold text-ink-900">{title}</h1>}
        <button
          onClick={openCreate}
          className="rounded-md bg-brand-700 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-brand-800 sm:ml-auto"
        >
          + Nouveau
        </button>
      </div>

      {error && <div className="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

      <DataTable
        columns={displayColumns}
        rows={rows}
        idKey={idKey}
        loading={loading}
        renderActions={(row) => (
          <>
            {extraRowActions?.(row)}
            <button onClick={() => openEdit(row)} className="mr-3 text-ink-500 hover:text-ink-900">
              Éditer
            </button>
            <button onClick={() => handleDelete(row)} className="text-red-500 hover:text-red-700">
              Suppr.
            </button>
          </>
        )}
      />

      {editing && (
        <Modal title={editing[idKey] ? 'Modifier' : 'Créer'} onClose={() => setEditing(null)}>
          <form onSubmit={handleSubmit}>
            {fields.map((f) =>
              f.type === 'checkbox' ? (
                <label key={f.key} className="mb-3 flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={!!formValues[f.key]}
                    onChange={(e) => setFormValues((v) => ({ ...v, [f.key]: e.target.checked }))}
                    className="h-4 w-4 rounded border-ink-100 text-brand-700 focus:ring-brand-500"
                  />
                  <span className="text-ink-700">{f.label}</span>
                </label>
              ) : (
                <label key={f.key} className="mb-3 block text-sm">
                  <span className="mb-1 block text-ink-700">{f.label}</span>
                  {f.type === 'select' ? (
                    <SearchSelect
                      id={`field-${f.key}`}
                      required={f.required}
                      options={(f.options ?? optionsByField[f.key] ?? []).map((opt) => ({
                        value: opt.id ?? opt.value,
                        label: opt.label ?? (f.optionLabel ? opt[f.optionLabel] : opt.nom ?? opt.label),
                      }))}
                      value={formValues[f.key] ?? ''}
                      onChange={(value) => setFormValues((v) => ({ ...v, [f.key]: value }))}
                    />
                  ) : f.type === 'password' ? (
                    <PasswordInput
                      required={f.required}
                      placeholder={f.placeholder}
                      value={formValues[f.key] ?? ''}
                      onChange={(e) => setFormValues((v) => ({ ...v, [f.key]: e.target.value }))}
                      className="w-full rounded-md border border-ink-100 px-3 py-2 focus:border-brand-500 focus:outline-none"
                    />
                  ) : (
                    <input
                      type={f.type ?? 'text'}
                      required={f.required}
                      placeholder={f.placeholder}
                      value={formValues[f.key] ?? ''}
                      onChange={(e) => setFormValues((v) => ({ ...v, [f.key]: e.target.value }))}
                      className="w-full rounded-md border border-ink-100 px-3 py-2 focus:border-brand-500 focus:outline-none"
                    />
                  )}
                  {formErrors[f.key] && (
                    <span className="mt-1 block text-xs text-red-600">{formErrors[f.key][0]}</span>
                  )}
                </label>
              ),
            )}
            <button
              type="submit"
              className="mt-2 w-full rounded-md bg-brand-700 py-2 text-sm font-medium text-white hover:bg-brand-800"
            >
              Enregistrer
            </button>
          </form>
        </Modal>
      )}
    </div>
  )
}
