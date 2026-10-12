import { Reveal } from './Reveal';
import { SchoolPhoto } from './SchoolPhoto';

export function FinalCTA() {
  return (
    <section id="cta" className="relative py-20 md:py-28 bg-brand-950 overflow-hidden">
      <SchoolPhoto photo="accompagnement" alt="" background />
      <div className="cta-photo-scrim absolute inset-0 pointer-events-none" />

      <div className="relative max-w-7xl mx-auto px-4 md:px-6">
        <Reveal className="max-w-xl mx-auto lg:mr-0 text-center lg:text-left">
          {/* Badge */}
          <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 border border-white/10 text-cyan-300 text-xs font-semibold mb-6 backdrop-blur-sm">
            <span className="relative flex w-2 h-2">
              <span className="absolute inline-flex w-full h-full rounded-full bg-audit-400 animate-ping opacity-75" />
              <span className="relative inline-flex w-2 h-2 rounded-full bg-audit-500" />
            </span>
            Pour votre établissement
          </div>

          <h2 className="text-3xl md:text-5xl font-bold text-white mb-4 leading-tight">
            Organisons le suivi<br />de votre personnel
          </h2>

          <p className="text-white/90 text-base md:text-lg leading-relaxed mb-8">
            Présentez-nous votre établissement, vos effectifs et vos points de
            contrôle. Découvrez le pointage, les bilans d’assiduité et le suivi
            pédagogique lors d’une démonstration.
          </p>

          <div className="flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
            <a
              href="mailto:demo@auditronx.com?subject=Demande%20de%20d%C3%A9monstration%20Auditron-X"
              className="px-7 py-3.5 rounded-xl bg-gradient-to-r from-brand-500 to-cyan-500 hover:from-brand-400 hover:to-cyan-400 text-white text-sm font-bold shadow-2xl shadow-brand-500/30 transition-all hover:-translate-y-0.5"
            >
              Demander une démonstration
            </a>
            <a
              href="https://wa.me/237679561615"
              target="_blank"
              rel="noopener noreferrer"
              className="px-7 py-3.5 rounded-xl bg-white/10 hover:bg-white/15 text-white text-sm font-semibold border border-white/15 backdrop-blur-sm transition-all hover:-translate-y-0.5"
            >
              Échanger sur WhatsApp
            </a>
          </div>

          <div className="flex flex-wrap items-center justify-center lg:justify-start gap-6 mt-8 text-white/90 text-xs">
            <div className="flex items-center gap-1.5">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
              </svg>
              demo@auditronx.com
            </div>
            <div className="flex items-center gap-1.5">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
              </svg>
              +237 679 561 615
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  );
}
