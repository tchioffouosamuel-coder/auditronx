import { Reveal } from './Reveal';
import { SchoolPhoto } from './SchoolPhoto';
import { MobileCarousel } from './MobileCarousel';

const PROBLEMS = [
  {
    icon: 'M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M9 14h.01M15 14h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    title: 'Registres de présence dispersés',
    desc: 'Entre feuilles de présence et relevés manuels, reconstituer les arrivées et les départs du personnel demande du temps.',
  },
  {
    icon: 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
    title: 'Retards et absences à recouper',
    desc: 'La direction doit rapprocher les pointages, les emplois du temps et les justificatifs pour établir un bilan par enseignant.',
  },
  {
    icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
    title: 'Avancement des cours difficile à suivre',
    desc: 'Les contenus des séances et la progression des programmes restent dans des cahiers séparés, loin du suivi quotidien de la direction.',
  },
];

const SOLUTIONS = [
  {
    icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    title: 'Un pointage au sein de l’établissement',
    desc: 'Le scan QR et la borne locale enregistrent les arrivées et les départs. Les présences synchronisées sont consultables dans le portail.',
  },
  {
    icon: 'M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 3h18M3 3v18h18V3',
    title: 'Des bilans reliés aux emplois du temps',
    desc: 'Analysez l’assiduité par section et par période, appliquez vos seuils de retard et retrouvez les signalements associés.',
  },
  {
    icon: 'M4 6h16M4 10h16M4 14h16M4 18h16',
    title: 'Un suivi pédagogique partagé',
    desc: 'Le cahier de texte, la validation des cours et la fiche de progression rendent le travail réalisé consultable par les responsables habilités.',
  },
];

export function ProblemSolution() {
  return (
    <section id="context" className="bg-white overflow-hidden">
      <div className="context-photo-band relative min-h-[540px] lg:min-h-[500px] flex items-start lg:items-center">
        <SchoolPhoto photo="etablissement" alt="" background />
        <div className="context-photo-scrim absolute inset-0 pointer-events-none" />
        <div className="relative w-full max-w-7xl mx-auto px-4 md:px-6 py-16 lg:py-20">
          <Reveal className="max-w-lg">
            <div className="inline-block px-3 py-1 rounded-full bg-ink-100 text-ink-600 text-xs font-semibold mb-3">
              Le quotidien de l’établissement
            </div>
            <h2 className="text-3xl md:text-4xl font-bold text-ink-900 mb-3">
              Une journée scolaire, plusieurs suivis à réunir
            </h2>
            <p className="text-ink-700 text-base leading-relaxed">
              Présences du personnel, retards, justificatifs et cours réalisés :
              la direction a besoin d’une vision commune pour piloter l’établissement.
            </p>
          </Reveal>
        </div>
      </div>
      <div className="max-w-7xl mx-auto px-4 md:px-6 py-10 md:py-28">
        {/* Problems */}
        <MobileCarousel label="Difficultés du suivi scolaire" desktopClassName="grid md:grid-cols-3 gap-5" className="mb-4 md:mb-16">
          {PROBLEMS.map((p, i) => (
            <Reveal key={p.title} delay={i * 100}>
              <div className="relative h-full p-6 rounded-2xl border border-ink-200 bg-ink-50/50 hover:bg-ink-50 transition-colors">
                <div className="w-11 h-11 rounded-xl bg-red-50 flex items-center justify-center mb-4">
                  <svg className="w-5 h-5 text-red-500" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d={p.icon} />
                  </svg>
                </div>
                <h3 className="font-bold text-ink-900 mb-2 text-sm">{p.title}</h3>
                <p className="text-sm text-ink-500 leading-relaxed">{p.desc}</p>
              </div>
            </Reveal>
          ))}
        </MobileCarousel>

        <div className="md:hidden text-center text-sm font-bold text-brand-700 mb-1">
          La solution Auditron
        </div>

        {/* Divider with arrow */}
        <Reveal className="hidden md:flex flex-col items-center mb-16">
          <div className="w-12 h-12 rounded-full bg-gradient-to-br from-brand-500 to-cyan-500 flex items-center justify-center shadow-lg shadow-brand-500/20">
            <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
          </div>
          <div className="text-center mt-3">
            <span className="text-sm font-bold text-brand-600">La solution Auditron</span>
          </div>
        </Reveal>

        {/* Solutions */}
        <MobileCarousel label="Solutions Auditron-X" desktopClassName="grid md:grid-cols-3 gap-5">
          {SOLUTIONS.map((s, i) => (
            <Reveal key={s.title} delay={i * 100}>
              <div className="relative h-full p-6 rounded-2xl border border-brand-200 bg-gradient-to-br from-brand-50/60 to-white hover:border-brand-300 transition-colors group">
                <div className="w-11 h-11 rounded-xl bg-gradient-to-br from-brand-500 to-cyan-500 flex items-center justify-center mb-4 group-hover:scale-105 transition-transform">
                  <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d={s.icon} />
                  </svg>
                </div>
                <h3 className="font-bold text-ink-900 mb-2 text-sm">{s.title}</h3>
                <p className="text-sm text-ink-600 leading-relaxed">{s.desc}</p>
              </div>
            </Reveal>
          ))}
        </MobileCarousel>
      </div>
    </section>
  );
}
