import { BookOpen, CheckCircle2, Users } from 'lucide-react';

export function CahierTexteMockup() {
  return (
    <div className="min-h-[380px] p-4 md:p-5">
      <div className="flex items-center gap-2 text-brand-700 mb-1"><BookOpen size={16} aria-hidden="true" /><span className="text-sm font-bold">Cahier de texte</span></div>
      <div className="text-[10px] text-ink-500 mb-4">Exemple de séance · Année scolaire 2026–2027</div>
      <div className="grid grid-cols-2 gap-3 mb-3">
        {[
          { label: 'Classe', value: '3e A' },
          { label: 'Discipline', value: 'Mathématiques' },
          { label: 'Date de séance', value: '05 octobre 2026' },
          { label: 'Créneau', value: '08:00 – 09:00' },
        ].map((field) => (
          <div key={field.label}>
            <div className="text-[10px] font-semibold text-ink-600 mb-1">{field.label}</div>
            <div className="px-2 py-2 rounded-lg border border-ink-200 text-[11px] text-ink-800">{field.value}</div>
          </div>
        ))}
      </div>
      <div className="text-[10px] font-semibold text-ink-600 mb-1">Contenu de la séance</div>
      <div className="p-3 rounded-lg border border-brand-200 bg-brand-50/30 text-[11px] text-ink-700 leading-relaxed mb-3">
        Résolution d’équations du premier degré. Exercices d’application et
        correction collective. Travail à poursuivre : exercices 4 à 6.
      </div>
      <div className="text-[10px] text-ink-500 mb-3">Référence au programme : Algèbre / Équations</div>
      <div className="flex items-center gap-2 border-t border-ink-200 pt-3 text-[11px] text-brand-700 font-semibold">
        <CheckCircle2 size={14} aria-hidden="true" /> Cours réalisé et validé
      </div>
    </div>
  );
}

export function PersonnelMockup() {
  return (
    <div className="min-h-[380px] p-4 md:p-5">
      <div className="flex items-center gap-2 text-brand-700 mb-1"><Users size={16} aria-hidden="true" /><span className="text-sm font-bold">Personnel de l’établissement</span></div>
      <div className="text-[10px] text-ink-500 mb-4">Exemple de registre · Enseignants et administration</div>
      <div className="flex flex-wrap gap-2 mb-4 text-[10px] text-ink-600">
        <span className="px-2 py-1.5 bg-brand-50 rounded-md text-brand-700">Toutes les sections</span>
        <span className="px-2 py-1.5 bg-ink-50 rounded-md">Tous les rôles</span>
      </div>
      <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_54px] gap-2 text-[9px] font-semibold text-ink-500 border-b border-ink-200 pb-2">
        <span>PERSONNEL</span><span>SECTION</span><span>STATUT</span>
      </div>
      {[
        { name: 'Enseignant A.', role: 'Enseignant', section: 'Enseignement général', status: 'Actif' },
        { name: 'Enseignante B.', role: 'Enseignante', section: 'Enseignement général', status: 'Active' },
        { name: 'Enseignant C.', role: 'Chef de section', section: 'Enseignement technique', status: 'Actif' },
        { name: 'Personnel D.', role: 'Secrétariat', section: 'Administration', status: 'Actif' },
        { name: 'Enseignante E.', role: 'Enseignante', section: 'Enseignement technique', status: 'Active' },
      ].map((row) => (
        <div key={row.name} className="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_54px] gap-2 py-3 border-b border-ink-100 items-center text-[10px]">
          <div><div className="font-semibold text-ink-800">{row.name}</div><div className="text-[9px] text-ink-500">{row.role}</div></div>
          <div className="text-ink-600">{row.section}</div>
          <div className="text-brand-700 font-semibold">{row.status}</div>
        </div>
      ))}
    </div>
  );
}
