import { NavLink, Outlet } from "react-router-dom";
import { usePlatformAuth } from "../context/PlatformAuthContext";

const LIENS = [
  { to: "/plateforme", label: "Tableau de bord", end: true },
  { to: "/plateforme/etablissements", label: "Établissements" },
  { to: "/plateforme/facturation", label: "Offres & facturation" },
];

/**
 * Coquille du portail éditeur. Intentionnellement sobre et distincte de celle
 * des établissements : quand on administre les données de tous les clients à
 * la fois, savoir d'un coup d'œil dans quel portail on se trouve est une
 * sécurité, pas une coquetterie.
 */
export default function PlateformeLayout() {
  const { compte, deconnexion } = usePlatformAuth();

  return (
    <div className="min-h-screen bg-ink-50">
      <header className="bg-ink-900 text-ink-50">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-4 py-3">
          <span className="font-bold">Auditron · Plateforme</span>

          <nav className="flex flex-1 flex-wrap gap-1">
            {LIENS.map((lien) => (
              <NavLink
                key={lien.to}
                to={lien.to}
                end={lien.end}
                className={({ isActive }) =>
                  `rounded-lg px-3 py-1.5 text-sm transition ${
                    isActive ? "bg-ink-700 font-semibold" : "hover:bg-ink-800"
                  }`
                }
              >
                {lien.label}
              </NavLink>
            ))}
          </nav>

          <div className="flex items-center gap-3 text-sm">
            <span className="text-ink-300">
              {compte?.name} · {compte?.role}
            </span>
            <button
              type="button"
              onClick={deconnexion}
              className="rounded-lg border border-ink-600 px-3 py-1.5 transition hover:bg-ink-800"
            >
              Déconnexion
            </button>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-6xl p-4">
        <Outlet />
      </main>
    </div>
  );
}
