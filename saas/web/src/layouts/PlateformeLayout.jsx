import { NavLink, Outlet } from "react-router-dom";
import { usePlatformAuth } from "../context/PlatformAuthContext";

const LIENS = [
  {
    to: "/plateforme",
    label: "Tableau de bord",
    icon: "space_dashboard",
    end: true,
  },
  { to: "/plateforme/etablissements", label: "Établissements", icon: "school" },
  {
    to: "/plateforme/facturation",
    label: "Offres & facturation",
    icon: "receipt_long",
  },
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
      <header className="sticky top-0 z-30 border-b border-brand-200 bg-white/85 backdrop-blur-md">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3">
          <span className="flex items-center gap-2 text-lg font-black tracking-tight text-brand-900">
            <img src="/logo.png" alt="" className="h-7 w-7 object-contain" />
            Auditron
            {/* Badge « Plateforme » : repère permanent pour ne pas confondre ce
                portail avec le backoffice d'un établissement. */}
            <span className="badge bg-brand-900 px-2.5 py-1 text-white">
              Plateforme
            </span>
          </span>

          <nav
            aria-label="Navigation plateforme"
            className="flex flex-1 flex-wrap gap-1.5"
          >
            {LIENS.map((lien) => (
              <NavLink
                key={lien.to}
                to={lien.to}
                end={lien.end}
                className={({ isActive }) =>
                  `inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition ${
                    isActive
                      ? "bg-brand-100 text-brand-900 shadow-sm"
                      : "text-ink-700 hover:bg-brand-50 hover:text-brand-800"
                  }`
                }
              >
                <span
                  aria-hidden="true"
                  className="material-symbols-rounded text-[18px]"
                >
                  {lien.icon}
                </span>
                {lien.label}
              </NavLink>
            ))}
          </nav>

          <div className="flex items-center gap-3 text-sm">
            <span className="hidden text-ink-500 sm:inline">
              {compte?.name} · {compte?.role}
            </span>
            <button
              type="button"
              onClick={deconnexion}
              className="btn-secondary rounded-full px-3 py-1.5"
            >
              <span
                aria-hidden="true"
                className="material-symbols-rounded text-[18px]"
              >
                logout
              </span>
              Déconnexion
            </button>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-6xl p-4 sm:p-6">
        <Outlet />
      </main>
    </div>
  );
}
