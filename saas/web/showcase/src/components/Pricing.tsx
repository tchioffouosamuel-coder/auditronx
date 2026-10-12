import { useState, type FormEvent } from 'react';
import { Reveal } from './Reveal';

const PLANS = [
  {
    name: 'Essai',
    price: '0',
    period: '30 jours',
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
    price: '200 000',
    period: 'Par établissement / an',
    desc: 'Pour le suivi quotidien d’un établissement de taille moyenne.',
    features: [
      'Jusqu’à 120 membres du personnel',
      'Jusqu’à 3 bornes',
      'Pointage QR et borne',
      'Mises à jour des bornes à distance',
      'Journal d’assiduité et rapports PDF',
      'Cahier de texte numérique',
      'Journal d’audit',
      'Notifications push',
      'Exports PDF groupés en ZIP',
    ],
    cta: 'Choisir Standard',
    highlighted: true,
  },
  {
    name: 'Premium',
    price: '400 000',
    period: 'Par établissement / an',
    desc: 'Pour les grands établissements et un suivi administratif étendu.',
    features: [
      '+ 120 membres du personnel',
      'Jusqu’à 5 bornes',
      'Pointage par reconnaissance faciale',
      'Fonctionnalités de l’offre Standard',
    ],
    cta: 'Échanger sur Premium',
    highlighted: false,
  },
];

export function Pricing() {
  const [selectedPlan, setSelectedPlan] = useState<(typeof PLANS)[number] | null>(null);

  function preparerDemande(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!selectedPlan) return;

    const form = new FormData(event.currentTarget);
    const details = [
      `Offre : ${selectedPlan.name}`,
      `Montant : ${selectedPlan.price} FCFA`,
      `Établissement : ${form.get('etablissement')}`,
      `Nom du contact : ${form.get('nom')}`,
      `E-mail : ${form.get('email')}`,
      `Téléphone : ${form.get('telephone')}`,
      selectedPlan.price !== '0' ? 'Moyen de paiement : Mobile Money' : null,
      selectedPlan.price !== '0' ? 'Paiement déclaré effectué : oui' : null,
      selectedPlan.price !== '0' ? `Référence de transaction : ${form.get('reference')}` : null,
    ].filter(Boolean).join('\n');

    window.location.href = `mailto:demo@auditronx.com?subject=${encodeURIComponent(`Souscription Auditron-X — ${selectedPlan.name}`)}&body=${encodeURIComponent(details)}`;
  }

  return (
    <section id="pricing" className="pricing-section relative py-20 md:py-28 overflow-hidden">
      <div className="relative z-10 max-w-7xl mx-auto px-4 md:px-6">
        <Reveal className="text-center max-w-2xl mx-auto mb-14">
          <div className="inline-block px-3 py-1 rounded-full bg-brand-50 text-brand-700 text-xs font-semibold mb-3">
            Tarifs
          </div>
          <h2 className="text-3xl md:text-4xl font-bold text-ink-900 tracking-tight mb-3">
            Une offre à la taille de votre établissement
          </h2>
          <p className="text-ink-700 text-base leading-relaxed">
            Choisissez selon l’effectif du personnel, le nombre de bornes et
            les modules nécessaires à votre suivi administratif et pédagogique.
          </p>
        </Reveal>

        <div className="grid md:grid-cols-3 gap-5 max-w-5xl mx-auto">
          {PLANS.map((plan, i) => (
            <Reveal key={plan.name} delay={i * 100}>
              <div
                className={`relative h-full p-7 rounded-2xl border transition-all ${plan.highlighted
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

                <button
                  type="button"
                  onClick={() => setSelectedPlan(plan)}
                  className={`block w-full py-3 rounded-xl text-sm font-semibold text-center transition-all hover:-translate-y-0.5 ${plan.highlighted
                      ? 'bg-brand-600 hover:bg-brand-700 text-white shadow-lg shadow-brand-500/25'
                      : 'bg-ink-100 hover:bg-ink-200 text-ink-700'
                    }`}
                >
                  {plan.cta}
                </button>

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
          <p className="text-sm text-ink-700">
            Contactez l’équipe pour confirmer l’offre et préparer la configuration
            des bornes de votre établissement.
          </p>
        </Reveal>
      </div>

      {selectedPlan && (
        <div
          className="fixed inset-0 z-50 flex items-end justify-center bg-brand-950/60 p-0 backdrop-blur-sm sm:items-center sm:p-4"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) setSelectedPlan(null);
          }}
        >
          <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="subscription-title"
            className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-6 shadow-2xl sm:rounded-2xl"
          >
            <div className="mb-5 flex items-start justify-between gap-4">
              <div>
                <h3 id="subscription-title" className="text-xl font-bold text-ink-900">
                  {selectedPlan.price === '0' ? 'Demander un essai' : `Souscrire au pack ${selectedPlan.name}`}
                </h3>
                <p className="mt-1 text-sm text-ink-600">
                  {selectedPlan.price} FCFA · {selectedPlan.period}
                </p>
              </div>
              <button
                type="button"
                aria-label="Fermer"
                onClick={() => setSelectedPlan(null)}
                className="rounded-lg px-2 py-1 text-xl text-ink-500 hover:bg-ink-100"
              >
                ×
              </button>
            </div>

            <form onSubmit={preparerDemande} className="space-y-4">
              {selectedPlan.price !== '0' && (
                <div className="rounded-xl border border-brand-200 bg-brand-50 p-4 text-sm text-brand-900">
                  <p className="font-semibold">Paiement Mobile Money</p>
                  <p className="mt-1">
                    Indiquez la référence d’un règlement déjà effectué. Les coordonnées de paiement
                    sont confirmées par l’équipe Auditron-X ; aucun paiement n’est encaissé sur cette page.
                  </p>
                </div>
              )}

              <label className="block text-sm">
                <span className="mb-1 block font-medium text-ink-700">Nom de l’établissement</span>
                <input name="etablissement" required maxLength={160} className="w-full rounded-lg border border-ink-200 px-3 py-2 outline-none focus:border-brand-500" />
              </label>
              <label className="block text-sm">
                <span className="mb-1 block font-medium text-ink-700">Nom du contact</span>
                <input name="nom" required autoComplete="name" maxLength={120} className="w-full rounded-lg border border-ink-200 px-3 py-2 outline-none focus:border-brand-500" />
              </label>
              <div className="grid gap-4 sm:grid-cols-2">
                <label className="block text-sm">
                  <span className="mb-1 block font-medium text-ink-700">E-mail</span>
                  <input name="email" type="email" required autoComplete="email" maxLength={160} className="w-full rounded-lg border border-ink-200 px-3 py-2 outline-none focus:border-brand-500" />
                </label>
                <label className="block text-sm">
                  <span className="mb-1 block font-medium text-ink-700">Téléphone</span>
                  <input name="telephone" type="tel" required autoComplete="tel" maxLength={40} className="w-full rounded-lg border border-ink-200 px-3 py-2 outline-none focus:border-brand-500" />
                </label>
              </div>
              {selectedPlan.price !== '0' && (
                <label className="block text-sm">
                  <span className="mb-1 block font-medium text-ink-700">Référence de transaction Mobile Money</span>
                  <input name="reference" required maxLength={120} className="w-full rounded-lg border border-ink-200 px-3 py-2 outline-none focus:border-brand-500" />
                </label>
              )}
              {selectedPlan.price !== '0' && (
                <label className="flex items-start gap-2 text-sm text-ink-700">
                  <input name="paiement_effectue" type="checkbox" required className="mt-1 accent-brand-600" />
                  <span>J’atteste que le règlement Mobile Money a déjà été effectué.</span>
                </label>
              )}
              <p className="text-xs leading-relaxed text-ink-500">
                Le bouton prépare un e-mail à demo@auditronx.com dans votre application de messagerie.
                La souscription sera confirmée après vérification par l’équipe.
              </p>
              <button type="submit" className="w-full rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-brand-700">
                Préparer la demande par e-mail
              </button>
            </form>
          </section>
        </div>
      )}
    </section>
  );
}
