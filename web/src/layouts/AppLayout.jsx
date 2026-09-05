import { useEffect, useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

const NAV_SECTIONS = [
  {
    title: 'Vue d’ensemble',
    links: [{ to: '/', label: 'Tableau de bord', end: true }],
  },
  {
    title: 'Personnel & structure',
    links: [
      { to: '/personnel', label: 'Personnel' },
      { to: '/classes', label: 'Classes' },
      { to: '/disciplines', label: 'Disciplines' },
      { to: '/emplois', label: 'Emplois du temps' },
      { to: '/accreditations', label: 'Accréditations' },
    ],
  },
  {
    title: 'Présence',
    links: [
      { to: '/retards', label: 'Retards & bilans' },
      { to: '/assiduite', label: 'Assiduité & rapports' },
      { to: '/correcteur', label: 'Correcteur de présences' },
      { to: '/validation', label: 'Validation des présences' },
      { to: '/signalements', label: 'Signalements' },
      { to: '/feries', label: 'Jours fériés' },
      { to: '/alertes', label: 'Alertes d’absences' },
    ],
  },
  {
    title: 'Pédagogie',
    links: [
      { to: '/cahier-texte', label: 'Cahier de texte' },
      { to: '/fiche-progression', label: 'Fiche de progression' },
    ],
  },
  {
    title: 'Administration',
    links: [{ to: '/appareils', label: 'Appareils & points d’accès' }],
  },
]

function SidebarContent({ onNavigate }) {
  const { user, logout } = useAuth()

  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center gap-2 border-b border-ink-100 px-5 py-4">
        <img src="/logo.png" alt="Auditron X" className="h-9 w-9" />
        <div>
          <div className="text-base font-bold text-brand-900">Auditron X</div>
          <div className="text-xs text-ink-500">Backoffice</div>
        </div>
      </div>

      <nav className="flex-1 overflow-y-auto px-3 py-4">
        {NAV_SECTIONS.map((section) => (
          <div key={section.title} className="mb-5">
            <div className="mb-1 px-2 text-xs font-semibold uppercase tracking-wide text-ink-300">
              {section.title}
            </div>
            {section.links.map((link) => (
              <NavLink
                key={link.to}
                to={link.to}
                end={link.end}
                onClick={onNavigate}
                className={({ isActive }) =>
                  `block rounded-lg px-2.5 py-2 text-sm font-medium transition ${
                    isActive
                      ? 'bg-brand-700 text-white shadow-sm'
                      : 'text-ink-700 hover:bg-brand-50 hover:text-brand-800'
                  }`
                }
              >
                {link.label}
              </NavLink>
            ))}
          </div>
        ))}
      </nav>

      <div className="border-t border-ink-100 px-4 py-3">
        <div className="mb-2 flex items-center gap-2">
          <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-800">
            {user?.name?.[0]?.toUpperCase() ?? '?'}
          </div>
          <div className="min-w-0">
            <div className="truncate text-sm font-medium text-ink-900">{user?.name}</div>
            <div className="truncate text-xs text-ink-500">{user?.email}</div>
          </div>
        </div>
        <div className="mb-3 inline-block rounded-full bg-gold-100 px-2 py-0.5 text-xs font-medium text-gold-700">
          {user?.accreditation?.label ?? 'Aucune accréditation'}
        </div>
        <button
          onClick={logout}
          className="w-full rounded-lg border border-ink-100 py-1.5 text-sm font-medium text-ink-700 transition hover:bg-ink-50"
        >
          Déconnexion
        </button>
      </div>
    </div>
  )
}

export default function AppLayout() {
  const [mobileOpen, setMobileOpen] = useState(false)
  const location = useLocation()

  // Referme le tiroir mobile à chaque changement de route (navigation via NavLink
  // le fait déjà via onNavigate, mais aussi le retour navigateur/deep-link).
  useEffect(() => setMobileOpen(false), [location.pathname])

  return (
    <div className="min-h-screen bg-ink-50 md:flex">
      {/* Barre mobile : logo + bouton menu, cachée en desktop où la sidebar est toujours visible. */}
      <header className="sticky top-0 z-30 flex items-center justify-between border-b border-ink-100 bg-white px-4 py-3 md:hidden">
        <div className="flex items-center gap-2">
          <img src="/logo.png" alt="Auditron X" className="h-8 w-8" />
          <span className="text-base font-bold text-brand-900">Auditron X</span>
        </div>
        <button
          onClick={() => setMobileOpen(true)}
          aria-label="Ouvrir le menu"
          className="rounded-lg p-2 text-ink-700 hover:bg-ink-50"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-6 w-6">
            <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
      </header>

      {/* Sidebar desktop : toujours visible à partir de md. */}
      <aside className="hidden w-64 shrink-0 border-r border-ink-100 bg-white md:block">
        <div className="sticky top-0 h-screen">
          <SidebarContent />
        </div>
      </aside>

      {/* Tiroir mobile : overlay + panneau glissant, uniquement sous md. */}
      {mobileOpen && (
        <div className="fixed inset-0 z-40 md:hidden">
          <div className="absolute inset-0 bg-black/30" onClick={() => setMobileOpen(false)} />
          <div className="absolute inset-y-0 left-0 w-72 max-w-[85vw] bg-white shadow-xl">
            <div className="flex justify-end px-3 pt-3">
              <button
                onClick={() => setMobileOpen(false)}
                aria-label="Fermer le menu"
                className="rounded-lg p-2 text-ink-700 hover:bg-ink-50"
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="h-6 w-6">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 6l12 12M18 6L6 18" />
                </svg>
              </button>
            </div>
            <SidebarContent onNavigate={() => setMobileOpen(false)} />
          </div>
        </div>
      )}

      <main className="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
        <Outlet />
      </main>
    </div>
  )
}
