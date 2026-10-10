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
    <div className="min-h-screen bg-transparent">
      <header className="border-b border-brand-200 bg-white/80 backdrop-blur-sm">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-4 py-3">
          <span className="text-lg font-black tracking-[-0.06em] text-brand-900">
            Attiékoi · Plateforme
          </span>

          <nav className="flex flex-1 flex-wrap gap-2">
            {LIENS.map((lien) => (
              <NavLink
                key={lien.to}
                to={lien.to}
                end={lien.end}
                className={({ isActive }) =>
                  `rounded-full px-3 py-1.5 text-sm font-medium transition ${
                    isActive
                      ? "bg-brand-100 text-brand-900 shadow-sm"
                      : "text-ink-700 hover:bg-brand-50"
                  }`
                }
              >
                {lien.label}
              </NavLink>
            ))}
          </nav>

          <div className="flex items-center gap-3 text-sm">
            <span className="text-ink-500">
              {compte?.name} · {compte?.role}
            </span>
            <button
              type="button"
              onClick={deconnexion}
              className="rounded-full border border-brand-200 bg-brand-50 px-3 py-1.5 font-medium text-brand-800 transition hover:bg-brand-100"
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
