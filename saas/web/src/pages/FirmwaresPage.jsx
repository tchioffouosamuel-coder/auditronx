import { useEffect, useState } from "react";
import DataTable from "../components/DataTable";
import api from "../lib/api";
import { formatDateTime } from "../lib/datetime";
import swal, { confirmAction } from "../lib/swal";

const EMPTY_FORM = { device_id: "", version: "", release_notes: "" };

function errorMessage(err, fallback) {
  const errors = err.response?.data?.errors;
  if (errors) return Object.values(errors).flat().join("\n");
  return err.response?.data?.message ?? fallback;
}

/**
 * Mises à jour OTA des bornes relais : upload d'un firmware.bin par borne,
 * puis activation. La borne vérifie son manifest toutes les 10 min
 * (OTA_CHECK_INTERVAL_MS côté firmware) et se met à jour d'elle-même.
 */
export default function FirmwaresPage() {
  const [devices, setDevices] = useState([]);
  const [firmwares, setFirmwares] = useState([]);
  const [loading, setLoading] = useState(true);
  const [form, setForm] = useState(EMPTY_FORM);
  const [file, setFile] = useState(null);
  const [fileKey, setFileKey] = useState(0);
  const [uploading, setUploading] = useState(false);

  function load() {
    setLoading(true);
    Promise.all([
      api.get("/devices", { params: { device_type: "relay_gateway", revoked: 0 } }),
      api.get("/firmwares"),
    ])
      .then(([d, f]) => {
        setDevices(d.data.data ?? []);
        setFirmwares(f.data ?? []);
      })
      .finally(() => setLoading(false));
  }

  useEffect(load, []);

  async function upload(e) {
    e.preventDefault();
    setUploading(true);
    try {
      const data = new FormData();
      Object.entries(form).forEach(([key, value]) => data.append(key, value));
      data.append("firmware", file);
      await api.post("/firmwares", data, { headers: { "Content-Type": "multipart/form-data" } });
      setForm(EMPTY_FORM);
      setFile(null);
      setFileKey((k) => k + 1);
      swal.fire({ icon: "success", title: "Firmware envoyé", text: "Activez-le pour déployer la mise à jour sur la borne." });
      load();
    } catch (err) {
      swal.fire({ icon: "error", title: "Échec de l'envoi", text: errorMessage(err, "Erreur inconnue.") });
    } finally {
      setUploading(false);
    }
  }

  async function activate(fw) {
    if (
      !(await confirmAction(
        `Déployer la v${fw.version} sur ${fw.device?.device_uuid} ? La borne l'installera à sa prochaine vérification (10 min max) puis redémarrera.`,
        { title: "Activer ce firmware", confirmText: "Activer" },
      ))
    )
      return;
    await api.post(`/firmwares/${fw.id}/activate`);
    load();
  }

  async function deactivate(fw) {
    await api.post(`/firmwares/${fw.id}/deactivate`);
    load();
  }

  async function remove(fw) {
    if (!(await confirmAction(`Supprimer la v${fw.version} ?`, { confirmText: "Supprimer" }))) return;
    try {
      await api.delete(`/firmwares/${fw.id}`);
      load();
    } catch (err) {
      swal.fire({ icon: "error", title: "Suppression impossible", text: errorMessage(err, "Erreur inconnue.") });
    }
  }

  const activeByDevice = Object.fromEntries(firmwares.filter((f) => f.is_active).map((f) => [f.device_id, f.version]));

  return (
    <div>
      <h1 className="page-title mb-4">Mises à jour firmware (OTA)</h1>

      <h2 className="mb-2 text-sm font-semibold text-ink-700">Bornes</h2>
      <div className="mb-6">
        <DataTable
          loading={loading}
          rows={devices}
          emptyMessage="Aucune borne active."
          pageSize={5}
          columns={[
            { key: "device_uuid", label: "Borne", render: (d) => <span className="font-mono text-xs">{d.device_uuid}</span> },
            { key: "firmware_version", label: "Version installée", render: (d) => d.firmware_version ?? "—" },
            { key: "active", label: "Version déployée", render: (d) => activeByDevice[d.id] ?? "—" },
            {
              key: "sync",
              label: "État",
              render: (d) => {
                const target = activeByDevice[d.id];
                if (!target) return <span className="text-ink-500">Aucun déploiement</span>;
                if (target === d.firmware_version) return <span className="text-green-600">À jour</span>;
                return <span className="text-gold-700">Mise à jour en attente</span>;
              },
            },
            {
              key: "firmware_checked_at",
              label: "Dernière vérification",
              render: (d) => (d.firmware_checked_at ? formatDateTime(d.firmware_checked_at) : "Jamais"),
              sortValue: (d) => d.firmware_checked_at ?? "",
            },
          ]}
        />
      </div>

      <h2 className="mb-2 text-sm font-semibold text-ink-700">Envoyer un firmware</h2>
      <form onSubmit={upload} className="card mb-6 flex flex-wrap items-end gap-4 p-4">
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Borne</span>
          <select
            required
            value={form.device_id}
            onChange={(e) => setForm({ ...form, device_id: e.target.value })}
            className="field py-2"
          >
            <option value="">Choisir…</option>
            {devices.map((d) => (
              <option key={d.id} value={d.id}>
                {d.device_uuid}
              </option>
            ))}
          </select>
        </label>
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Version (= FIRMWARE_VERSION)</span>
          <input
            required
            pattern="\d+\.\d+\.\d+"
            placeholder="ex. 1.1.0"
            value={form.version}
            onChange={(e) => setForm({ ...form, version: e.target.value })}
            className="field w-32 py-2"
          />
        </label>
        <label className="min-w-64 flex-1 text-sm">
          <span className="mb-1 block text-ink-700">Notes de version</span>
          <input
            value={form.release_notes}
            onChange={(e) => setForm({ ...form, release_notes: e.target.value })}
            className="field py-2"
          />
        </label>
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">firmware.bin</span>
          <input
            key={fileKey}
            required
            type="file"
            accept=".bin"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            className="text-sm file:mr-3 file:rounded-md file:border file:border-ink-100 file:bg-white file:px-3 file:py-1.5 file:text-sm file:text-ink-700 hover:file:bg-ink-50"
          />
        </label>
        <button
          type="submit"
          disabled={uploading}
          className="btn-primary"
        >
          {uploading ? "Envoi…" : "Envoyer"}
        </button>
      </form>

      <h2 className="mb-2 text-sm font-semibold text-ink-700">Firmwares</h2>
      <DataTable
        loading={loading}
        rows={firmwares}
        emptyMessage="Aucun firmware envoyé."
        columns={[
          { key: "device", label: "Borne", render: (f) => <span className="font-mono text-xs">{f.device?.device_uuid ?? "—"}</span> },
          { key: "version", label: "Version" },
          {
            key: "size_bytes",
            label: "Taille",
            render: (f) => `${(f.size_bytes / 1048576).toFixed(2)} Mo`,
            sortValue: (f) => f.size_bytes,
          },
          {
            key: "sha256",
            label: "SHA256",
            render: (f) => (
              <span className="font-mono text-xs" title={f.sha256}>
                {f.sha256.slice(0, 12)}…
              </span>
            ),
          },
          { key: "release_notes", label: "Notes", render: (f) => f.release_notes ?? "" },
          {
            key: "is_active",
            label: "Statut",
            render: (f) => (f.is_active ? <span className="text-green-600">Déployé</span> : <span className="text-ink-500">Inactif</span>),
            sortValue: (f) => (f.is_active ? 0 : 1),
          },
          {
            key: "created_at",
            label: "Envoyé le",
            render: (f) => `${formatDateTime(f.created_at)}${f.uploader ? ` · ${f.uploader.name}` : ""}`,
            sortValue: (f) => f.created_at,
          },
        ]}
        renderActions={(f) => (
          <div className="flex gap-3">
            {f.is_active ? (
              <button onClick={() => deactivate(f)} className="text-ink-500 hover:text-ink-900">
                Désactiver
              </button>
            ) : (
              <>
                <button onClick={() => activate(f)} className="text-brand-700 hover:text-brand-900">
                  Activer
                </button>
                <button onClick={() => remove(f)} className="text-red-500 hover:text-red-700">
                  Supprimer
                </button>
              </>
            )}
          </div>
        )}
      />
    </div>
  );
}
