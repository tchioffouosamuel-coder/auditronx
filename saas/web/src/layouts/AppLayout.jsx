import { useEffect, useState } from "react";
import { NavLink, Outlet, useLocation } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import { useTenant } from "../context/TenantContext";

/**
 * Le journal d'audit sert à contrôler les rôles restreints : l'API le réserve
 * aux accréditations à accès total qui ne sont pas elles-mêmes bridées
 * (surveillance générale). On masque le lien dans les mêmes conditions, pour
 * ne pas proposer une page qui répondrait 403.
 */
function aAccesTotal(user) {
  const accreditation = user?.accreditation;

  if (!accreditation) return true;

  return accreditation.groupe === "*" && !accreditation.exclut_administration;
}

const NAV_SECTIONS = [
  {
    title: "Vue d’ensemble",
    links: [{ to: "/", label: "Tableau de bord", end: true }],
  },
  {
    title: "Personnel & structure",
    links: [
      { to: "/personnel", label: "Personnel" },
      { to: "/classes", label: "Classes" },
      { to: "/disciplines", label: "Disciplines" },
      { to: "/emplois", label: "Emplois du temps" },
      { to: "/accreditations", label: "Accréditations", visible: aAccesTotal },
    ],
  },
  {
    title: "Présence",
    links: [
      { to: "/retards", label: "Retards & bilans" },
      { to: "/assiduite", label: "Assiduité & rapports" },
      { to: "/validation", label: "Validation des présences" },
      { to: "/signalements", label: "Signalements" },
      { to: "/feries", label: "Jours fériés" },
      { to: "/alertes", label: "Alertes d’absences" },
    ],
  },
  {
    title: "Pédagogie",
    links: [
      { to: "/cahier-texte", label: "Cahier de texte" },
      { to: "/fiche-progression", label: "Fiche de progression" },
    ],
  },
  {
    title: "Administration",
    links: [
      { to: "/appareils", label: "Appareils & points d’accès" },
      { to: "/moniteur-bornes", label: "Moniteur des bornes" },
      { to: "/firmwares", label: "Mises à jour firmware" },
      { to: "/journal-audit", label: "Journal d’audit", visible: aAccesTotal },
      { to: "/configuration", label: "Configuration" },
    ],
  },
];

function SidebarContent({ onNavigate }) {
  const { user, logout } = useAuth();
  const { code, branding, changerEtablissement } = useTenant();

  return (
    <div className="relative flex h-full flex-col overflow-hidden bg-gradient-to-b from-brand-950 via-brand-900 to-brand-800 text-brand-50">
      {/* Décor : halos diffus façon "circuit" repris de l'identité visuelle. */}
      <div className="pointer-events-none absolute -top-16 -right-20 h-56 w-56 rounded-full bg-brand-400/20 blur-3xl" />
      <div className="pointer-events-none absolute top-1/3 -left-16 h-48 w-48 rounded-full bg-gold-500/10 blur-3xl" />
      <div className="pointer-events-none absolute -bottom-20 -right-10 h-64 w-64 rounded-full bg-brand-500/15 blur-3xl" />

      {/*
        Un seul portail pour tous les abonnés : l'établissement courant est
        affiché en permanence. Sans ça, rien ne distinguerait visuellement la
        session d'un lycée de celle d'un autre — et une saisie faite dans le
        mauvais établissement ne se voit qu'après coup.
      */}
      <div className="relative z-10 flex items-center gap-2 border-b border-white/10 px-5 py-4">
        <img
          src={branding?.logo_url || "/logo.png"}
          alt={branding?.nom || "Auditron X"}
          className="h-9 w-9 object-contain drop-shadow"
        />
        <div className="min-w-0">
          <div className="truncate text-base font-bold text-white">
            {branding?.nom_court || branding?.nom || "Auditron X"}
          </div>
          <button
            type="button"
            onClick={changerEtablissement}
            title="Changer d’établissement"
            className="text-xs text-brand-200 underline-offset-2 transition hover:text-white hover:underline"
          >
            {code || "Backoffice"} · changer
          </button>
        </div>
      </div>

      <nav className="sidebar-scroll relative z-10 flex-1 overflow-y-auto px-3 py-4">
        {NAV_SECTIONS.map((section) => (
          <div key={section.title} className="mb-5">
            <div className="mb-1 px-2 text-xs font-semibold uppercase tracking-wide text-brand-300">
              {section.title}
            </div>
            {section.links
              .filter((link) => !link.visible || link.visible(user))
              .map((link) => (
                <NavLink
                  key={link.to}
                  to={link.to}
                  end={link.end}
                  onClick={onNavigate}
                  className={({ isActive }) =>
                    `block rounded-lg px-2.5 py-2 text-sm font-medium transition ${
                      isActive
                        ? "bg-white/15 text-white shadow-sm backdrop-blur-sm"
                        : "text-brand-100 hover:bg-white/10 hover:text-white"
                    }`
                  }
                >
                  {link.label}
                </NavLink>
              ))}
          </div>
        ))}
      </nav>

      <div className="relative z-10 border-t border-white/10 px-4 py-3">
        <div className="mb-2 flex items-center gap-2">
          <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gold-500/90 text-sm font-semibold text-brand-950">
            {user?.name?.[0]?.toUpperCase() ?? "?"}
          </div>
          <div className="min-w-0">
            <div className="truncate text-sm font-medium text-white">
              {user?.name}
            </div>
            <div className="truncate text-xs text-brand-200">{user?.email}</div>
          </div>
        </div>
        <div className="mb-3 inline-block rounded-full bg-gold-500/20 px-2 py-0.5 text-xs font-medium text-gold-300">
          {user?.accreditation?.label ?? "Aucune accréditation"}
        </div>
        <button
          onClick={logout}
          className="w-full rounded-lg border border-white/15 py-1.5 text-sm font-medium text-brand-100 transition hover:bg-white/10 hover:text-white"
        >
          Déconnexion
        </button>
      </div>
    </div>
  );
}

export default function AppLayout() {
  const [mobileOpen, setMobileOpen] = useState(false);
  const location = useLocation();

  // Referme le tiroir mobile à chaque changement de route (navigation via NavLink
  // le fait déjà via onNavigate, mais aussi le retour navigateur/deep-link).
  useEffect(() => setMobileOpen(false), [location.pathname]);

  return (
    <div className="min-h-screen md:flex">
      <header className="sticky top-0 z-30 flex items-center justify-between border-b border-ink-100/80 bg-white/80 px-4 py-3 backdrop-blur md:hidden">
        <div className="flex items-center gap-2">
          <img
            src="/logo.png"
            alt="Auditron X"
            className="h-8 w-8 rounded-lg object-cover"
          />
          <span className="text-base font-extrabold tracking-[-0.06em] text-brand-900">
            Attiékoi
          </span>
        </div>
        <button
          onClick={() => setMobileOpen(true)}
          aria-label="Ouvrir le menu"
          className="rounded-lg p-2 text-ink-700 hover:bg-brand-100"
        >
          <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            className="h-6 w-6"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M4 6h16M4 12h16M4 18h16"
            />
          </svg>
        </button>
      </header>

      <aside className="hidden w-72 shrink-0 border-r border-brand-200/80 bg-white/75 shadow-[0_0_0_1px_rgba(91,62,142,0.04)] backdrop-blur-sm md:block">
        <div className="sticky top-0 h-screen">
          <SidebarContent />
        </div>
      </aside>

      {mobileOpen && (
        <div className="fixed inset-0 z-40 md:hidden">
          <div
            className="absolute inset-0 bg-brand-950/25"
            onClick={() => setMobileOpen(false)}
          />
          <div className="absolute inset-y-0 left-0 w-72 max-w-[85vw] shadow-xl">
            <button
              onClick={() => setMobileOpen(false)}
              aria-label="Fermer le menu"
              className="absolute top-3 right-3 z-20 rounded-lg p-2 text-brand-100 hover:bg-white/10 hover:text-white"
            >
              <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                className="h-6 w-6"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  d="M6 6l12 12M18 6L6 18"
                />
              </svg>
            </button>
            <SidebarContent onNavigate={() => setMobileOpen(false)} />
          </div>
        </div>
      )}

      <main className="relative min-w-0 flex-1 overflow-hidden p-4 sm:p-6 lg:p-8">
        <img
          src="/logo.png"
          alt=""
          aria-hidden="true"
          className="pointer-events-none absolute top-1/2 left-1/2 h-[32rem] w-[32rem] -translate-x-1/2 -translate-y-1/2 opacity-[0.04] select-none"
        />
        <div className="relative z-10">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
