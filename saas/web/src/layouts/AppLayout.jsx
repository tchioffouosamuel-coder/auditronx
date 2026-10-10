import { useEffect, useMemo, useState } from "react";
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

/**
 * Chaque lien porte une icône Material Symbols : la police était déjà chargée
 * mais la navigation restait une liste de vingt libellés texte, où l'on relit
 * tout pour retrouver un écran. L'icône donne un point d'ancrage visuel stable
 * d'une session à l'autre.
 */
const NAV_SECTIONS = [
  {
    title: "Vue d’ensemble",
    links: [
      { to: "/", label: "Tableau de bord", icon: "space_dashboard", end: true },
    ],
  },
  {
    title: "Personnel & structure",
    links: [
      { to: "/personnel", label: "Personnel", icon: "badge" },
      { to: "/classes", label: "Classes", icon: "meeting_room" },
      { to: "/disciplines", label: "Disciplines", icon: "menu_book" },
      { to: "/emplois", label: "Emplois du temps", icon: "calendar_month" },
      {
        to: "/accreditations",
        label: "Accréditations",
        icon: "key",
        visible: aAccesTotal,
      },
    ],
  },
  {
    title: "Présence",
    links: [
      { to: "/retards", label: "Retards & bilans", icon: "running_with_errors" },
      { to: "/assiduite", label: "Assiduité & rapports", icon: "fact_check" },
      {
        to: "/validation",
        label: "Validation des présences",
        icon: "how_to_reg",
      },
      { to: "/signalements", label: "Signalements", icon: "flag" },
      { to: "/feries", label: "Jours fériés", icon: "event_busy" },
      { to: "/alertes", label: "Alertes d’absences", icon: "notifications" },
    ],
  },
  {
    title: "Pédagogie",
    links: [
      { to: "/cahier-texte", label: "Cahier de texte", icon: "edit_note" },
      {
        to: "/fiche-progression",
        label: "Fiche de progression",
        icon: "trending_up",
      },
    ],
  },
  {
    title: "Administration",
    links: [
      {
        to: "/appareils",
        label: "Appareils & points d’accès",
        icon: "qr_code_scanner",
      },
      { to: "/moniteur-bornes", label: "Moniteur des bornes", icon: "sensors" },
      { to: "/firmwares", label: "Mises à jour firmware", icon: "memory" },
      {
        to: "/journal-audit",
        label: "Journal d’audit",
        icon: "history",
        visible: aAccesTotal,
      },
      { to: "/configuration", label: "Configuration", icon: "settings" },
    ],
  },
];

const TOUS_LES_LIENS = NAV_SECTIONS.flatMap((section) => section.links);

/**
 * Libellé de l'écran courant, pour l'en-tête mobile. On prend la
 * correspondance la plus longue : sans ça « / » gagnerait sur « /personnel »,
 * et la barre afficherait « Tableau de bord » partout.
 */
function libelleCourant(pathname) {
  const correspondances = TOUS_LES_LIENS.filter((lien) =>
    lien.end ? pathname === lien.to : pathname.startsWith(lien.to),
  );

  if (correspondances.length === 0) return null;

  return correspondances.reduce((a, b) => (b.to.length > a.to.length ? b : a))
    .label;
}

function Icone({ nom, className = "" }) {
  return (
    <span
      aria-hidden="true"
      className={`material-symbols-rounded text-[20px] ${className}`}
    >
      {nom}
    </span>
  );
}

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
      <div className="relative z-10 flex items-center gap-3 border-b border-white/10 px-5 py-4">
        <img
          src={branding?.logo_url || "/logo.png"}
          alt=""
          className="h-10 w-10 shrink-0 rounded-xl bg-white/10 object-contain p-1 drop-shadow"
        />
        <div className="min-w-0">
          <div className="truncate text-base font-bold text-white">
            {branding?.nom_court || branding?.nom || "Auditron"}
          </div>
          <button
            type="button"
            onClick={changerEtablissement}
            title="Changer d’établissement"
            className="group inline-flex items-center gap-1 rounded text-xs text-brand-200 transition hover:text-white"
          >
            <span className="truncate">{code || "Backoffice"}</span>
            <Icone
              nom="swap_horiz"
              className="text-[14px] opacity-60 transition group-hover:opacity-100"
            />
          </button>
        </div>
      </div>

      <nav
        aria-label="Navigation principale"
        className="sidebar-scroll relative z-10 flex-1 overflow-y-auto px-3 py-4"
      >
        {NAV_SECTIONS.map((section) => {
          const liens = section.links.filter(
            (link) => !link.visible || link.visible(user),
          );

          /* Une section dont tous les liens sont masqués ne doit pas laisser
             son titre orphelin (cas des accréditations restreintes). */
          if (liens.length === 0) return null;

          return (
            <div key={section.title} className="mb-5">
              <div className="mb-1.5 px-2.5 text-[11px] font-bold uppercase tracking-wider text-brand-300/90">
                {section.title}
              </div>
              <ul className="space-y-0.5">
                {liens.map((link) => (
                  <li key={link.to}>
                    <NavLink
                      to={link.to}
                      end={link.end}
                      onClick={onNavigate}
                      className={({ isActive }) =>
                        `group relative flex items-center gap-2.5 rounded-xl py-2 pr-2.5 pl-3 text-sm font-medium transition ${
                          isActive
                            ? "bg-white/15 text-white shadow-sm"
                            : "text-brand-100 hover:bg-white/10 hover:text-white"
                        }`
                      }
                    >
                      {({ isActive }) => (
                        <>
                          {/* Liseré d'accent : l'état actif reste lisible même
                              quand le fond translucide se confond avec le
                              dégradé de la sidebar. */}
                          <span
                            aria-hidden="true"
                            className={`absolute top-1/2 left-0 h-5 w-[3px] -translate-y-1/2 rounded-r-full bg-gold-500 transition-opacity ${
                              isActive ? "opacity-100" : "opacity-0"
                            }`}
                          />
                          <Icone
                            nom={link.icon}
                            className={
                              isActive
                                ? "text-gold-300"
                                : "text-brand-300 transition group-hover:text-brand-100"
                            }
                          />
                          <span className="min-w-0 flex-1 truncate">
                            {link.label}
                          </span>
                        </>
                      )}
                    </NavLink>
                  </li>
                ))}
              </ul>
            </div>
          );
        })}
      </nav>

      <div className="relative z-10 border-t border-white/10 px-4 py-3">
        <div className="mb-2 flex items-center gap-2.5">
          <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gold-500/90 text-sm font-bold text-brand-950">
            {user?.name?.[0]?.toUpperCase() ?? "?"}
          </div>
          <div className="min-w-0">
            <div className="truncate text-sm font-semibold text-white">
              {user?.name}
            </div>
            <div className="truncate text-xs text-brand-200" title={user?.email}>
              {user?.email}
            </div>
          </div>
        </div>
        <div className="mb-3 inline-flex max-w-full items-center gap-1 rounded-full bg-gold-500/20 px-2 py-0.5 text-xs font-medium text-gold-300">
          <Icone nom="verified_user" className="text-[14px]" />
          <span className="truncate">
            {user?.accreditation?.label ?? "Aucune accréditation"}
          </span>
        </div>
        <button
          onClick={logout}
          className="flex w-full items-center justify-center gap-1.5 rounded-xl border border-white/15 py-2 text-sm font-semibold text-brand-100 transition hover:border-white/30 hover:bg-white/10 hover:text-white"
        >
          <Icone nom="logout" className="text-[18px]" />
          Déconnexion
        </button>
      </div>
    </div>
  );
}

export default function AppLayout() {
  const [mobileOpen, setMobileOpen] = useState(false);
  const location = useLocation();
  const titreCourant = useMemo(
    () => libelleCourant(location.pathname),
    [location.pathname],
  );

  // Referme le tiroir mobile à chaque changement de route (navigation via NavLink
  // le fait déjà via onNavigate, mais aussi le retour navigateur/deep-link).
  useEffect(() => setMobileOpen(false), [location.pathname]);

  // Échap referme le tiroir, et on bloque le défilement de la page derrière
  // lui : sans ça, un glissement sur l'overlay fait défiler le contenu masqué.
  useEffect(() => {
    if (!mobileOpen) return;

    function onKeyDown(event) {
      if (event.key === "Escape") setMobileOpen(false);
    }

    const overflowInitial = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    document.addEventListener("keydown", onKeyDown);

    return () => {
      document.body.style.overflow = overflowInitial;
      document.removeEventListener("keydown", onKeyDown);
    };
  }, [mobileOpen]);

  return (
    <div className="min-h-screen md:flex">
      {/* Accès direct au contenu : la sidebar compte une vingtaine de liens à
          traverser avant d'atteindre la page au clavier. */}
      <a
        href="#contenu-principal"
        className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-xl focus:bg-brand-900 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white"
      >
        Aller au contenu
      </a>

      <header className="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-brand-200/70 bg-white/85 px-4 py-2.5 backdrop-blur-md md:hidden">
        <div className="flex min-w-0 items-center gap-2.5">
          <img
            src="/logo.png"
            alt=""
            className="h-8 w-8 shrink-0 rounded-lg object-cover"
          />
          <div className="min-w-0">
            <div className="truncate text-sm font-extrabold tracking-tight text-brand-900">
              Auditron
            </div>
            {/* Sur mobile la sidebar est masquée : sans ce rappel, rien
                n'indique sur quel écran on se trouve. */}
            {titreCourant && (
              <div className="truncate text-xs text-ink-500">
                {titreCourant}
              </div>
            )}
          </div>
        </div>
        <button
          onClick={() => setMobileOpen(true)}
          aria-label="Ouvrir le menu"
          aria-expanded={mobileOpen}
          className="shrink-0 rounded-xl p-2 text-ink-700 transition hover:bg-brand-100"
        >
          <Icone nom="menu" className="text-[24px]" />
        </button>
      </header>

      <aside className="hidden w-72 shrink-0 border-r border-brand-900/20 md:block">
        <div className="sticky top-0 h-screen">
          <SidebarContent />
        </div>
      </aside>

      {mobileOpen && (
        <div className="fixed inset-0 z-40 md:hidden">
          <div
            className="animate-fade-in absolute inset-0 bg-brand-950/40 backdrop-blur-sm"
            onClick={() => setMobileOpen(false)}
          />
          <div
            role="dialog"
            aria-modal="true"
            aria-label="Navigation principale"
            className="animate-drawer-in absolute inset-y-0 left-0 w-72 max-w-[85vw] shadow-2xl"
          >
            <button
              onClick={() => setMobileOpen(false)}
              aria-label="Fermer le menu"
              className="absolute top-3 right-3 z-20 rounded-xl p-2 text-brand-100 transition hover:bg-white/10 hover:text-white"
            >
              <Icone nom="close" className="text-[22px]" />
            </button>
            <SidebarContent onNavigate={() => setMobileOpen(false)} />
          </div>
        </div>
      )}

      <main
        id="contenu-principal"
        className="relative min-w-0 flex-1 overflow-hidden p-4 sm:p-6 lg:p-8"
      >
        <img
          src="/logo.png"
          alt=""
          aria-hidden="true"
          className="pointer-events-none absolute top-1/2 left-1/2 h-[32rem] w-[32rem] -translate-x-1/2 -translate-y-1/2 opacity-[0.04] select-none"
        />
        <div className="relative z-10 mx-auto max-w-7xl">
          <Outlet />
        </div>
      </main>
    </div>
  );
}
