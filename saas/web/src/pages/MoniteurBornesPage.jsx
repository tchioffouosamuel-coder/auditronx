import { useEffect, useMemo, useRef, useState } from "react";
import api from "../lib/api";
import { formatDateTime, TIME_ZONE } from "../lib/datetime";
import { confirmAction } from "../lib/swal";

// La borne pousse ses lignes toutes les ~5 s (LOG_FLUSH_INTERVAL_MS côté
// firmware) : un polling à 3 s suffit pour un rendu quasi temps réel.
const POLL_INTERVAL_MS = 3000;
// Au-delà, les plus anciennes lignes sortent de la console (mémoire navigateur).
const MAX_LINES = 3000;
// Sans nouvelle ligne depuis ce délai, la borne est considérée hors ligne.
const ONLINE_THRESHOLD_MS = 30000;

const timeFormat = new Intl.DateTimeFormat("fr-FR", {
  timeZone: TIME_ZONE,
  hour: "2-digit",
  minute: "2-digit",
  second: "2-digit",
});

function lineColor(message) {
  if (/échec|erreur|error|saturée|illisible|refusé|panic|brownout/i.test(message)) return "text-red-400";
  if (message.startsWith("[boot]")) return "text-gold-300";
  if (message.startsWith("[sync]")) return "text-sky-300";
  if (message.startsWith("[ble]")) return "text-violet-300";
  if (message.startsWith("[wifi]") || message.startsWith("[time]")) return "text-brand-300";
  return "text-ink-100";
}

export default function MoniteurBornesPage() {
  const [devices, setDevices] = useState([]);
  const [deviceId, setDeviceId] = useState("");
  const [lines, setLines] = useState([]);
  const [lastSeenAt, setLastSeenAt] = useState(null);
  const [paused, setPaused] = useState(false);
  const [autoScroll, setAutoScroll] = useState(true);
  const [filter, setFilter] = useState("");
  const [error, setError] = useState(null);
  const [now, setNow] = useState(() => Date.now());

  const lastIdRef = useRef(null);
  const consoleRef = useRef(null);

  useEffect(() => {
    api
      .get("/devices", { params: { device_type: "relay_gateway", revoked: 0 } })
      .then(({ data }) => {
        const relays = data.data ?? [];
        setDevices(relays);
        if (relays.length > 0) setDeviceId(String(relays[0].id));
      });
  }, []);

  // Changement de borne : console vidée, rechargement complet.
  function selectDevice(id) {
    setLines([]);
    setLastSeenAt(null);
    setError(null);
    lastIdRef.current = null;
    setDeviceId(id);
  }

  useEffect(() => {
    if (!deviceId || paused) return;

    let cancelled = false;
    let timer = null;

    async function poll() {
      try {
        const params = lastIdRef.current === null ? {} : { after_id: lastIdRef.current };
        const { data } = await api.get(`/devices/${deviceId}/logs`, { params });
        if (cancelled) return;
        const incoming = data.data ?? [];
        if (incoming.length > 0) {
          lastIdRef.current = incoming[incoming.length - 1].id;
          setLines((prev) => prev.concat(incoming).slice(-MAX_LINES));
        } else if (lastIdRef.current === null) {
          lastIdRef.current = 0;
        }
        setLastSeenAt(data.last_seen_at);
        setError(null);
      } catch {
        if (!cancelled) setError("Impossible de récupérer les logs, nouvelle tentative…");
      }
      if (!cancelled) timer = setTimeout(poll, POLL_INTERVAL_MS);
    }

    poll();
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [deviceId, paused]);

  // Rafraîchit l'indicateur en ligne/hors ligne même sans nouvelle ligne.
  useEffect(() => {
    const id = setInterval(() => setNow(Date.now()), 5000);
    return () => clearInterval(id);
  }, []);

  const visibleLines = useMemo(() => {
    const needle = filter.trim().toLowerCase();
    return needle ? lines.filter((l) => l.message.toLowerCase().includes(needle)) : lines;
  }, [lines, filter]);

  useEffect(() => {
    if (autoScroll && consoleRef.current) {
      consoleRef.current.scrollTop = consoleRef.current.scrollHeight;
    }
  }, [visibleLines, autoScroll]);

  async function clearLogs() {
    if (
      !(await confirmAction("Effacer tous les logs enregistrés pour cette borne ?", {
        confirmText: "Effacer",
      }))
    )
      return;
    await api.delete(`/devices/${deviceId}/logs`);
    setLines([]);
    lastIdRef.current = 0;
  }

  const online = lastSeenAt && now - new Date(lastSeenAt).getTime() < ONLINE_THRESHOLD_MS;

  return (
    <div>
      <h1 className="page-title mb-4">Moniteur série des bornes</h1>

      <div className="mb-4 flex flex-wrap items-end gap-4 card p-4">
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Borne</span>
          <select
            value={deviceId}
            onChange={(e) => selectDevice(e.target.value)}
            className="field py-2"
          >
            {devices.length === 0 && <option value="">Aucune borne active</option>}
            {devices.map((d) => (
              <option key={d.id} value={d.id}>
                {d.device_uuid}
              </option>
            ))}
          </select>
        </label>
        <label className="text-sm">
          <span className="mb-1 block text-ink-700">Filtrer</span>
          <input
            type="search"
            value={filter}
            onChange={(e) => setFilter(e.target.value)}
            placeholder="ex. [sync], échec…"
            className="field py-2"
          />
        </label>
        <label className="flex items-center gap-2 pb-2 text-sm text-ink-700">
          <input type="checkbox" checked={autoScroll} onChange={(e) => setAutoScroll(e.target.checked)} />
          Défilement auto
        </label>

        <div className="ml-auto flex items-center gap-3">
          {deviceId && (
            <span className="flex items-center gap-1.5 text-sm text-ink-700">
              <span className={`h-2.5 w-2.5 rounded-full ${online ? "bg-green-500" : "bg-ink-300"}`} />
              {online
                ? "En ligne"
                : lastSeenAt
                  ? `Dernier envoi : ${formatDateTime(lastSeenAt)}`
                  : "Aucun log reçu"}
            </span>
          )}
          <button
            onClick={() => setPaused((p) => !p)}
            disabled={!deviceId}
            className="btn-secondary"
          >
            {paused ? "Reprendre" : "Pause"}
          </button>
          <button
            onClick={clearLogs}
            disabled={!deviceId}
            className="rounded-md border border-red-200 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 disabled:opacity-50"
          >
            Effacer
          </button>
        </div>
      </div>

      {error && <div className="mb-2 text-sm text-red-600">{error}</div>}

      <div
        ref={consoleRef}
        className="h-[65vh] overflow-y-auto rounded-lg bg-ink-900 p-3 font-mono text-xs leading-relaxed"
      >
        {visibleLines.length === 0 ? (
          <div className="text-ink-500">
            {deviceId ? "En attente de logs de la borne…" : "Sélectionnez une borne."}
          </div>
        ) : (
          visibleLines.map((l) => (
            <div key={l.id} className="whitespace-pre-wrap break-all">
              <span className="mr-2 text-ink-500">{timeFormat.format(new Date(l.logged_at))}</span>
              <span className={lineColor(l.message)}>{l.message}</span>
            </div>
          ))
        )}
      </div>
    </div>
  );
}
