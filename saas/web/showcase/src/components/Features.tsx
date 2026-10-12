import { Reveal } from './Reveal';
import { SchoolPhoto } from './SchoolPhoto';
import { MobileCarousel } from './MobileCarousel';

const FEATURES = [
  {
    icon: 'M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h.01M4 16h.01M4 8h.01M4 4h.01',
    title: 'Pointage QR et borne locale',
    desc: 'Le personnel scanne le QR du point de contrôle. Le téléphone transmet le pointage à la borne de l’établissement par Bluetooth.',
    color: 'from-brand-500 to-brand-600',
  },
  {
    icon: 'M4 6h16M4 10h16M4 14h16M4 18h16',
    title: 'Personnel et emplois du temps',
    desc: 'Organisez les fiches du personnel, les sections, les classes et les disciplines. Planifiez les cours et repérez les conflits de créneaux.',
    color: 'from-cyan-500 to-cyan-600',
  },
  {
    icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    title: 'Assiduité, retards et absences',
    desc: 'Consultez les présences du jour, les bilans par section et les retards. Retrouvez les alertes d’absences répétées et les justificatifs.',
    color: 'from-audit-500 to-audit-600',
  },
  {
    icon: 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.836 8.5a2.617 2.617 0 01-2.147-1.5',
    title: 'Cahier de texte et progression',
    desc: 'Consignez le contenu des séances, validez les cours réalisés et suivez l’avancement du programme par classe et par discipline.',
    color: 'from-brand-500 to-cyan-500',
  },
  {
    icon: 'M12 10v6m0 0l-3-3m3 3l3-3m2 8V7a2 2 0 00-2-2h-8a2 2 0 00-2 2v13a2 2 0 002 2h8a2 2 0 002-2z',
    title: 'Rapports pour la direction',
    desc: 'Générez les bilans de retards et d’assiduité en PDF. Retrouvez les fiches individuelles et les exports groupés pour vos dossiers.',
    color: 'from-cyan-500 to-audit-500',
  },
  {
    icon: 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z',
    title: 'Espace enseignant et administration',
    desc: 'Sur mobile, consultez vos présences et vos cours. Les responsables habilités disposent du pointage par procuration avec un motif obligatoire.',
    color: 'from-audit-500 to-brand-500',
  },
];

export function Features() {
  return (
    <section id="features" className="relative py-20 md:py-28 bg-ink-50/60 overflow-hidden">
      <div className="absolute inset-0 bg-dots opacity-40 pointer-events-none" />
      <div className="relative max-w-7xl mx-auto px-4 md:px-6">
        <Reveal className="mb-14">
          <div className="grid lg:grid-cols-2 gap-8 lg:gap-12 items-center">
            <figure className="order-2 lg:order-1">
              <SchoolPhoto photo="accompagnement" alt="Une enseignante accompagne un élève dans son travail pendant une séance en classe." />
              <figcaption className="mt-3 text-sm text-ink-500 leading-relaxed">
                L’accompagnement en classe donne son sens au suivi des séances.
              </figcaption>
            </figure>
            <div className="order-1 lg:order-2">
              <div className="inline-block px-3 py-1 rounded-full bg-brand-50 text-brand-700 text-xs font-semibold mb-3">
                Fonctionnalités
              </div>
              <h2 className="text-3xl md:text-4xl font-bold text-ink-900 tracking-tight mb-3">
                La présence et le suivi pédagogique réunis
              </h2>
              <p className="text-ink-500 text-base leading-relaxed">
                Du pointage à la fiche de progression, Auditron-X accompagne les
                enseignants, les chefs de section, les censeurs et la direction.
              </p>
              <p className="text-ink-500 text-base leading-relaxed mt-4">
                Le cahier de texte et la validation des cours relient le suivi
                administratif au contenu réellement traité pendant les séances.
              </p>
            </div>
          </div>
        </Reveal>

        <MobileCarousel label="Fonctionnalités Auditron-X" desktopClassName="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
          {FEATURES.map((f, i) => (
            <Reveal key={f.title} delay={(i % 3) * 80}>
              <div className="group relative h-full p-6 rounded-2xl bg-white border border-ink-200 hover:border-brand-300 hover:shadow-xl hover:shadow-brand-500/5 transition-all duration-300 hover:-translate-y-1">
                <div className={`w-12 h-12 rounded-xl bg-gradient-to-br ${f.color} flex items-center justify-center mb-4 group-hover:scale-110 transition-transform`}>
                  <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d={f.icon} />
                  </svg>
                </div>
                <h3 className="font-bold text-ink-900 mb-2">{f.title}</h3>
                <p className="text-sm text-ink-500 leading-relaxed">{f.desc}</p>
                {/* Hover arrow */}
                <div className="absolute top-6 right-6 w-8 h-8 rounded-full bg-ink-50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                  <svg className="w-3.5 h-3.5 text-ink-600" fill="none" stroke="currentColor" strokeWidth="2.5" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                  </svg>
                </div>
              </div>
            </Reveal>
          ))}
        </MobileCarousel>
      </div>
    </section>
  );
}
