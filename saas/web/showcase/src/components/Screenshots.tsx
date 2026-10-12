import { Reveal } from './Reveal';
import { BrowserFrame } from './BrowserFrame';
import { PhoneFrame } from './PhoneFrame';
import { DashboardMockup } from './DashboardMockup';
import { CahierTexteMockup, PersonnelMockup } from './SchoolMockups';
import { MobileScreenAttendance, MobileScreenAdminDashboard } from './MobileScreens';

export function Screenshots() {
  return (
    <section id="screenshots" className="relative py-20 md:py-28 bg-white overflow-hidden">
      <div className="max-w-7xl mx-auto px-4 md:px-6">
        <Reveal className="text-center max-w-2xl mx-auto mb-14">
          <div className="inline-block px-3 py-1 rounded-full bg-cyan-50 text-cyan-700 text-xs font-semibold mb-3">
            Aperçus du produit
          </div>
          <h2 className="text-3xl md:text-4xl font-bold text-ink-900 tracking-tight mb-3">
            Le produit, vu de l'intérieur
          </h2>
          <p className="text-ink-500 text-base leading-relaxed">
            Tableau de bord des présences, fiches du personnel, cahier de texte
            et espace mobile : les principaux usages de votre établissement.
          </p>
        </Reveal>

        {/* Screenshot 1: Dashboard */}
        <Reveal className="mb-10">
          <div className="relative">
            <div className="absolute -top-3 left-6 z-10 px-3 py-1 rounded-full bg-ink-900 text-white text-xs font-semibold">
              Tableau de bord
            </div>
            <BrowserFrame className="shadow-2xl shadow-ink-900/10">
              <DashboardMockup />
            </BrowserFrame>
          </div>
        </Reveal>

        {/* Screenshot 2 & 3 side by side */}
        <div className="grid lg:grid-cols-2 gap-8 mb-10">
          <Reveal>
            <div className="relative">
              <div className="absolute -top-3 left-6 z-10 px-3 py-1 rounded-full bg-ink-900 text-white text-xs font-semibold">
                Cahier de texte numérique
              </div>
              <BrowserFrame url="Auditron-X / Cahier de texte">
                <CahierTexteMockup />
              </BrowserFrame>
            </div>
          </Reveal>
          <Reveal delay={100}>
            <div className="relative">
              <div className="absolute -top-3 left-6 z-10 px-3 py-1 rounded-full bg-ink-900 text-white text-xs font-semibold">
                Gestion du personnel
              </div>
              <BrowserFrame url="Auditron-X / Personnel">
                <PersonnelMockup />
              </BrowserFrame>
            </div>
          </Reveal>
        </div>

        {/* Screenshot 4: Mobile views */}
        <Reveal>
          <div className="relative py-8 md:py-12 border-t border-ink-200 overflow-hidden">
            <div className="absolute top-0 right-0 w-64 h-64 bg-cyan-400/8 rounded-full blur-3xl pointer-events-none" />
            <div className="absolute bottom-0 left-0 w-64 h-64 bg-brand-500/8 rounded-full blur-3xl pointer-events-none" />

            <div className="relative flex flex-col lg:flex-row items-center gap-8">
              <div className="flex-1 text-center lg:text-left">
                <div className="inline-block px-3 py-1 rounded-full bg-brand-100 text-brand-700 text-xs font-semibold mb-3">
                  Vue mobile
                </div>
                <h3 className="text-xl md:text-2xl font-bold text-ink-900 mb-2">
                  Présences et cours sur mobile
                </h3>
                <p className="text-sm text-ink-500 leading-relaxed max-w-md">
                  L’enseignant retrouve ses arrivées, ses départs et ses cours.
                  La direction consulte le suivi de l’établissement depuis son
                  espace d’administration mobile.
                </p>
              </div>

              <div className="flex gap-4 md:gap-6 items-end">
                <div className="w-[130px] h-[270px] sm:w-[182px] sm:h-[378px] opacity-90 [&>div]:scale-50 sm:[&>div]:scale-[0.7] [&>div]:origin-top-left">
                  <PhoneFrame tilt="left">
                    <MobileScreenAttendance />
                  </PhoneFrame>
                </div>
                <div className="w-[130px] h-[270px] sm:w-[208px] sm:h-[432px] [&>div]:scale-50 sm:[&>div]:scale-[0.8] [&>div]:origin-top-left">
                  <PhoneFrame tilt="right">
                    <MobileScreenAdminDashboard />
                  </PhoneFrame>
                </div>
              </div>
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  );
}
