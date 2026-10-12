import { BookOpen, CalendarDays, Clock, LayoutDashboard, Users } from 'lucide-react';

// Données d'illustration du suivi d'un établissement.
export function DashboardMockup() {
  return (
    <div className="flex min-h-[420px]">
      <div className="hidden md:flex flex-col w-40 shrink-0 bg-ink-50 border-r border-ink-200 p-3 gap-1">
        <div className="flex items-center gap-1.5 font-display font-bold text-sm text-brand-700 px-2 py-3 mb-2">
          <img src="/logo.png" alt="" width={24} height={24} className="w-6 h-6 object-contain shrink-0" />
          Auditron-X
        </div>
        {[
          { Icon: LayoutDashboard, label: 'Tableau de bord' },
          { Icon: Users, label: 'Personnel' },
          { Icon: CalendarDays, label: 'Emplois du temps' },
          { Icon: Clock, label: 'Assiduité et retards' },
          { Icon: BookOpen, label: 'Cahier de texte' },
        ].map(({ Icon, label }, i) => (
          <div key={label} className={`flex items-center gap-2 px-2 py-2 rounded-lg text-[10px] font-medium ${i === 0 ? 'bg-brand-50 text-brand-700' : 'text-ink-500'}`}>
            <Icon size={14} className="shrink-0" aria-hidden="true" />
            {label}
          </div>
        ))}
        <div className="mt-auto border-t border-ink-200 pt-3 px-2">
          <div className="text-xs font-semibold text-ink-700">Espace direction</div>
          <div className="text-[10px] text-ink-400">Établissement de démonstration</div>
        </div>
      </div>
      <div className="flex-1 min-w-0 p-4 md:p-5">
        <div className="flex items-start justify-between gap-2 mb-4">
          <div>
            <div className="text-sm font-bold text-ink-900">Présences du jour</div>
            <div className="text-[10px] text-ink-500">Exemple d’établissement · Lundi 5 octobre 2026</div>
          </div>
          <div className="shrink-0 px-2 py-1.5 rounded-lg bg-brand-50 text-brand-700 text-[10px] font-semibold">Bilan PDF</div>
        </div>
        <div className="grid grid-cols-3 gap-2 md:gap-3 mb-4">
          {[
            { label: 'Présents', value: '68', detail: 'Sur 72 attendus', color: 'text-brand-700' },
            { label: 'Absents', value: '4', detail: 'À examiner', color: 'text-red-600' },
            { label: 'Retardataires', value: '3', detail: 'Parmi les présents', color: 'text-cyan-700' },
          ].map((kpi) => (
            <div key={kpi.label} className="rounded-lg border border-ink-200 bg-white p-2 md:p-3">
              <div className="text-[9px] text-ink-500 font-medium mb-1">{kpi.label}</div>
              <div className={`text-lg font-bold ${kpi.color}`}>{kpi.value}</div>
              <div className="text-[8px] text-ink-500">{kpi.detail}</div>
            </div>
          ))}
        </div>
        <div className="grid grid-cols-1 xl:grid-cols-3 gap-3 mb-4">
          <div className="xl:col-span-2 rounded-lg border border-ink-200 p-3">
            <div className="flex flex-wrap justify-between gap-2 mb-3">
              <div className="text-[11px] font-semibold text-ink-700">Présences de la semaine</div>
              <div className="flex items-center gap-2 text-[8px] text-ink-500">
                <span className="flex items-center gap-1"><span className="w-2 h-2 bg-brand-500" /> Présents</span>
                <span className="flex items-center gap-1"><span className="w-2 h-2 bg-cyan-400" /> Retards</span>
              </div>
            </div>
            <div className="flex items-end gap-3 h-28">
              {[
                { day: 'Lun', present: 94, late: 4 },
                { day: 'Mar', present: 92, late: 7 },
                { day: 'Mer', present: 96, late: 3 },
                { day: 'Jeu', present: 90, late: 8 },
                { day: 'Ven', present: 95, late: 4 },
              ].map((day) => (
                <div key={day.day} className="flex-1 h-full flex flex-col items-center gap-1">
                  <div className="w-full flex-1 flex items-end justify-center gap-1">
                    <div className="w-1/3 rounded-t-sm bg-brand-500/80" style={{ height: `${day.present}%` }} />
                    <div className="w-1/3 rounded-t-sm bg-cyan-400" style={{ height: `${day.late}%` }} />
                  </div>
                  <div className="text-[8px] text-ink-500">{day.day}</div>
                </div>
              ))}
            </div>
          </div>
          <div className="rounded-lg border border-ink-200 p-3">
            <div className="text-[11px] font-semibold text-ink-700 mb-3">Assiduité par section</div>
            {[
              { label: 'Enseignement général', pct: 96 },
              { label: 'Enseignement technique', pct: 92 },
              { label: 'Administration', pct: 98 },
            ].map((section) => (
              <div key={section.label} className="mb-3">
                <div className="flex justify-between gap-1 text-[8px] text-ink-600 mb-1"><span>{section.label}</span><span>{section.pct}%</span></div>
                <div className="h-1.5 rounded-full bg-ink-100"><div className="h-full rounded-full bg-brand-500" style={{ width: `${section.pct}%` }} /></div>
              </div>
            ))}
          </div>
        </div>
        <div className="border-t border-ink-200 pt-3">
          <div className="text-[11px] font-semibold text-ink-700 mb-2">Derniers pointages</div>
          {[
            { name: 'Enseignant A.', section: 'Mathématiques', time: '07:25', status: 'Présent' },
            { name: 'Enseignante B.', section: 'Français', time: '07:28', status: 'Présente' },
            { name: 'Enseignant C.', section: 'Sciences', time: '07:42', status: 'Retard' },
          ].map((row) => (
            <div key={row.name} className="grid grid-cols-[1fr_auto_auto] gap-3 py-1.5 text-[9px] border-b border-ink-100 items-center">
              <div className="min-w-0"><span className="font-semibold text-ink-700">{row.name}</span><span className="text-ink-500 hidden sm:inline"> · {row.section}</span></div>
              <span className="text-ink-500">{row.time}</span>
              <span className={row.status === 'Retard' ? 'text-cyan-700' : 'text-brand-700'}>{row.status}</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
