import { useEffect, useState } from 'react'
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
    <div className="overflow-x-auto rounded-lg border border-ink-100 bg-white">
      <table className="min-w-full divide-y divide-ink-100 text-sm">
        <thead className="bg-ink-50">
          <tr>
            <th className="px-4 py-2 text-left font-medium text-ink-500">Enseignant</th>
            <th className="px-4 py-2 text-left font-medium text-ink-500">UUID</th>
            <th className="px-4 py-2 text-left font-medium text-ink-500">Type</th>
            <th className="px-4 py-2 text-left font-medium text-ink-500">Activé le</th>
            <th className="px-4 py-2 text-left font-medium text-ink-500">Statut</th>
            <th className="px-4 py-2" />
          </tr>
        </thead>
        <tbody className="divide-y divide-ink-100">
          {loading && (
            <tr>
              <td colSpan={6} className="px-4 py-6 text-center text-ink-300">Chargement…</td>
            </tr>
          )}
          {!loading && devices.length === 0 && (
            <tr>
              <td colSpan={6} className="px-4 py-6 text-center text-ink-300">Aucun device.</td>
            </tr>
          )}
          {devices.map((d) => (
            <tr key={d.id} className="hover:bg-ink-50">
              <td className="px-4 py-2">{d.teacher?.nom ?? '—'}</td>
              <td className="px-4 py-2 font-mono text-xs">{d.device_uuid}</td>
              <td className="px-4 py-2">{d.device_type}</td>
              <td className="px-4 py-2">{d.activated_at ? new Date(d.activated_at).toLocaleString('fr-FR') : '—'}</td>
              <td className="px-4 py-2">
                {d.revoked_at ? (
                  <span className="text-red-600">Révoqué</span>
                ) : (
                  <span className="text-green-600">Actif</span>
                )}
              </td>
              <td className="px-4 py-2 text-right">
                {!d.revoked_at && (
                  <button onClick={() => revoke(d)} className="text-red-500 hover:text-red-700">
                    Révoquer
                  </button>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
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
      <div className="overflow-x-auto rounded-lg border border-ink-100 bg-white">
        <table className="min-w-full divide-y divide-ink-100 text-sm">
          <thead className="bg-ink-50">
            <tr>
              <th className="px-4 py-2 text-left font-medium text-ink-500">Enseignant</th>
              <th className="px-4 py-2 text-left font-medium text-ink-500">Téléphone</th>
              <th className="px-4 py-2 text-left font-medium text-ink-500">Demandée le</th>
              <th className="px-4 py-2" />
            </tr>
          </thead>
          <tbody className="divide-y divide-ink-100">
            {loading && (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-center text-ink-300">Chargement…</td>
              </tr>
            )}
            {!loading && requests.length === 0 && (
              <tr>
                <td colSpan={4} className="px-4 py-6 text-center text-ink-300">Aucune demande en attente.</td>
              </tr>
            )}
            {requests.map((r) => (
              <tr key={r.id} className="hover:bg-ink-50">
                <td className="px-4 py-2">{r.enseignant?.nom}</td>
                <td className="px-4 py-2">{r.enseignant?.tel}</td>
                <td className="px-4 py-2">{new Date(r.requested_at).toLocaleString('fr-FR')}</td>
                <td className="px-4 py-2 text-right">
                  <button
                    onClick={() => generateOtp(r)}
                    className="rounded-md bg-brand-700 px-3 py-1 text-white hover:bg-brand-800"
                  >
                    Générer le code
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

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
