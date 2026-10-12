import { Reveal } from './Reveal';
import { SchoolPhoto } from './SchoolPhoto';
import { MobileCarousel } from './MobileCarousel';
import { BookOpen, CalendarCheck, FileDown, Files, QrCode, UserCheck, Users } from 'lucide-react';

const FEATURES = [
  {
    icon: QrCode,
    title: 'Arrivées et départs par QR code',
    desc: 'Le personnel pointe depuis son téléphone, même sans connexion internet. Le scan est transmis par Bluetooth à la borne locale, qui l’envoie au serveur dès qu’elle est connectée. La direction retrouve les heures d’arrivée et de départ.',
    color: 'from-brand-500 to-brand-600',
  },
  {
    icon: Users,
    title: 'Personnel et emplois du temps',
    desc: 'Centralisez les noms, fonctions, matricules et coordonnées du personnel. Retrouvez un fichier actualisé, organisez les sections, les classes et les disciplines, puis planifiez les cours et leurs créneaux.',
    color: 'from-cyan-500 to-cyan-600',
  },
  {
    icon: Files,
    title: 'Bilans mensuels, globaux et par section',
    desc: 'Générez les rapports d’assiduité en PDF : taux mensuel, absences, durée cumulée des retards et des départs anticipés. Les emplois du temps donnent le contexte des journées attendues.',
    color: 'from-audit-500 to-audit-600',
  },
  {
    icon: BookOpen,
    title: 'Cahier de texte et progression',
    desc: 'L’enseignant marque les leçons réalisées et remplit le cahier de texte. Les censeurs et responsables habilités consultent les séances et suivent l’avancement du programme par classe et par discipline.',
    color: 'from-brand-500 to-cyan-500',
  },
  {
    icon: FileDown,
    title: 'Une fiche individuelle par enseignant',
    desc: 'Retrouvez les jours de cours prévus, les présences, absences, retards, départs anticipés et périodes à rattraper, avec le taux d’assiduité. Un suivi utile pour examiner l’activité du personnel payé à l’heure.',
    color: 'from-cyan-500 to-audit-500',
  },
  {
    icon: UserCheck,
    title: 'Pointage par procuration encadré',
    desc: 'Lorsqu’un enseignant ne peut pas pointer lui-même, un responsable habilité peut enregistrer sa présence à sa place. Le motif est obligatoire et l’intervention reste traçable.',
    color: 'from-audit-500 to-brand-500',
  },
  {
    icon: CalendarCheck,
    title: 'Signalements et jours fériés',
    desc: 'L’administration enregistre les absences justifiées : les jours signalés restent visibles sur la fiche sans pénaliser le taux d’assiduité. Les jours déclarés fériés sont exclus des calculs.',
    color: 'from-brand-500 to-brand-600',
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
                enseignants, les chefs de services, les censeurs et la direction.
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
                  <f.icon size={24} strokeWidth={1.8} className="text-white" aria-hidden="true" />
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
