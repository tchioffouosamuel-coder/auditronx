import { Reveal } from './Reveal';

const PLANS = [
  {
    name: 'Essai',
    price: '0',
    period: 'Découverte limitée dans le temps',
    desc: 'Pour découvrir le pointage sur un premier site.',
    features: [
      'Jusqu’à 25 membres du personnel',
      '1 borne sur un seul site',
      'Pointage QR',
      'Journal d’assiduité',
    ],
    cta: 'Demander un essai',
    highlighted: false,
  },
  {
    name: 'Standard',
    price: '75 000',
    period: 'Par établissement / an',
    desc: 'Pour le suivi quotidien d’un établissement de taille moyenne.',
    features: [
      'Jusqu’à 120 membres du personnel',
      'Jusqu’à 3 bornes',
      'Pointage QR et borne',
      'Journal d’assiduité et rapports PDF',
      'Cahier de texte numérique',
      'Notifications push',
    ],
    cta: 'Choisir Standard',
    highlighted: true,
  },
  {
    name: 'Premium',
    price: '150 000',
    period: 'Par établissement / an',
    desc: 'Pour les grands établissements et un suivi administratif étendu.',
    features: [
      'Personnel et bornes sans quota',
      'Fonctionnalités de l’offre Standard',
      'Journal d’audit',
      'Mises à jour des bornes à distance',
      'Exports PDF groupés en ZIP',
    ],
    cta: 'Échanger sur Premium',
    highlighted: false,
  },
];

export function Pricing() {
  return (
    <section id="pricing" className="relative py-20 md:py-28 bg-ink-50/60 overflow-hidden">
      <div className="absolute inset-0 bg-dots opacity-30 pointer-events-none" />
      <div className="relative max-w-7xl mx-auto px-4 md:px-6">
        <Reveal className="text-center max-w-2xl mx-auto mb-14">
          <div className="inline-block px-3 py-1 rounded-full bg-brand-50 text-brand-700 text-xs font-semibold mb-3">
            Tarifs
          </div>
          <h2 className="text-3xl md:text-4xl font-bold text-ink-900 tracking-tight mb-3">
            Une offre à la taille de votre établissement
          </h2>
          <p className="text-ink-500 text-base leading-relaxed">
            Choisissez selon l’effectif du personnel, le nombre de bornes et
            les modules nécessaires à votre suivi administratif et pédagogique.
          </p>
        </Reveal>

        <div className="grid md:grid-cols-3 gap-5 max-w-5xl mx-auto">
          {PLANS.map((plan, i) => (
            <Reveal key={plan.name} delay={i * 100}>
              <div
                className={`relative h-full p-7 rounded-2xl border transition-all ${
                  plan.highlighted
                    ? 'border-brand-500 bg-white shadow-2xl shadow-brand-500/10 md:scale-105'
                    : 'border-ink-200 bg-white hover:border-ink-300 hover:shadow-lg'
                }`}
              >
                {plan.highlighted && (
                  <div className="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full bg-gradient-to-r from-brand-500 to-cyan-500 text-white text-xs font-semibold shadow-lg">
                    Standard
                  </div>
                )}

                <div className="mb-5">
                  <h3 className="font-bold text-ink-900 text-lg mb-1">{plan.name}</h3>
                  <p className="text-sm text-ink-500">{plan.desc}</p>
                </div>

                <div className="mb-6">
                  <div className="flex flex-wrap items-baseline gap-2">
                    <span className={`text-3xl font-bold ${plan.highlighted ? 'text-brand-600' : 'text-ink-900'}`}>
                      {plan.price}
                    </span>
                    <span className="text-sm font-bold text-ink-500">FCFA</span>
                  </div>
                  <div className="text-xs text-ink-500 mt-1">{plan.period}</div>
                </div>

                <a
                  href={`mailto:demo@auditronx.com?subject=${encodeURIComponent(`Auditron-X : offre ${plan.name}`)}`}
                  className={`block w-full py-3 rounded-xl text-sm font-semibold text-center transition-all hover:-translate-y-0.5 ${
                    plan.highlighted
                      ? 'bg-brand-600 hover:bg-brand-700 text-white shadow-lg shadow-brand-500/25'
                      : 'bg-ink-100 hover:bg-ink-200 text-ink-700'
                  }`}
                >
                  {plan.cta}
                </a>

                <ul className="mt-6 space-y-3">
                  {plan.features.map((feat) => (
                    <li key={feat} className="flex items-start gap-2.5">
                      <svg className="w-5 h-5 text-audit-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                      </svg>
                      <span className="text-sm text-ink-600">{feat}</span>
                    </li>
                  ))}
                </ul>
              </div>
            </Reveal>
          ))}
        </div>

        <Reveal className="text-center mt-8">
          <p className="text-sm text-ink-400">
            Contactez l’équipe pour confirmer l’offre et préparer la configuration
            des bornes de votre établissement.
          </p>
        </Reveal>
      </div>
    </section>
  );
}
