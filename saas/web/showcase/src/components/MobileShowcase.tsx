import { Reveal } from './Reveal';
import { PhoneFrame } from './PhoneFrame';
import { MobileScreenHistory, MobileScreenScan, MobileScreenAttendance } from './MobileScreens';

export function MobileShowcase() {
  return (
    <section id="mobile" className="relative py-20 md:py-28 mesh-gradient-dark overflow-hidden">
      <div className="absolute inset-0 bg-grid-dark opacity-40 pointer-events-none" />
      <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-brand-500/10 rounded-full blur-[120px] pointer-events-none" />

      <div className="relative max-w-7xl mx-auto px-4 md:px-6">
        <div className="flex flex-col lg:flex-row items-center gap-12 lg:gap-16">
          {/* Left: Phones */}
          <div className="relative flex items-end justify-center gap-4 md:gap-6 order-2 lg:order-1 shrink-0">
            {/* Phone 1 - back */}
            <div className="w-[169px] h-[351px] translate-y-8 opacity-80 hidden xl:block [&>div]:scale-[0.65] [&>div]:origin-top-left">
              <PhoneFrame tilt="left">
                <MobileScreenHistory />
              </PhoneFrame>
            </div>
            {/* Phone 2 - center, elevated */}
            <div className="w-[195px] h-[405px] z-10 [&>div]:scale-[0.75] [&>div]:origin-top-left">
              <PhoneFrame tilt="front">
                <MobileScreenAttendance />
              </PhoneFrame>
            </div>
            {/* Phone 3 - back right */}
            <div className="w-[169px] h-[351px] translate-y-8 opacity-80 hidden xl:block [&>div]:scale-[0.65] [&>div]:origin-top-left">
              <PhoneFrame tilt="right">
                <MobileScreenScan />
              </PhoneFrame>
            </div>
          </div>

          {/* Right: Copy */}
          <div className="flex-1 text-center lg:text-left order-1 lg:order-2">
            <Reveal>
              <div className="inline-block px-3 py-1 rounded-full bg-white/10 text-cyan-300 text-xs font-semibold mb-4 backdrop-blur-sm border border-white/10">
                Application mobile
              </div>
              <h2 className="text-3xl md:text-4xl font-bold text-white tracking-tight mb-4 leading-tight">
                Votre journée scolaire<br />sur votre téléphone
              </h2>
              <p className="text-white/60 text-base leading-relaxed max-w-lg mx-auto lg:mx-0 mb-6">
                Activez votre compte dans l’établissement, pointez votre arrivée
                et votre départ au point de contrôle, puis retrouvez votre
                historique, votre emploi du temps et vos cours du jour.
                Les responsables habilités peuvent aussi pointer par procuration.
              </p>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-md mx-auto lg:mx-0">
                {[
                  { icon: 'M4 4h5M4 4v5m16-5h-5m5 0v5M4 20h5m-5 0v-5m16 5h-5m5 0v-5', label: 'Pointage QR + Bluetooth' },
                  { icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', label: 'Historique des présences' },
                  { icon: 'M8 3v4m8-4v4M4 9h16M4 5h16v16H4z', label: 'Emploi du temps' },
                  { icon: 'M9 12l2 2 4-4M4 4h16v16H4z', label: 'Procuration avec motif' },
                ].map((item) => (
                  <div key={item.label} className="flex items-center gap-2.5 px-3 py-2.5 rounded-xl bg-white/5 border border-white/10 backdrop-blur-sm">
                    <svg className="w-4 h-4 text-cyan-400 flex-shrink-0" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" d={item.icon} />
                    </svg>
                    <span className="text-xs font-medium text-white/80">{item.label}</span>
                  </div>
                ))}
              </div>
            </Reveal>
          </div>
        </div>
      </div>
    </section>
  );
}
