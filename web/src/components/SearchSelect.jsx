import Select from 'react-select'

/** Habillage `react-select` aux couleurs de la marque (cf. index.css). */
export const SELECT_STYLES = {
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
