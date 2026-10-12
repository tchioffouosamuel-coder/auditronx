import { Bluetooth, CalendarDays, CheckCircle2, Clock, ScanLine } from 'lucide-react';
import { QRCodeArt } from './QRCodeArt';

function MobileHeader({ title, subtitle }: { title: string; subtitle?: string }) {
  return (
    <div className="px-4 pt-8 pb-3 bg-brand-700 text-white">
      <div className="flex items-center gap-1.5 font-display text-sm font-bold mb-2">
        <img src="/logo.png" alt="" width={24} height={24} className="w-6 h-6 object-contain shrink-0" />
        Auditron-X
      </div>
      <div className="text-base font-bold leading-tight">{title}</div>
      {subtitle && <div className="text-[10px] text-white/80 mt-1">{subtitle}</div>}
    </div>
  );
}

export function MobileScreenHistory() {
  return (
    <div className="w-full h-full bg-ink-50 flex flex-col">
      <MobileHeader title="Mes présences" subtitle="Exemple · Octobre 2026" />
      <div className="flex-1 px-3 pt-3 space-y-2">
        <div className="text-[10px] text-ink-600 font-semibold mb-3">Arrivées et départs</div>
        {[
          { date: 'Lundi 05 octobre', arrival: '07:25', departure: '15:30', status: 'À l’heure' },
          { date: 'Vendredi 02 octobre', arrival: '07:28', departure: '15:20', status: 'À l’heure' },
          { date: 'Jeudi 01 octobre', arrival: '07:42', departure: '15:35', status: 'Retard : 12 min' },
          { date: 'Mercredi 30 septembre', arrival: '07:20', departure: '13:00', status: 'À l’heure' },
        ].map((entry) => (
          <div key={entry.date} className="p-3 rounded-lg border border-ink-200 bg-white">
            <div className="text-[11px] font-semibold text-ink-800 mb-1">{entry.date}</div>
            <div className="flex justify-between text-[9px] text-ink-500 mb-1"><span>Arrivée {entry.arrival}</span><span>Départ {entry.departure}</span></div>
            <div className={`text-[9px] font-semibold ${entry.status.startsWith('Retard') ? 'text-cyan-700' : 'text-brand-700'}`}>{entry.status}</div>
          </div>
        ))}
      </div>
      <div className="flex items-center justify-around py-3 bg-white border-t border-ink-200 text-ink-400">
        <ScanLine size={17} aria-hidden="true" /><Clock size={17} className="text-brand-700" aria-hidden="true" /><CalendarDays size={17} aria-hidden="true" />
      </div>
    </div>
  );
}

export function MobileScreenAttendance() {
  return (
    <div className="w-full h-full bg-ink-50 flex flex-col">
      <MobileHeader title="Ma journée" subtitle="Exemple · Enseignant A. · Lundi 05 octobre" />
      <div className="flex-1 px-3 pt-3 space-y-3">
        <div className="rounded-lg bg-brand-700 p-3 text-white">
          <div className="flex items-center gap-1.5 text-[10px] mb-1"><CheckCircle2 size={14} aria-hidden="true" /> Arrivée enregistrée</div>
          <div className="text-2xl font-bold">07:25</div>
          <div className="text-[9px] text-white/80 mt-1">Point de contrôle · Entrée principale</div>
        </div>
        <div className="flex items-center justify-center gap-2 rounded-lg bg-cyan-500 py-2.5 text-white text-[11px] font-semibold">
          <ScanLine size={16} aria-hidden="true" /> Scanner pour pointer
        </div>
        <div>
          <div className="text-[11px] font-semibold text-ink-700 mb-2">Mes cours du jour</div>
          {[
            { time: '08:00 – 09:00', subject: 'Mathématiques', group: '3e A · Salle 4' },
            { time: '10:00 – 11:00', subject: 'Mathématiques', group: '4e B · Salle 2' },
          ].map((course) => (
            <div key={course.time} className="py-2 border-b border-ink-200">
              <div className="text-[9px] text-brand-700 font-semibold">{course.time}</div>
              <div className="text-[11px] font-semibold text-ink-800">{course.subject}</div>
              <div className="text-[9px] text-ink-500">{course.group}</div>
            </div>
          ))}
        </div>
        <div className="text-[10px] text-ink-600 flex items-center gap-2"><Clock size={14} aria-hidden="true" /> Historique de mes présences</div>
      </div>
    </div>
  );
}

export function MobileScreenScan() {
  return (
    <div className="w-full h-full bg-ink-50 flex flex-col">
      <MobileHeader title="Pointer ma présence" subtitle="Exemple · Point de contrôle" />
      <div className="flex-1 flex flex-col items-center justify-center px-4 gap-4">
        <div className="text-[11px] font-semibold text-ink-700">Entrée principale</div>
        <div className="p-4 border-2 border-brand-500 rounded-lg bg-white">
          <QRCodeArt size={132} seed={7} />
        </div>
        <div className="flex items-center gap-1.5 text-[10px] text-brand-700 font-semibold"><Bluetooth size={15} aria-hidden="true" /> Borne locale connectée</div>
        <div className="flex items-center gap-1.5 text-[11px] text-brand-700"><CheckCircle2 size={16} aria-hidden="true" /> Pointage accepté par la borne</div>
        <div className="text-[9px] text-ink-500 text-center">Transmission locale par Bluetooth</div>
      </div>
    </div>
  );
}

export function MobileScreenAdminDashboard() {
  return (
    <div className="w-full h-full bg-ink-50 flex flex-col">
      <MobileHeader title="Espace direction" subtitle="Exemple · Présences de l’établissement" />
      <div className="flex-1 px-3 pt-3 space-y-3">
        <div className="grid grid-cols-2 gap-2">
          {[
            { label: 'Personnel attendu', value: '72', color: 'text-ink-800' },
            { label: 'Présents', value: '68', color: 'text-brand-700' },
            { label: 'Absents', value: '4', color: 'text-red-600' },
            { label: 'Retardataires', value: '3', color: 'text-cyan-700' },
          ].map((kpi) => (
            <div key={kpi.label} className="rounded-lg bg-white border border-ink-200 p-2.5">
              <div className="text-[9px] text-ink-500">{kpi.label}</div>
              <div className={`text-lg font-bold ${kpi.color}`}>{kpi.value}</div>
            </div>
          ))}
        </div>
        <div className="border-t border-ink-200 pt-3">
          <div className="text-[11px] font-semibold text-ink-700 mb-2">Assiduité par section</div>
          {[
            { section: 'Enseignement général', pct: 96 },
            { section: 'Enseignement technique', pct: 92 },
            { section: 'Administration', pct: 98 },
          ].map((entry) => (
            <div key={entry.section} className="mb-3">
              <div className="flex justify-between text-[9px] text-ink-600 mb-1"><span>{entry.section}</span><span>{entry.pct}%</span></div>
              <div className="h-1.5 rounded-full bg-ink-200"><div className="h-full rounded-full bg-brand-500" style={{ width: `${entry.pct}%` }} /></div>
            </div>
          ))}
        </div>
        <div className="text-[10px] text-ink-600 border-t border-ink-200 pt-3">Signalements et justificatifs</div>
        <div className="text-[10px] text-ink-600">Bilans d’assiduité et de retards</div>
      </div>
    </div>
  );
}
