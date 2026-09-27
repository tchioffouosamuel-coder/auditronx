import { useEffect, useState } from "react";
import { useAuth } from "../context/AuthContext";
import api from "../lib/api";

const TABS = [
  ["courses", "Mes cours"],
  ["history", "Présences"],
  ["notebook", "Cahier de texte"],
  ["notifications", "Notifications"],
  ["account", "Mon compte"],
];

function apiError(error) {
  return (
    error.response?.data?.message ?? error.message ?? "Une erreur est survenue."
  );
}

function Panel({ children }) {
  return (
    <section className="rounded-lg border border-ink-100 bg-white p-4 shadow-sm sm:p-6">
      {children}
    </section>
  );
}

export default function TeacherPortalPage() {
  const { user, logout } = useAuth();
  const [tab, setTab] = useState("courses");
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [data, setData] = useState(null);
  const [loadedKey, setLoadedKey] = useState("");
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [refreshKey, setRefreshKey] = useState(0);
  const [busy, setBusy] = useState(null);
  const [drafts, setDrafts] = useState({});
  const [password, setPassword] = useState({
    current_password: "",
    password: "",
    password_confirmation: "",
  });
  const requestKey = `${tab}:${date}:${refreshKey}`;
  const loading = tab !== "account" && loadedKey !== requestKey;

  useEffect(() => {
    let active = true;
    const requests = {
      courses: () =>
        api
          .get("/mes-cours-du-jour", { params: { date } })
          .then((response) => response.data),
      history: () =>
        api.get("/mes-presences").then((response) => response.data),
      notebook: () =>
        api
          .get(`/cahier-texte/${user.id}`)
          .then((response) => response.data.data ?? []),
      notifications: () =>
        api.get("/notifications").then((response) => response.data),
    };
    if (tab === "account") {
      return () => {
        active = false;
      };
    }
    requests[tab]()
      .then((result) => {
        if (active) setData(result);
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
  }, [tab, date, refreshKey, requestKey, user.id]);

  async function toggleLesson(course, lesson) {
    setBusy(lesson.id);
    setError("");
    try {
      await api.post("/mes-cours-du-jour/lecon-toggle", {
        emploi_du_temps_id: course.emploi_du_temps_id,
        progression_lecon_id: lesson.id,
        date,
      });
      setRefreshKey((key) => key + 1);
    } catch (actionError) {
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
    try {
      await api.post("/cahier-texte", {
        emploi_du_temps_id: course.emploi_du_temps_id,
        date,
        contenu: draft.contenu.trim(),
        reference_programme: draft.reference_programme?.trim() || undefined,
      });
      setDrafts((current) => ({ ...current, [course.emploi_du_temps_id]: {} }));
      setNotice("Entrée enregistrée dans le cahier de texte.");
    } catch (actionError) {
      setError(apiError(actionError));
    } finally {
      setBusy(null);
    }
  }

  async function markRead(notification) {
    setBusy(notification.id);
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
      setNotice("Mot de passe modifié.");
    } catch (actionError) {
      setError(apiError(actionError));
    } finally {
      setBusy(null);
    }
  }

  function refreshData() {
    setError("");
    setNotice("");
    setRefreshKey((key) => key + 1);
  }

  const courses = data?.cours ?? [];

  return (
    <div className="mx-auto max-w-5xl">
      <header className="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-ink-100 pb-5">
        <div className="flex items-center gap-3">
          <img src="/logo.png" alt="" className="h-11 w-11" />
          <div>
            <p className="text-xs font-semibold uppercase text-brand-700">
              Auditron X · Espace enseignant
            </p>
            <h1 className="text-xl font-semibold text-ink-900">
              Bonjour, {user.nom}
            </h1>
          </div>
        </div>
        <button
          onClick={logout}
          className="rounded-md border border-ink-100 px-3 py-2 text-sm font-medium text-ink-700 hover:bg-white"
        >
          Déconnexion
        </button>
      </header>

      <nav
        className="mb-5 flex gap-1 overflow-x-auto border-b border-ink-100"
        aria-label="Navigation enseignant"
      >
        {TABS.map(([id, label]) => (
          <button
            key={id}
            onClick={() => {
              setError("");
              setNotice("");
              setTab(id);
            }}
            className={`shrink-0 border-b-2 px-3 py-3 text-sm font-medium ${tab === id ? "border-brand-700 text-brand-800" : "border-transparent text-ink-500 hover:text-ink-900"}`}
          >
            {label}
          </button>
        ))}
      </nav>

      {error && (
        <div
          role="alert"
          className="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
        >
          {error}
        </div>
      )}
      {notice && (
        <div
          role="status"
          className="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
        >
          {notice}
        </div>
      )}

      {tab === "courses" && (
        <div className="space-y-4">
          <div className="flex flex-wrap items-end justify-between gap-3">
            <div>
              <h2 className="text-lg font-semibold text-ink-900">Mes cours</h2>
              <p className="mt-1 text-sm text-ink-500">
                Pointage requis pour déclarer les leçons réalisées.
              </p>
            </div>
            <label className="text-sm text-ink-700">
              Date
              <input
                type="date"
                value={date}
                onChange={(event) => {
                  setError("");
                  setNotice("");
                  setDate(event.target.value);
                }}
                className="mt-1 block rounded-md border border-ink-100 bg-white px-3 py-2"
              />
            </label>
          </div>
          <Panel>
            {!loading && (
              <div
                className={`mb-4 border-l-4 px-3 py-2 text-sm ${data?.present ? "border-green-600 bg-green-50 text-green-800" : "border-gold-600 bg-gold-100 text-ink-700"}`}
              >
                {data?.present
                  ? "Présence enregistrée pour cette date."
                  : "Pointez votre présence dans l’application mobile pour déclarer les leçons."}
              </div>
            )}
            {loading ? (
              <p className="py-8 text-center text-sm text-ink-500">
                Chargement des cours…
              </p>
            ) : courses.length === 0 ? (
              <p className="py-8 text-center text-sm text-ink-500">
                Aucun cours prévu à cette date.
              </p>
            ) : (
              <div className="divide-y divide-ink-100">
                {courses.map((course) => {
                  const draft = drafts[course.emploi_du_temps_id] ?? {};
                  return (
                    <article
                      key={course.emploi_du_temps_id}
                      className="py-5 first:pt-1 last:pb-1"
                    >
                      <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <h3 className="font-semibold text-ink-900">
                          {course.discipline} · {course.classe}
                        </h3>
                        <span className="text-sm tabular-nums text-ink-500">
                          {course.heure_debut} – {course.heure_fin}
                        </span>
                      </div>
                      {course.lecons?.length ? (
                        <div className="space-y-2">
                          {course.lecons.map((lesson) => (
                            <label
                              key={lesson.id}
                              className="flex items-start gap-3 rounded-md px-2 py-2 hover:bg-ink-50"
                            >
                              <input
                                type="checkbox"
                                checked={lesson.faite}
                                disabled={!data?.present || busy === lesson.id}
                                onChange={() => toggleLesson(course, lesson)}
                                className="mt-1 h-4 w-4 accent-brand-700"
                              />
                              <span className="text-sm text-ink-800">
                                {lesson.ordre}. {lesson.titre}
                                {lesson.unite_apprentissage && (
                                  <span className="block text-xs text-ink-500">
                                    {lesson.unite_apprentissage}
                                  </span>
                                )}
                              </span>
                            </label>
                          ))}
                        </div>
                      ) : (
                        <p className="text-sm text-ink-500">
                          Aucune leçon de programme disponible pour ce cours.
                        </p>
                      )}
                      <div className="mt-4 grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                        <input
                          value={draft.contenu ?? ""}
                          onChange={(event) =>
                            setDrafts((current) => ({
                              ...current,
                              [course.emploi_du_temps_id]: {
                                ...draft,
                                contenu: event.target.value,
                              },
                            }))
                          }
                          placeholder="Contenu réellement traité"
                          className="rounded-md border border-ink-100 px-3 py-2 text-sm"
                        />
                        <input
                          value={draft.reference_programme ?? ""}
                          onChange={(event) =>
                            setDrafts((current) => ({
                              ...current,
                              [course.emploi_du_temps_id]: {
                                ...draft,
                                reference_programme: event.target.value,
                              },
                            }))
                          }
                          placeholder="Référence du programme (facultatif)"
                          className="rounded-md border border-ink-100 px-3 py-2 text-sm"
                        />
                        <button
                          onClick={() => saveEntry(course)}
                          disabled={
                            !draft.contenu?.trim() ||
                            busy === course.emploi_du_temps_id
                          }
                          className="rounded-md bg-brand-700 px-3 py-2 text-sm font-medium text-white hover:bg-brand-800 disabled:opacity-50"
                        >
                          Enregistrer
                        </button>
                      </div>
                    </article>
                  );
                })}
              </div>
            )}
          </Panel>
        </div>
      )}

      {tab === "history" && (
        <div className="space-y-4">
          <h2 className="text-lg font-semibold text-ink-900">
            Historique des présences · mois en cours
          </h2>
          <Panel>
            {loading ? (
              <p className="py-8 text-center text-sm text-ink-500">
                Chargement…
              </p>
            ) : !data?.length ? (
              <p className="py-8 text-center text-sm text-ink-500">
                Aucune présence enregistrée ce mois-ci.
              </p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full min-w-[560px] text-left text-sm">
                  <thead className="border-b border-ink-100 text-xs uppercase text-ink-500">
                    <tr>
                      <th className="py-3 pr-3">Date</th>
                      <th className="py-3 pr-3">Arrivée</th>
                      <th className="py-3 pr-3">Départ</th>
                      <th className="py-3 pr-3">Retard</th>
                      <th className="py-3">Source</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-ink-100">
                    {data.map((entry) => (
                      <tr key={entry.id}>
                        <td className="py-3 pr-3">{entry.date}</td>
                        <td className="py-3 pr-3">
                          {entry.heure_arrivee ?? "—"}
                        </td>
                        <td className="py-3 pr-3">
                          {entry.heure_depart ?? "—"}
                        </td>
                        <td className="py-3 pr-3">
                          {entry.minutes_retard
                            ? `${entry.minutes_retard} min`
                            : "—"}
                        </td>
                        <td className="py-3">{entry.source ?? "—"}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </Panel>
        </div>
      )}

      {tab === "notebook" && (
        <div className="space-y-4">
          <h2 className="text-lg font-semibold text-ink-900">
            Cahier de texte
          </h2>
          <Panel>
            {loading ? (
              <p className="py-8 text-center text-sm text-ink-500">
                Chargement…
              </p>
            ) : !data?.length ? (
              <p className="py-8 text-center text-sm text-ink-500">
                Aucune entrée enregistrée.
              </p>
            ) : (
              <div className="divide-y divide-ink-100">
                {data.map((entry) => (
                  <article key={entry.id} className="py-4 first:pt-0 last:pb-0">
                    <div className="flex flex-wrap justify-between gap-2 text-xs text-ink-500">
                      <span>{entry.date}</span>
                      <span>
                        {entry.emploi_du_temps?.classe?.nom} ·{" "}
                        {entry.emploi_du_temps?.discipline?.nom}
                      </span>
                    </div>
                    <p className="mt-2 whitespace-pre-wrap text-sm text-ink-900">
                      {entry.contenu}
                    </p>
                    {entry.reference_programme && (
                      <p className="mt-2 text-xs text-ink-500">
                        Référence programme : {entry.reference_programme}
                      </p>
                    )}
                  </article>
                ))}
              </div>
            )}
          </Panel>
        </div>
      )}

      {tab === "notifications" && (
        <div className="space-y-4">
          <h2 className="text-lg font-semibold text-ink-900">Notifications</h2>
          <Panel>
            {loading ? (
              <p className="py-8 text-center text-sm text-ink-500">
                Chargement…
              </p>
            ) : !data?.length ? (
              <p className="py-8 text-center text-sm text-ink-500">
                Aucune notification.
              </p>
            ) : (
              <div className="divide-y divide-ink-100">
                {data.map((notification) => (
                  <article
                    key={notification.id}
                    className="flex flex-wrap items-center justify-between gap-3 py-4 first:pt-0 last:pb-0"
                  >
                    <div>
                      <p className="text-sm text-ink-900">
                        {notification.message}
                      </p>
                      <p className="mt-1 text-xs text-ink-500">
                        {new Date(notification.created_at).toLocaleString(
                          "fr-FR",
                        )}
                      </p>
                    </div>
                    {notification.read_at ? (
                      <span className="text-xs text-ink-500">Lue</span>
                    ) : (
                      <button
                        disabled={busy === notification.id}
                        onClick={() => markRead(notification)}
                        className="rounded-md border border-brand-700 px-3 py-1.5 text-sm font-medium text-brand-800 hover:bg-brand-50"
                      >
                        Marquer comme lue
                      </button>
                    )}
                  </article>
                ))}
              </div>
            )}
          </Panel>
        </div>
      )}

      {tab === "account" && (
        <div className="space-y-4">
          <h2 className="text-lg font-semibold text-ink-900">Mon compte</h2>
          <Panel>
            <dl className="grid gap-4 sm:grid-cols-2">
              <div>
                <dt className="text-xs uppercase text-ink-500">Nom</dt>
                <dd className="mt-1 text-sm font-medium">{user.nom}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-ink-500">Téléphone</dt>
                <dd className="mt-1 text-sm font-medium">{user.tel ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-ink-500">Email</dt>
                <dd className="mt-1 text-sm font-medium">
                  {user.email ?? "—"}
                </dd>
              </div>
              <div>
                <dt className="text-xs uppercase text-ink-500">Section</dt>
                <dd className="mt-1 text-sm font-medium">
                  {user.section ?? "—"}
                </dd>
              </div>
            </dl>
          </Panel>
          <Panel>
            <h3 className="mb-4 font-semibold text-ink-900">
              Modifier le mot de passe
            </h3>
            <form onSubmit={updatePassword} className="grid max-w-xl gap-3">
              <label className="text-sm">
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
                  className="mt-1 block w-full rounded-md border border-ink-100 px-3 py-2"
                />
              </label>
              <label className="text-sm">
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
                  className="mt-1 block w-full rounded-md border border-ink-100 px-3 py-2"
                />
              </label>
              <label className="text-sm">
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
                  className="mt-1 block w-full rounded-md border border-ink-100 px-3 py-2"
                />
              </label>
              <div>
                <button
                  disabled={busy === "password"}
                  className="rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800 disabled:opacity-50"
                >
                  Mettre à jour
                </button>
              </div>
            </form>
          </Panel>
        </div>
      )}

      {tab !== "account" && !loading && error && (
        <button
          onClick={refreshData}
          className="mt-4 text-sm font-medium text-brand-800 underline"
        >
          Réessayer
        </button>
      )}
      <p className="mt-6 border-t border-ink-100 pt-4 text-xs text-ink-500">
        Pour pointer votre présence, utilisez le scan QR dans l’application
        mobile.
      </p>
    </div>
  );
}
