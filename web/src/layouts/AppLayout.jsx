import { NavLink, Outlet } from 'react-router-dom'
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

export default function AppLayout() {
  const { user, logout } = useAuth()

  return (
    <div className="flex min-h-screen">
      <aside className="flex w-64 shrink-0 flex-col border-r border-ink-100 bg-white">
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
                  className={({ isActive }) =>
                    `block rounded-lg px-2.5 py-1.5 text-sm font-medium transition ${
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
          <div className="mb-2 text-sm font-medium text-ink-900">{user?.name}</div>
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
      </aside>

      <main className="flex-1 overflow-y-auto bg-ink-50 p-8">
        <Outlet />
      </main>
    </div>
  )
}
