import { useEffect, useState } from 'react'
import DataTable from '../components/DataTable'
import ResourceTable from '../components/ResourceTable'
import Modal from '../components/Modal'
import api from '../lib/api'

function DevicesTable() {
  const [devices, setDevices] = useState([])
  const [loading, setLoading] = useState(true)

  function load() {
    setLoading(true)
    api
      .get('/devices')
      .then(({ data }) => setDevices(data.data ?? []))
      .finally(() => setLoading(false))
  }

  useEffect(load, [])

  async function revoke(device) {
    if (!window.confirm(`Révoquer le device ${device.device_uuid} ?`)) return
    await api.post(`/devices/${device.id}/revoke`)
    load()
  }

  return (
    <DataTable
      loading={loading}
      emptyMessage="Aucun device."
      rows={devices}
      columns={[
        { key: 'teacher', label: 'Enseignant', render: (d) => d.teacher?.nom ?? '—', sortValue: (d) => d.teacher?.nom ?? '' },
        { key: 'device_uuid', label: 'UUID', render: (d) => <span className="font-mono text-xs">{d.device_uuid}</span> },
        { key: 'device_type', label: 'Type' },
        {
          key: 'activated_at',
          label: 'Activé le',
          render: (d) => (d.activated_at ? new Date(d.activated_at).toLocaleString('fr-FR') : '—'),
          sortValue: (d) => d.activated_at ?? '',
        },
        {
          key: 'statut',
          label: 'Statut',
          render: (d) => (d.revoked_at ? <span className="text-red-600">Révoqué</span> : <span className="text-green-600">Actif</span>),
          searchValue: (d) => (d.revoked_at ? 'Révoqué' : 'Actif'),
          sortValue: (d) => (d.revoked_at ? 1 : 0),
        },
      ]}
      renderActions={(d) =>
        !d.revoked_at && (
          <button onClick={() => revoke(d)} className="text-red-500 hover:text-red-700">
            Révoquer
          </button>
        )
      }
    />
  )
}

/**
 * Demandes d'activation (§4.1 revu) : un enseignant non-admin s'est identifié
 * (tel + mot de passe) mais attend qu'un administrateur génère l'OTP à lui
 * remettre en personne.
 */
function ActivationRequestsTable() {
  const [requests, setRequests] = useState([])
  const [loading, setLoading] = useState(true)
  const [otpResult, setOtpResult] = useState(null)

  function load() {
    setLoading(true)
    api
      .get('/devices/activation-requests', { params: { statut: 'en_attente' } })
      .then(({ data }) => setRequests(data.data ?? []))
      .finally(() => setLoading(false))
  }

  useEffect(load, [])

  async function generateOtp(request) {
    const { data } = await api.post(`/devices/activation-requests/${request.id}/generate-otp`)
    setOtpResult({ enseignant: request.enseignant?.nom, ...data })
    load()
  }

  return (
    <>
      <DataTable
        loading={loading}
        emptyMessage="Aucune demande en attente."
        rows={requests}
        columns={[
          { key: 'enseignant', label: 'Enseignant', render: (r) => r.enseignant?.nom, sortValue: (r) => r.enseignant?.nom },
          { key: 'tel', label: 'Téléphone', render: (r) => r.enseignant?.tel, sortValue: (r) => r.enseignant?.tel },
          {
            key: 'requested_at',
            label: 'Demandée le',
            render: (r) => new Date(r.requested_at).toLocaleString('fr-FR'),
            sortValue: (r) => r.requested_at,
          },
        ]}
        renderActions={(r) => (
          <button onClick={() => generateOtp(r)} className="rounded-md bg-brand-700 px-3 py-1 text-white hover:bg-brand-800">
            Générer le code
          </button>
        )}
      />

      {otpResult && (
        <Modal title={`Code d'activation — ${otpResult.enseignant}`} onClose={() => setOtpResult(null)}>
          <p className="mb-3 text-sm text-ink-700">
            À remettre en personne à l'enseignant. Ce code n'est affiché qu'une seule fois et expire le{' '}
            {new Date(otpResult.expires_at).toLocaleString('fr-FR')}.
          </p>
          <div className="rounded-lg bg-gold-100 px-4 py-3 text-center text-3xl font-bold tracking-[0.3em] text-gold-700">
            {otpResult.code}
          </div>
        </Modal>
      )}
    </>
  )
}

export default function AppareilsPage() {
  const [tab, setTab] = useState('activation-requests')

  return (
    <div>
      <h1 className="mb-4 text-lg font-semibold text-ink-900">Appareils & points d’accès</h1>

      <div className="mb-4 flex gap-2">
        {[
          ['activation-requests', 'Demandes d’activation'],
          ['devices', 'Devices'],
          ['access-points', 'Bornes WiFi'],
          ['qr-points', 'Points QR'],
        ].map(([key, label]) => (
          <button
            key={key}
            onClick={() => setTab(key)}
            className={`rounded-md px-3 py-1.5 text-sm ${
              tab === key ? 'bg-brand-700 text-white' : 'bg-white text-ink-700 border border-ink-100'
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      {tab === 'activation-requests' && <ActivationRequestsTable />}
      {tab === 'devices' && <DevicesTable />}
      {tab === 'access-points' && (
        <ResourceTable
          title=""
          resource="/access-points"
          fields={[
            { key: 'bssid', label: 'BSSID', required: true },
            { key: 'ssid', label: 'SSID' },
            { key: 'password', label: 'Mot de passe WiFi', type: 'password' },
            { key: 'label', label: 'Libellé' },
          ]}
          columns={[
            { key: 'bssid', label: 'BSSID' },
            { key: 'ssid', label: 'SSID' },
            { key: 'label', label: 'Libellé' },
          ]}
        />
      )}
      {tab === 'qr-points' && (
        <ResourceTable
          title=""
          resource="/qr-points"
          fields={[{ key: 'label', label: 'Libellé' }]}
          columns={[
            { key: 'code', label: 'Code' },
            { key: 'label', label: 'Libellé' },
          ]}
        />
      )}
    </div>
  )
}
