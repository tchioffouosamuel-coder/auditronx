import ResourceTable from '../components/ResourceTable'

export default function ClassesPage() {
  return (
    <ResourceTable
      title="Classes"
      resource="/classes"
      fields={[
        { key: 'nom', label: 'Nom', required: true },
        { key: 'code', label: 'Code', required: true },
        { key: 'niveau', label: 'Niveau' },
        { key: 'specialite', label: 'Spécialité' },
        { key: 'effectif', label: 'Effectif', type: 'number' },
      ]}
    />
  )
}
