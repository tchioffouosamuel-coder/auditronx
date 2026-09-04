import ResourceTable from '../components/ResourceTable'

export default function SignalementsPage() {
  return (
    <ResourceTable
      title="Signalements & justificatifs"
      resource="/signalements"
      columns={[
        { key: 'enseignant', label: 'Enseignant', render: (r) => r.enseignant?.nom },
        { key: 'date', label: 'Date' },
        { key: 'motif', label: 'Motif' },
        { key: 'duree_jours', label: 'Durée (j)' },
      ]}
      fields={[
        { key: 'enseignant_id', label: 'Enseignant', type: 'select', optionsUrl: '/personnel?per_page=500', required: true },
        { key: 'date', label: 'Date', type: 'date', required: true },
        { key: 'motif', label: 'Motif', required: true },
        { key: 'duree_jours', label: 'Durée (jours)', type: 'number' },
      ]}
    />
  )
}
