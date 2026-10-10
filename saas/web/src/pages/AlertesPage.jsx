import { useEffect, useState } from 'react'
import DataTable from '../components/DataTable'
import api from '../lib/api'
import { formatDateTime } from '../lib/datetime'

export default function AlertesPage() {
  const [alertes, setAlertes] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    api.get('/absences/alertes').then(({ data }) => {
      setAlertes(data.data ?? [])
      setLoading(false)
    })
  }, [])

  return (
    <div>
      <h1 className="page-title mb-4">Alertes d’absences répétées</h1>
      <DataTable
        loading={loading}
        emptyMessage="Aucune alerte."
        rows={alertes}
        columns={[
          { key: 'enseignant', label: 'Enseignant', render: (a) => a.enseignant?.nom, sortValue: (a) => a.enseignant?.nom },
          {
            key: 'sent_at',
            label: 'Envoyée le',
            render: (a) => formatDateTime(a.sent_at, {}),
            sortValue: (a) => a.sent_at,
          },
          { key: 'canal', label: 'Canal' },
        ]}
      />
    </div>
  )
}
