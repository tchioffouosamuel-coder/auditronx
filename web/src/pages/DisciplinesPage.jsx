import ResourceTable from '../components/ResourceTable'

export default function DisciplinesPage() {
  return (
    <ResourceTable
      title="Disciplines"
      resource="/disciplines"
      fields={[
        { key: 'nom', label: 'Nom', required: true },
        { key: 'code', label: 'Code', required: true },
        { key: 'coefficient', label: 'Coefficient', type: 'number' },
        { key: 'departement', label: 'Département' },
      ]}
    />
  )
}
