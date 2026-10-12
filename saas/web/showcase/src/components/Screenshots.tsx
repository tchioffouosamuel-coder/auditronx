import { Reveal } from './Reveal';
import { BrowserFrame } from './BrowserFrame';
import { PhoneFrame } from './PhoneFrame';
import { DashboardMockup } from './DashboardMockup';
import { CahierTexteMockup, PersonnelMockup } from './SchoolMockups';
import { MobileScreenAttendance, MobileScreenAdminDashboard } from './MobileScreens';
import { MobileCarousel } from './MobileCarousel';

export function Screenshots() {
  return (
    <section id="screenshots" className="relative pt-20 md:pt-28 bg-white overflow-hidden">
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

        <MobileCarousel label="Aperçus du produit" desktopClassName="grid lg:grid-cols-2 gap-8" slideClassName="lg:first:col-span-2" className="mb-10">
          <Reveal>
            <div className="relative">
              <div className="absolute -top-3 left-6 z-10 px-3 py-1 rounded-full bg-ink-900 text-white text-xs font-semibold">
                Tableau de bord
              </div>
              <BrowserFrame className="shadow-2xl shadow-ink-900/10">
                <DashboardMockup />
              </BrowserFrame>
            </div>
          </Reveal>

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
        </MobileCarousel>
      </div>

      {/* Screenshot 4: Mobile views */}
      <div className="mobile-preview-band relative overflow-hidden py-16 md:py-20">
        <div className="mobile-preview-plane absolute inset-0 pointer-events-none" aria-hidden="true" />
        <div className="mobile-preview-fold absolute inset-0 pointer-events-none" aria-hidden="true" />
        <Reveal className="relative max-w-7xl mx-auto px-4 md:px-6">
          <div className="relative flex flex-col lg:flex-row items-center gap-12">
            <div className="flex-1 text-center lg:text-left">
              <div className="inline-block px-3 py-1 rounded-full bg-brand-100 text-brand-700 text-xs font-semibold mb-3">
                Vue mobile
              </div>
              <h3 className="text-xl md:text-2xl font-bold text-ink-900 mb-2">
                Présences et cours sur mobile
              </h3>
              <p className="text-sm text-ink-700 leading-relaxed max-w-md mx-auto lg:mx-0">
                L’enseignant retrouve ses arrivées, ses départs et ses cours.
                La direction consulte le suivi de l’établissement depuis son
                espace d’administration mobile.
              </p>
            </div>

            <div className="flex gap-4 md:gap-6 items-end shrink-0 sm:px-2 pb-8 pt-3">
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
        </Reveal>
      </div>
    </section>
  );
}
