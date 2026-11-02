import Select from 'react-select'

/** Styles react-select alignés sur l'utilitaire `.field` (cf. index.css). */
export const SELECT_STYLES = {
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

/**
 * Liste déroulante recherchable sur `options` = [{ value, label }], avec la
 * valeur comparée en chaîne : les identifiants reviennent de l'API en nombre
 * et du formulaire en texte, et une comparaison stricte ne retrouvait pas
 * l'option sélectionnée.
 */
export default function SearchSelect({ id, options, value, onChange, required, placeholder = 'Rechercher…' }) {
  const selected = options.find((o) => String(o.value) === String(value ?? '')) ?? null

  return (
    <Select
      inputId={id}
      styles={SELECT_STYLES}
      isClearable={!required}
      placeholder={placeholder}
      noOptionsMessage={() => 'Aucun résultat'}
      options={options}
      value={selected}
      onChange={(opt) => onChange(opt?.value ?? '')}
    />
  )
}
