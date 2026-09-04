import ResourceTable from '../components/ResourceTable'

export default function AccreditationsPage() {
  return (
    <ResourceTable
      title="Accréditations"
      resource="/accreditations"
      fields={[
        { key: 'label', label: 'Libellé', required: true },
        { key: 'groupe', label: "Groupe (section, ou '*' pour accès total)" },
        { key: 'niveau', label: 'Niveau (1-4)', type: 'number' },
      ]}
    />
  )
}
