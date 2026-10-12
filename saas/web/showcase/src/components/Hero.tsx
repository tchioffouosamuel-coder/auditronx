import { BrowserFrame } from './BrowserFrame';
import { PhoneFrame } from './PhoneFrame';
import { DashboardMockup } from './DashboardMockup';
import { MobileScreenAttendance } from './MobileScreens';

export function Hero() {
  return (
    <section className="relative pt-28 md:pt-36 pb-20 md:pb-28 overflow-hidden mesh-gradient">
      {/* Background decorations */}
      <div className="absolute inset-0 bg-grid opacity-60 pointer-events-none" />
      <div className="absolute top-0 right-0 w-[500px] h-[500px] bg-brand-500/5 rounded-full blur-[120px] pointer-events-none" />
      <div className="absolute bottom-0 left-0 w-[400px] h-[400px] bg-cyan-400/5 rounded-full blur-[100px] pointer-events-none" />

      <div className="relative max-w-7xl mx-auto px-4 md:px-6">
        <div className="grid lg:grid-cols-2 gap-12 lg:gap-8 items-center">
          {/* Left: Copy */}
          <div className="text-center lg:text-left">
            {/* Badge */}
            <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-brand-50 border border-brand-200 text-brand-700 text-xs font-semibold mb-5 animate-fade-down">
              <span className="relative flex w-2 h-2">
                <span className="absolute inline-flex w-full h-full rounded-full bg-audit-400 animate-ping opacity-75" />
                <span className="relative inline-flex w-2 h-2 rounded-full bg-audit-500" />
              </span>
              L’assiduité intelligente au service de l’éducation
            </div>

            <h1 className="text-4xl md:text-5xl lg:text-6xl font-bold text-ink-900 leading-[1.1] tracking-tight mb-5 animate-fade-up">
              Auditron-X<br />
              <span className="gradient-text">Suivi des présences</span>
              <br className="hidden md:block" /> en établissement scolaire
            </h1>

            <p className="text-base md:text-lg text-ink-500 leading-relaxed max-w-xl mx-auto lg:mx-0 mb-7 animate-fade-up" style={{ animationDelay: '0.1s' }}>
              Suivez la présence des enseignants et du personnel administratif,
              les retards et les cours réalisés. Le pointage sur téléphone et le
              portail de direction réunissent le suivi de votre établissement.
            </p>

            {/* CTAs */}
            <div className="flex flex-col sm:flex-row gap-3 justify-center lg:justify-start animate-fade-up" style={{ animationDelay: '0.2s' }}>
              <a
                href="#cta"
                className="px-6 py-3.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold shadow-xl shadow-brand-500/25 hover:shadow-brand-500/40 transition-all hover:-translate-y-0.5 text-center"
              >
                Demander une démonstration
              </a>
              <a
                href="#screenshots"
                className="px-6 py-3.5 rounded-xl bg-white hover:bg-ink-50 text-ink-700 text-sm font-semibold border border-ink-200 hover:border-ink-300 transition-all hover:-translate-y-0.5 text-center"
              >
                Voir les captures
              </a>
            </div>

            {/* Trust indicators */}
            <div className="flex items-center gap-5 mt-8 justify-center lg:justify-start animate-fade-up" style={{ animationDelay: '0.3s' }}>
              {[
                { value: 'Présences', label: 'Arrivées et départs' },
                { value: 'Assiduité', label: 'Retards et absences' },
                { value: 'Pédagogie', label: 'Cours et progression' },
              ].map((stat) => (
                <div key={stat.label} className="text-left">
                  <div className="text-lg font-bold text-ink-900">{stat.value}</div>
                  <div className="text-[11px] text-ink-400 font-medium">{stat.label}</div>
                </div>
              ))}
            </div>
          </div>

          {/* Right: Dashboard mockup with phone overlay */}
          <div className="relative animate-scale-in" style={{ animationDelay: '0.15s' }}>
            {/* Decorative blobs */}
            <div className="absolute -top-8 -right-4 w-32 h-32 bg-cyan-400/10 rounded-full blur-3xl pointer-events-none" />
            <div className="absolute -bottom-8 -left-4 w-32 h-32 bg-brand-500/10 rounded-full blur-3xl pointer-events-none" />

            {/* Browser mockup */}
            <div className="relative z-10">
              <BrowserFrame className="shadow-2xl shadow-brand-900/10">
                <DashboardMockup />
              </BrowserFrame>
            </div>

            {/* Phone overlay */}
            <div className="absolute -bottom-12 -left-4 md:-left-12 z-20 hidden sm:block animate-float" style={{ animationDelay: '0.5s' }}>
              <div className="scale-[0.65] origin-bottom-left">
                <PhoneFrame>
                  <MobileScreenAttendance />
                </PhoneFrame>
              </div>
            </div>

            {/* Floating stat badge */}
            <div className="absolute top-8 -right-2 md:-right-6 z-20 hidden md:flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl bg-white shadow-xl shadow-ink-900/10 border border-ink-100 animate-float" style={{ animationDelay: '1s' }}>
              <div className="w-9 h-9 rounded-lg bg-audit-50 flex items-center justify-center">
                <svg className="w-4.5 h-4.5 text-audit-600" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
              </div>
              <div>
                <div className="text-xs font-bold text-ink-900">Présence enregistrée</div>
                <div className="text-[10px] text-ink-400">Point de contrôle · 07:25</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
