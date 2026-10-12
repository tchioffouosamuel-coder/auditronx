import { Reveal } from './Reveal';
import { SchoolPhoto } from './SchoolPhoto';

const BENEFITS = [
  {
    icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    title: 'Moins de ressaisies',
    desc: 'Retrouvez les pointages, les emplois du temps et les justificatifs dans un même espace pour préparer vos bilans.',
  },
  {
    icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10',
    title: 'Une vue par section',
    desc: 'Consultez l’assiduité et la ponctualité par enseignant, par section et par période pour repérer les situations à examiner.',
  },
  {
    icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    title: 'Meilleure traçabilité',
    desc: 'Les procurations et corrections conservent leur auteur et leur motif. La direction peut retrouver l’origine des interventions.',
  },
  {
    icon: 'M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h8a2 2 0 002-2v-3a2 2 0 00-2-2h-3m-3 0V7m6 10V5a2 2 0 00-2-2h-2',
    title: 'Un suivi des cours partagé',
    desc: 'Enseignants et responsables consultent les séances réalisées et la progression du programme selon leurs droits d’accès.',
  },
];

const AUDIENCES = [
  { icon: 'M3 21V3h18v18M7 7h2m6 0h2M7 11h2m6 0h2M10 21v-6h4v6', label: 'Direction' },
  { icon: 'M3 4h8v16H3zM13 4h8v16h-8M7 8h1m-1 4h1m9-4h1m-1 4h1', label: 'Censeurs' },
  { icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM5 21a7 7 0 0114 0', label: 'Enseignants' },
  { icon: 'M8 4h8v4H8zM4 8h16v13H4zM8 12h8m-8 4h5', label: 'Administration' },
];

export function Benefits() {
  return (
    <section id="benefits" className="relative py-20 md:py-28 bg-ink-50/60 overflow-hidden">
      <div className="max-w-7xl mx-auto px-4 md:px-6">
        <Reveal className="mb-14">
          <div className="grid lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] gap-8 lg:gap-12 items-center">
            <div>
              <div className="inline-block px-3 py-1 rounded-full bg-audit-50 text-audit-700 text-xs font-semibold mb-3">
                Avantages
              </div>
              <h2 className="text-3xl md:text-4xl font-bold text-ink-900 tracking-tight mb-3">
                Le temps de classe au cœur du suivi
              </h2>
              <p className="text-ink-500 text-base leading-relaxed">
                Suivre l’assiduité du personnel et l’avancement des cours aide
                la direction à mieux accompagner la continuité des enseignements.
                Auditron-X réunit les informations nécessaires à ce suivi.
              </p>
            </div>
            <figure>
              <SchoolPhoto photo="lecture" alt="Une élève assise sur un banc lit un livre ouvert dans une salle de classe." />
              <figcaption className="mt-3 text-sm text-ink-500 leading-relaxed">
                Derrière le suivi du personnel et des cours, le quotidien des élèves.
              </figcaption>
            </figure>
          </div>
        </Reveal>

        <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-14">
          {BENEFITS.map((b, i) => (
            <Reveal key={b.title} delay={(i % 4) * 80}>
              <div className="h-full p-6 rounded-2xl bg-white border border-ink-200 hover:shadow-lg transition-shadow group">
                <div className="w-12 h-12 rounded-xl bg-audit-50 flex items-center justify-center mb-4 group-hover:bg-audit-100 transition-colors">
                  <svg className="w-6 h-6 text-audit-600" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d={b.icon} />
                  </svg>
                </div>
                <h3 className="font-bold text-ink-900 mb-2 text-sm">{b.title}</h3>
                <p className="text-sm text-ink-500 leading-relaxed">{b.desc}</p>
              </div>
            </Reveal>
          ))}
        </div>

        {/* Audience row */}
        <Reveal>
          <div className="p-6 md:p-8 rounded-2xl bg-gradient-to-r from-brand-600 to-brand-700 text-white">
            <div className="flex flex-col md:flex-row items-center gap-6 md:gap-10">
              <div className="text-center md:text-left md:w-64 flex-shrink-0">
                <h3 className="font-bold text-lg mb-1">Chaque rôle a son espace</h3>
                <p className="text-sm text-white/70">Des accès adaptés aux responsabilités dans l’établissement.</p>
              </div>
              <div className="grid grid-cols-2 xl:grid-cols-4 gap-3 flex-1 w-full">
                {AUDIENCES.map((a) => (
                  <div key={a.label} className="flex items-center gap-2.5 px-3 py-3 rounded-xl bg-white/10 backdrop-blur-sm border border-white/10 hover:bg-white/15 transition-colors">
                    <svg className="w-5 h-5 text-cyan-300 flex-shrink-0" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" d={a.icon} />
                    </svg>
                    <span className="text-xs font-semibold text-white">{a.label}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  );
}
