import { useEffect, useMemo, useState } from "react";
import { useAuth } from "../context/AuthContext";
import api from "../lib/api";

const TABS = [
  ["scan", "Scanner", "qr_code_scanner"],
  ["history", "Historique", "history"],
  ["courses", "Mes cours", "menu_book"],
  ["notifications", "Alertes", "notifications"],
];

const TITLES = {
  scan: "Auditron X",
  history: "Mon historique",
  courses: "Mes cours du jour",
  notifications: "Notifications",
};

const DAY_NAMES = {
  1: "Lundi",
  2: "Mardi",
  3: "Mercredi",
  4: "Jeudi",
  5: "Vendredi",
  6: "Samedi",
  7: "Dimanche",
};

function apiError(error) {
  return (
    error.response?.data?.message ?? error.message ?? "Une erreur est survenue."
  );
}

function todayIso() {
  return new Date().toISOString().slice(0, 10);
}

function formatDateTime(value) {
  if (!value) return "—";
  return new Date(value).toLocaleString("fr-FR", {
    dateStyle: "short",
    timeStyle: "short",
  });
}

function formatTime(value) {
  return value ?? "—";
}

function Icon({ name, className = "" }) {
  return (
    <span aria-hidden="true" className={`material-symbols-rounded ${className}`}>
      {name}
    </span>
  );
}

function IconButton({ label, icon, onClick }) {
  return (
    <button
      type="button"
      onClick={onClick}
      title={label}
      aria-label={label}
      className="grid h-11 w-11 place-items-center rounded-full text-white/90 transition hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-gold-500"
    >
      <Icon name={icon} />
    </button>
  );
}

function Frame({ title, leading, actions, bottomNav, children }) {
  return (
    <div className="min-h-screen bg-ink-50">
      <div className="mx-auto flex min-h-screen max-w-xl flex-col bg-ink-50 shadow-none sm:shadow-2xl sm:shadow-ink-900/10">
        <header className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-1 bg-brand-950 px-1 text-white">
          <div className="flex h-full w-12 items-center justify-center">
            {leading}
          </div>
          <h1 className="min-w-0 flex-1 truncate px-1 text-xl font-semibold">
            {title}
          </h1>
          <div className="flex items-center">{actions}</div>
        </header>
        <main className={`flex-1 ${bottomNav ? "pb-24" : ""}`}>{children}</main>
        {bottomNav}
      </div>
    </div>
  );
}

function BottomNav({ value, onChange }) {
  return (
    <nav
      className="fixed inset-x-0 bottom-0 z-30 mx-auto max-w-xl bg-brand-950 px-2 pb-[max(env(safe-area-inset-bottom),0.75rem)] pt-2 shadow-2xl shadow-ink-900/25"
      aria-label="Navigation enseignant"
    >
      <div className="grid grid-cols-4 gap-1">
        {TABS.map(([id, label, icon]) => {
          const selected = value === id;
          return (
            <button
              key={id}
              type="button"
              onClick={() => onChange(id)}
              className="flex min-h-16 flex-col items-center justify-center gap-1 rounded-2xl px-1 text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-gold-500"
            >
              <span
                className={`grid h-8 min-w-14 place-items-center rounded-2xl transition ${selected ? "bg-gold-500 text-brand-950" : "text-white/70"}`}
              >
                <Icon name={icon} className="text-[22px]" />
              </span>
              <span className={selected ? "text-gold-100" : "text-white/70"}>
                {label}
              </span>
            </button>
          );
        })}
      </div>
    </nav>
  );
}

function Card({ children, className = "" }) {
  return (
    <section
      className={`overflow-hidden rounded-2xl border border-ink-100 bg-white shadow-none ${className}`}
    >
      {children}
    </section>
  );
}

function ListTile({
  leading,
  title,
  subtitle,
  trailing,
  onClick,
  className = "",
}) {
  const Tag = onClick ? "button" : "div";
  return (
    <Tag
      type={onClick ? "button" : undefined}
      onClick={onClick}
      className={`flex w-full items-center gap-3 px-4 py-4 text-left ${onClick ? "transition hover:bg-ink-50" : ""} ${className}`}
    >
      {leading && <div className="shrink-0">{leading}</div>}
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-semibold text-ink-900">{title}</p>
        {subtitle && (
          <p className="mt-1 text-sm leading-5 text-ink-500">{subtitle}</p>
        )}
      </div>
      {trailing && <div className="shrink-0">{trailing}</div>}
    </Tag>
  );
}

function StateMessage({ children }) {
  return (
    <div className="px-6 py-6 text-sm leading-6 text-ink-700">{children}</div>
  );
}

function Loader() {
  return (
    <div className="grid min-h-64 place-items-center">
      <div className="h-9 w-9 animate-spin rounded-full border-4 border-brand-100 border-t-brand-700" />
    </div>
  );
}

function Alert({ type, children }) {
  const styles =
    type === "error"
      ? "border-red-200 bg-red-50 text-red-800"
      : "border-green-200 bg-green-50 text-green-800";
  return (
    <div className={`mx-4 mt-4 rounded-xl border px-4 py-3 text-sm ${styles}`}>
      {children}
    </div>
  );
}

function BottomSheet({ children, onClose }) {
  return (
    <div className="fixed inset-0 z-40 flex items-end justify-center bg-black/40 px-0">
      <button
        type="button"
        aria-label="Fermer"
        className="absolute inset-0 h-full w-full"
        onClick={onClose}
      />
      <div className="relative max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-t-[28px] bg-ink-50 px-6 pb-6 pt-3 shadow-2xl">
        <div className="mx-auto mb-4 h-1 w-10 rounded-full bg-ink-300" />
        {children}
      </div>
    </div>
  );
}

export default function TeacherPortalPage() {
  const { user, logout } = useAuth();
  const [tab, setTab] = useState("scan");
  const [date] = useState(todayIso);
  const [data, setData] = useState(null);
  const [loadedKey, setLoadedKey] = useState("");
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [refreshKey, setRefreshKey] = useState(0);
  const [busy, setBusy] = useState(null);
  const [drafts, setDrafts] = useState({});
  const [sheet, setSheet] = useState(null);
  const [screen, setScreen] = useState(null);
  const [password, setPassword] = useState({
    current_password: "",
    password: "",
    password_confirmation: "",
  });
  const [schedule, setSchedule] = useState({
    loading: false,
    error: "",
    data: [],
    refreshKey: 0,
  });

  const requestKey = `${tab}:${date}:${refreshKey}`;
  const loading = tab !== "scan" && loadedKey !== requestKey;

  useEffect(() => {
    let active = true;
    const requests = {
      courses: () =>
        api
          .get("/mes-cours-du-jour", { params: { date } })
          .then((response) => response.data),
      history: () =>
        api.get("/mes-presences").then((response) => response.data),
      notifications: () =>
        api.get("/notifications").then((response) => response.data),
    };

    if (!requests[tab]) {
      return () => {
        active = false;
      };
    }

    requests[tab]()
      .then((result) => {
        if (active) {
          setData(result);
          setError("");
        }
      })
      .catch((loadError) => {
        if (active) setError(apiError(loadError));
      })
      .finally(() => {
        if (active) setLoadedKey(requestKey);
      });

    return () => {
      active = false;
    };
  }, [tab, date, refreshKey, requestKey]);

  useEffect(() => {
    if (screen !== "schedule") return;
    let active = true;
    api
      .get("/emplois", { params: { enseignant_id: user.id } })
      .then((response) => {
        const page = response.data ?? {};
        const rows = Array.isArray(page.data) ? page.data : [];
        if (active) {
          setSchedule((current) => ({
            ...current,
            loading: false,
            data: rows,
          }));
        }
      })
      .catch((loadError) => {
        if (active) {
          setSchedule((current) => ({
            ...current,
            loading: false,
            error: apiError(loadError),
          }));
        }
      });
    return () => {
      active = false;
    };
  }, [screen, schedule.refreshKey, user.id]);

  async function toggleLesson(course, lesson) {
    const previous = lesson.faite;
    lesson.faite = !previous;
    setData((current) => ({ ...current }));
    setBusy(lesson.id);
    setError("");
    try {
      await api.post("/mes-cours-du-jour/lecon-toggle", {
        emploi_du_temps_id: course.emploi_du_temps_id,
        progression_lecon_id: lesson.id,
        date,
      });
    } catch (actionError) {
      lesson.faite = previous;
      setData((current) => ({ ...current }));
      setError(apiError(actionError));
    } finally {
      setBusy(null);
    }
  }

  async function saveEntry(course) {
    const draft = drafts[course.emploi_du_temps_id] ?? {};
    if (!draft.contenu?.trim()) return;
    setBusy(course.emploi_du_temps_id);
    setError("");
    setNotice("");
    try {
      await api.post("/cahier-texte", {
        emploi_du_temps_id: course.emploi_du_temps_id,
        date,
        contenu: draft.contenu.trim(),
        reference_programme: draft.reference_programme?.trim() || undefined,
      });
      setDrafts((current) => ({ ...current, [course.emploi_du_temps_id]: {} }));
      setSheet(null);
      setNotice("Entrée enregistrée dans le cahier de texte.");
    } catch (actionError) {
      setError(apiError(actionError));
    } finally {
      setBusy(null);
    }
  }

  async function markRead(notification) {
    if (notification.read_at) return;
    setBusy(notification.id);
    setError("");
    try {
      await api.post(`/notifications/${notification.id}/read`);
      setRefreshKey((key) => key + 1);
    } catch (actionError) {
      setError(apiError(actionError));
    } finally {
      setBusy(null);
    }
  }

  async function updatePassword(event) {
    event.preventDefault();
    setBusy("password");
    setError("");
    setNotice("");
    try {
      await api.put("/me/password", password);
      setPassword({
        current_password: "",
        password: "",
        password_confirmation: "",
      });
      setScreen(null);
      setNotice("Mot de passe modifié.");
    } catch (actionError) {
      setError(apiError(actionError));
    } finally {
      setBusy(null);
    }
  }

  function changeTab(nextTab) {
    setError("");
    setNotice("");
    setTab(nextTab);
  }

  function refreshData() {
    setError("");
    setNotice("");
    setRefreshKey((key) => key + 1);
  }

  function openSchedule() {
    setSchedule((current) => ({ ...current, loading: true, error: "" }));
    setScreen("schedule");
  }

  function refreshSchedule() {
    setSchedule((current) => ({
      ...current,
      loading: true,
      error: "",
      refreshKey: current.refreshKey + 1,
    }));
  }

  const courses = data?.cours ?? [];

  const mainActions = (
    <>
      <IconButton
        label="Mon emploi du temps"
        icon="calendar_month"
        onClick={openSchedule}
      />
      <IconButton
        label="Modifier le mot de passe"
        icon="lock"
        onClick={() => setScreen("password")}
      />
      <IconButton label="Déconnexion" icon="logout" onClick={logout} />
    </>
  );

  if (screen === "schedule") {
    return (
      <Frame
        title="Mon emploi du temps"
        leading={
          <IconButton
            label="Retour"
            icon="arrow_back"
            onClick={() => setScreen(null)}
          />
        }
      >
        <ScheduleScreen
          schedule={schedule}
          onRefresh={refreshSchedule}
        />
      </Frame>
    );
  }

  if (screen === "password") {
    return (
      <Frame
        title="Modifier le mot de passe"
        leading={
          <IconButton
            label="Retour"
            icon="arrow_back"
            onClick={() => setScreen(null)}
          />
        }
      >
        <PasswordScreen
          password={password}
          setPassword={setPassword}
          busy={busy === "password"}
          onSubmit={updatePassword}
          error={error}
        />
      </Frame>
    );
  }

  return (
    <Frame
      title={TITLES[tab]}
      actions={mainActions}
      bottomNav={<BottomNav value={tab} onChange={changeTab} />}
    >
      {error && <Alert type="error">{error}</Alert>}
      {notice && <Alert type="success">{notice}</Alert>}

      {tab === "scan" && (
        <ScanHome nom={user.nom} onScan={() => setSheet({ type: "scan" })} />
      )}

      {tab === "history" && (
        <HistoryScreen
          loading={loading}
          entries={Array.isArray(data) ? data : []}
          onRetry={refreshData}
          error={error}
        />
      )}

      {tab === "courses" && (
        <CoursesScreen
          loading={loading}
          data={data}
          courses={courses}
          busy={busy}
          drafts={drafts}
          setDrafts={setDrafts}
          onToggle={toggleLesson}
          onOpenNotebook={(course) => setSheet({ type: "notebook", course })}
        />
      )}

      {tab === "notifications" && (
        <NotificationsScreen
          loading={loading}
          entries={Array.isArray(data) ? data : []}
          busy={busy}
          onMarkRead={markRead}
        />
      )}

      {sheet?.type === "scan" && (
        <BottomSheet onClose={() => setSheet(null)}>
          <div className="text-center">
            <div className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-brand-100 text-brand-700">
              <Icon name="qr_code_scanner" className="text-[38px]" />
            </div>
            <h2 className="mt-5 text-2xl font-extrabold text-ink-900">
              Scanner ma présence
            </h2>
            <p className="mt-2 text-sm leading-6 text-ink-700">
              Le pointage web conserve la même entrée que l’application mobile.
              Le scan réel passe par l’application, car la transmission à la
              borne utilise le Bluetooth local.
            </p>
            <button
              type="button"
              onClick={() => setSheet(null)}
              className="mt-6 inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-brand-700 px-5 text-sm font-bold text-white transition hover:bg-brand-800"
            >
              <Icon name="done" />
              Terminer
            </button>
          </div>
        </BottomSheet>
      )}

      {sheet?.type === "notebook" && (
        <NotebookSheet
          course={sheet.course}
          draft={drafts[sheet.course.emploi_du_temps_id] ?? {}}
          setDrafts={setDrafts}
          busy={busy === sheet.course.emploi_du_temps_id}
          onSubmit={() => saveEntry(sheet.course)}
          onClose={() => setSheet(null)}
        />
      )}
    </Frame>
  );
}

function ScanHome({ nom, onScan }) {
  return (
    <div className="grid min-h-[calc(100vh-10rem)] place-items-center px-6 py-12 text-center">
      <div>
        <img src="/logo.png" alt="" className="mx-auto h-16 w-16" />
        <h2 className="mt-4 text-xl font-bold text-ink-900">Bonjour, {nom}</h2>
        <button
          type="button"
          onClick={onScan}
          className="mt-6 inline-flex min-h-16 items-center justify-center gap-3 rounded-xl bg-brand-700 px-8 py-5 text-base font-bold text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-700 focus:ring-offset-2"
        >
          <Icon name="qr_code_scanner" />
          Scanner ma présence
        </button>
      </div>
    </div>
  );
}

function HistoryScreen({ loading, entries, onRetry, error }) {
  if (loading) return <Loader />;

  if (error) {
    return (
      <StateMessage>
        <p className="text-center">{error}</p>
        <button
          type="button"
          onClick={onRetry}
          className="mx-auto mt-3 block rounded-xl border border-ink-100 bg-white px-4 py-2 text-sm font-semibold text-ink-700"
        >
          Réessayer
        </button>
      </StateMessage>
    );
  }

  if (!entries.length) {
    return <StateMessage>Aucune présence ce mois-ci.</StateMessage>;
  }

  return (
    <div className="space-y-2 p-4">
      {entries.map((entry) => {
        const late = Number(entry.minutes_retard ?? 0) > 0;
        return (
          <Card key={entry.id}>
            <ListTile
              title={entry.date}
              subtitle={`Arrivée : ${formatTime(entry.heure_arrivee)}   Départ : ${formatTime(entry.heure_depart)}`}
              trailing={
                late ? (
                  <span className="rounded-full bg-orange-100 px-3 py-2 text-xs font-semibold text-orange-800">
                    Retard {entry.minutes_retard} min
                  </span>
                ) : (
                  <Icon name="check_circle" className="text-green-600" />
                )
              }
            />
          </Card>
        );
      })}
    </div>
  );
}

function CoursesScreen({
  loading,
  data,
  courses,
  busy,
  drafts,
  setDrafts,
  onToggle,
  onOpenNotebook,
}) {
  if (loading) return <Loader />;

  if (data?.present !== true) {
    return (
      <StateMessage>
        Pointez votre présence pour déclarer les leçons du jour.
      </StateMessage>
    );
  }

  if (!courses.length) {
    return <StateMessage>Aucun cours prévu aujourd’hui.</StateMessage>;
  }

  return (
    <div className="space-y-3 p-4">
      {courses.map((course) => {
        const lessons = course.lecons ?? [];
        return (
          <Card key={course.emploi_du_temps_id}>
            <ListTile
              title={`${course.discipline} - ${course.classe}`}
              subtitle={`${course.heure_debut} - ${course.heure_fin}`}
            />
            <div className="border-t border-ink-100">
              {lessons.map((lesson) => (
                <label
                  key={lesson.id}
                  className="flex items-start gap-3 px-4 py-3 transition hover:bg-ink-50"
                >
                  <input
                    type="checkbox"
                    checked={lesson.faite}
                    disabled={busy === lesson.id}
                    onChange={() => onToggle(course, lesson)}
                    className="mt-1 h-5 w-5 rounded border-ink-300 accent-brand-700"
                  />
                  <span className="min-w-0 text-sm text-ink-900">
                    <span className="font-medium">
                      {lesson.ordre}. {lesson.titre}
                    </span>
                    {lesson.unite_apprentissage && (
                      <span className="mt-1 block text-sm text-ink-500">
                        {lesson.unite_apprentissage}
                      </span>
                    )}
                  </span>
                </label>
              ))}
              {!lessons.length && (
                <div className="px-4 py-4 text-sm text-ink-600">
                  Aucune progression importée pour cette classe et cette
                  matière.
                </div>
              )}
            </div>
            <div className="flex justify-end px-2 pb-2">
              <button
                type="button"
                onClick={() => {
                  const draft = drafts[course.emploi_du_temps_id] ?? {};
                  setDrafts((current) => ({
                    ...current,
                    [course.emploi_du_temps_id]: draft,
                  }));
                  onOpenNotebook(course);
                }}
                className="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-brand-700 transition hover:bg-brand-50"
              >
                <Icon name="edit_note" className="text-[20px]" />
                Cahier de texte
              </button>
            </div>
          </Card>
        );
      })}
    </div>
  );
}

function NotificationsScreen({ loading, entries, busy, onMarkRead }) {
  if (loading) return <Loader />;

  if (!entries.length) {
    return <StateMessage>Aucune notification.</StateMessage>;
  }

  return (
    <div className="space-y-2 p-4">
      {entries.map((entry) => {
        const read = Boolean(entry.read_at);
        return (
          <Card
            key={entry.id}
            className={!read ? "border-blue-100 bg-blue-50" : ""}
          >
            <ListTile
              onClick={() => onMarkRead(entry)}
              leading={
                <Icon
                  name={read ? "notifications" : "notifications_active"}
                  className={read ? "text-ink-400" : "text-blue-600"}
                />
              }
              title={entry.message}
              subtitle={formatDateTime(entry.created_at)}
              trailing={
                busy === entry.id ? (
                  <div className="h-5 w-5 animate-spin rounded-full border-2 border-blue-200 border-t-blue-600" />
                ) : null
              }
            />
          </Card>
        );
      })}
    </div>
  );
}

function NotebookSheet({
  course,
  draft,
  setDrafts,
  busy,
  onSubmit,
  onClose,
}) {
  function updateDraft(field, value) {
    setDrafts((current) => ({
      ...current,
      [course.emploi_du_temps_id]: {
        ...(current[course.emploi_du_temps_id] ?? {}),
        [field]: value,
      },
    }));
  }

  return (
    <BottomSheet onClose={onClose}>
      <h2 className="text-xl font-bold text-ink-900">Cahier de texte</h2>
      <p className="mt-1 text-sm text-ink-500">
        {course.discipline} - {course.classe}
      </p>
      <div className="mt-5 space-y-3">
        <label className="block text-sm font-medium text-ink-700">
          Contenu de la séance *
          <textarea
            value={draft.contenu ?? ""}
            onChange={(event) => updateDraft("contenu", event.target.value)}
            rows={3}
            className="mt-1 block w-full resize-none rounded-xl border border-ink-100 bg-white px-3 py-3 text-sm outline-none transition focus:border-brand-700 focus:ring-2 focus:ring-brand-700/20"
          />
        </label>
        <label className="block text-sm font-medium text-ink-700">
          Référence programme
          <input
            value={draft.reference_programme ?? ""}
            onChange={(event) =>
              updateDraft("reference_programme", event.target.value)
            }
            className="mt-1 block w-full rounded-xl border border-ink-100 bg-white px-3 py-3 text-sm outline-none transition focus:border-brand-700 focus:ring-2 focus:ring-brand-700/20"
          />
        </label>
        <button
          type="button"
          onClick={onSubmit}
          disabled={busy || !draft.contenu?.trim()}
          className="inline-flex h-12 w-full items-center justify-center rounded-xl bg-brand-700 px-5 text-sm font-bold text-white transition hover:bg-brand-800 disabled:opacity-50"
        >
          {busy ? (
            <span className="h-5 w-5 animate-spin rounded-full border-2 border-white/40 border-t-white" />
          ) : (
            "Enregistrer"
          )}
        </button>
      </div>
    </BottomSheet>
  );
}

function PasswordScreen({
  password,
  setPassword,
  busy,
  onSubmit,
  error,
}) {
  return (
    <div className="p-4">
      {error && <Alert type="error">{error}</Alert>}
      <form onSubmit={onSubmit} className="space-y-3">
        <label className="block text-sm font-medium text-ink-700">
          Mot de passe actuel
          <input
            required
            type="password"
            autoComplete="current-password"
            value={password.current_password}
            onChange={(event) =>
              setPassword({
                ...password,
                current_password: event.target.value,
              })
            }
            className="mt-1 block w-full rounded-xl border border-ink-100 bg-white px-3 py-3 text-sm outline-none transition focus:border-brand-700 focus:ring-2 focus:ring-brand-700/20"
          />
        </label>
        <label className="block text-sm font-medium text-ink-700">
          Nouveau mot de passe
          <input
            required
            minLength={8}
            type="password"
            autoComplete="new-password"
            value={password.password}
            onChange={(event) =>
              setPassword({ ...password, password: event.target.value })
            }
            className="mt-1 block w-full rounded-xl border border-ink-100 bg-white px-3 py-3 text-sm outline-none transition focus:border-brand-700 focus:ring-2 focus:ring-brand-700/20"
          />
        </label>
        <label className="block text-sm font-medium text-ink-700">
          Confirmer le nouveau mot de passe
          <input
            required
            minLength={8}
            type="password"
            autoComplete="new-password"
            value={password.password_confirmation}
            onChange={(event) =>
              setPassword({
                ...password,
                password_confirmation: event.target.value,
              })
            }
            className="mt-1 block w-full rounded-xl border border-ink-100 bg-white px-3 py-3 text-sm outline-none transition focus:border-brand-700 focus:ring-2 focus:ring-brand-700/20"
          />
        </label>
        <button
          disabled={busy}
          className="inline-flex h-12 w-full items-center justify-center rounded-xl bg-brand-700 px-5 text-sm font-bold text-white transition hover:bg-brand-800 disabled:opacity-50"
        >
          {busy ? (
            <span className="h-5 w-5 animate-spin rounded-full border-2 border-white/40 border-t-white" />
          ) : (
            "Enregistrer"
          )}
        </button>
      </form>
    </div>
  );
}

function ScheduleScreen({ schedule, onRefresh }) {
  const coursesByDay = useMemo(() => {
    const grouped = new Map();
    for (const course of schedule.data) {
      const day = Number(course.jour);
      if (!Number.isInteger(day) || day < 1 || day > 7) continue;
      if (!grouped.has(day)) grouped.set(day, []);
      grouped.get(day).push(course);
    }
    for (const list of grouped.values()) {
      list.sort((a, b) =>
        `${a.heure_debut ?? ""}`.localeCompare(`${b.heure_debut ?? ""}`),
      );
    }
    return grouped;
  }, [schedule.data]);

  const today = new Date().getDay() || 7;
  const days = Array.from(coursesByDay.keys()).sort(
    (a, b) => ((a - today + 7) % 7) - ((b - today + 7) % 7),
  );

  if (schedule.loading) return <Loader />;

  if (schedule.error) {
    return (
      <StateMessage>
        <p className="text-red-700">Impossible de charger l’emploi du temps.</p>
        <button
          type="button"
          onClick={onRefresh}
          className="mt-3 rounded-xl border border-ink-100 bg-white px-4 py-2 text-sm font-semibold text-ink-700"
        >
          Réessayer
        </button>
      </StateMessage>
    );
  }

  if (!days.length) {
    return <StateMessage>Aucun cours planifié.</StateMessage>;
  }

  return (
    <div className="divide-y divide-ink-100 py-2">
      {days.map((day) => (
        <details key={day} open={day === today} className="group">
          <summary className="flex cursor-pointer items-center gap-3 px-4 py-4 marker:content-none">
            <Icon
              name="calendar_today"
              className={day === today ? "text-brand-700" : "text-ink-500"}
            />
            <div className="min-w-0 flex-1">
              <p
                className={`font-semibold ${day === today ? "text-brand-700" : "text-ink-900"}`}
              >
                {DAY_NAMES[day] ?? `Jour ${day}`}
              </p>
              {day === today && (
                <p className="mt-1 text-sm text-ink-500">Aujourd’hui</p>
              )}
            </div>
            <Icon
              name="expand_more"
              className="text-ink-500 transition group-open:rotate-180"
            />
          </summary>
          <div>
            {coursesByDay.get(day).map((course) => (
              <ListTile
                key={course.id}
                leading={<Icon name="schedule" className="text-ink-500" />}
                title={`${course.heure_debut ?? "--:--"} - ${course.heure_fin ?? "--:--"}`}
                subtitle={`${course.classe?.nom ?? "Classe"} - ${course.discipline?.nom ?? "Matière"}${course.salle ? ` - ${course.salle}` : ""}`}
                className="pl-10"
              />
            ))}
          </div>
        </details>
      ))}
    </div>
  );
}
