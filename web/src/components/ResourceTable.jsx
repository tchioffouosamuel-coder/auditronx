import { useEffect, useState } from 'react'
import Select from 'react-select'
import api from '../lib/api'
import Modal from './Modal'

const SELECT_STYLES = {
  control: (base, state) => ({
    ...base,
    minHeight: '2.5rem',
    borderColor: state.isFocused ? 'var(--color-brand-500)' : 'var(--color-ink-100)',
    boxShadow: state.isFocused ? '0 0 0 2px var(--color-brand-100)' : 'none',
    '&:hover': { borderColor: 'var(--color-brand-500)' },
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
  menu: (base) => ({ ...base, zIndex: 50 }),
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
export default function ResourceTable({ title, resource, fields, columns, idKey = 'id' }) {
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
      const { data } = await api.get(resource)
      setRows(Array.isArray(data) ? data : data.data ?? [])
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
        api.get(f.optionsUrl).then(({ data }) => {
          const list = Array.isArray(data) ? data : data.data ?? []
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
    if (!window.confirm('Confirmer la suppression ?')) return
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

      <div className="overflow-x-auto rounded-lg border border-ink-100 bg-white shadow-sm">
        <table className="min-w-full divide-y divide-ink-100 text-sm">
          <thead className="bg-ink-50">
            <tr>
              {displayColumns.map((c) => (
                <th key={c.key} className="whitespace-nowrap px-4 py-2.5 text-left font-medium text-ink-500">
                  {c.label}
                </th>
              ))}
              <th className="px-4 py-2.5" />
            </tr>
          </thead>
          <tbody className="divide-y divide-ink-100">
            {loading && (
              <tr>
                <td colSpan={displayColumns.length + 1} className="px-4 py-8 text-center text-ink-300">
                  Chargement…
                </td>
              </tr>
            )}
            {!loading && rows.length === 0 && (
              <tr>
                <td colSpan={displayColumns.length + 1} className="px-4 py-8 text-center text-ink-300">
                  Aucune donnée.
                </td>
              </tr>
            )}
            {rows.map((row) => (
              <tr key={row[idKey]} className="hover:bg-ink-50">
                {displayColumns.map((c) => (
                  <td key={c.key} className="whitespace-nowrap px-4 py-2.5 text-ink-700">
                    {c.render ? c.render(row) : String(row[c.key] ?? '—')}
                  </td>
                ))}
                <td className="whitespace-nowrap px-4 py-2.5 text-right">
                  <button onClick={() => openEdit(row)} className="mr-3 text-ink-500 hover:text-ink-900">
                    Éditer
                  </button>
                  <button onClick={() => handleDelete(row)} className="text-red-500 hover:text-red-700">
                    Suppr.
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

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
