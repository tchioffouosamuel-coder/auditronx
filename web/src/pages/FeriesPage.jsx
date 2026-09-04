import ResourceTable from '../components/ResourceTable'

export default function FeriesPage() {
  return (
    <ResourceTable
      title="Jours fériés"
      resource="/feries"
      fields={[
        { key: 'date', label: 'Date', type: 'date', required: true },
        { key: 'libelle', label: 'Libellé', required: true },
        { key: 'description', label: 'Description' },
      ]}
    />
  )
}
