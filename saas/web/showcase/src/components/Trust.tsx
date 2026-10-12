import { Reveal } from './Reveal';

const TRUST_ITEMS = [
  {
    icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    title: 'Données isolées par établissement',
    desc: 'Chaque établissement dispose de sa propre base de données. Les comptes et les présences restent rattachés à son espace.',
  },
  {
    icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10',
    title: 'Pointage lié au site',
    desc: 'Le scan du QR est transmis à la borne locale par Bluetooth. Le personnel pointe au point de contrôle de son établissement.',
  },
  {
    icon: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
    title: 'Interventions traçables',
    desc: 'Les pointages par procuration et les corrections sont associés à un responsable et à un motif pour permettre leur vérification.',
  },
  {
    icon: 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z',
    title: 'Droits selon les responsabilités',
    desc: 'L’enseignant consulte son suivi personnel. Les responsables accèdent aux personnels et aux modules autorisés par leurs accréditations.',
  },
];

export function Trust() {
  return (
    <section className="relative py-20 md:py-28 bg-white overflow-hidden">
      <div className="max-w-7xl mx-auto px-4 md:px-6">
        <div className="grid lg:grid-cols-2 gap-12 items-center">
          {/* Left: Copy */}
          <Reveal>
            <div className="inline-block px-3 py-1 rounded-full bg-brand-50 text-brand-700 text-xs font-semibold mb-3">
              Confiance & fiabilité
            </div>
            <h2 className="text-3xl md:text-4xl font-bold text-ink-900 tracking-tight mb-4 leading-tight">
              Une plateforme pensée<br />pour la fiabilité
            </h2>
            <p className="text-ink-500 text-base leading-relaxed mb-6">
              Le suivi du personnel exige des accès maîtrisés et un historique
              consultable. Auditron-X relie les pointages à l’établissement et
              conserve la trace des interventions administratives.
            </p>

            {/* Trust stats */}
            <div className="grid grid-cols-3 gap-4">
              {[
                { value: 'QR + BLE', label: 'Pointage local' },
                { value: 'Par rôle', label: 'Accès aux données' },
                { value: 'Historique', label: 'Suivi des actions' },
              ].map((s) => (
                <div key={s.label} className="p-4 rounded-xl bg-ink-50 border border-ink-200">
                  <div className="text-sm font-bold text-brand-600 break-words">{s.value}</div>
                  <div className="text-xs text-ink-500 font-medium mt-0.5">{s.label}</div>
                </div>
              ))}
            </div>
          </Reveal>

          {/* Right: Trust items */}
          <div className="grid sm:grid-cols-2 gap-4">
            {TRUST_ITEMS.map((item, i) => (
              <Reveal key={item.title} delay={(i % 2) * 100}>
                <div className="h-full p-5 rounded-2xl border border-ink-200 bg-gradient-to-br from-white to-ink-50/40 hover:border-brand-300 hover:shadow-lg transition-all">
                  <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-500 to-cyan-500 flex items-center justify-center mb-3">
                    <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" d={item.icon} />
                    </svg>
                  </div>
                  <h3 className="font-bold text-ink-900 mb-1.5 text-sm">{item.title}</h3>
                  <p className="text-xs text-ink-500 leading-relaxed">{item.desc}</p>
                </div>
              </Reveal>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
