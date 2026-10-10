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
        {
          key: 'exclut_administration',
          label: 'Exclure le personnel administratif (surveillance générale)',
          type: 'checkbox',
        },
      ]}
      columns={[
        { key: 'label', label: 'Libellé' },
        { key: 'groupe', label: 'Groupe' },
        { key: 'niveau', label: 'Niveau' },
        {
          key: 'exclut_administration',
          label: 'Administration',
          render: (a) => (a.exclut_administration ? 'Exclue' : 'Visible'),
          sortValue: (a) => (a.exclut_administration ? 1 : 0),
        },
      ]}
    />
  )
}
