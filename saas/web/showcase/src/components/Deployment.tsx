import { BookOpen, CalendarDays, ChevronDown, GraduationCap, School, Users } from 'lucide-react';
import { Reveal } from './Reveal';

const SETUP_DATA = [
  {
    icon: School,
    title: 'Votre établissement',
    detail: 'Nom, arrondissement, département, région, ville et logo de l’établissement.',
  },
  {
    icon: Users,
    title: 'Le fichier du personnel',
    detail: 'Pour chaque personne : nom, poste ou fonction, numéro de téléphone et matricule.',
  },
  {
    icon: GraduationCap,
    title: 'Les classes',
    detail: 'La liste des classes prises en charge par l’établissement.',
  },
  {
    icon: BookOpen,
    title: 'Les matières enseignées',
    detail: 'La liste des matières ou disciplines à rattacher aux cours.',
  },
  {
    icon: CalendarDays,
    title: 'Les périodes de cours',
    detail: 'Les créneaux horaires, leur organisation et les emplois du temps du personnel.',
  },
];

export function Deployment() {
  return (
    <section id="deployment" className="py-12 md:py-20 bg-white border-y border-ink-200">
      <div className="max-w-7xl mx-auto px-4 md:px-6 grid lg:grid-cols-2 gap-12 lg:gap-16">
        <Reveal>
          <p className="text-xs font-semibold text-brand-700 mb-3">Configuration</p>
          <h2 className="text-2xl font-bold text-ink-900 mb-3">Les données à préparer</h2>
          <p className="text-sm text-ink-600 leading-relaxed mb-6">
            Le suivi commence avec les informations de votre établissement,
            de votre personnel et de votre organisation pédagogique.
          </p>
          <div className="divide-y divide-ink-200 border-y border-ink-200">
            {SETUP_DATA.map((item) => (
              <details key={item.title} className="group">
                <summary className="list-none [&::-webkit-details-marker]:hidden cursor-pointer flex items-center gap-3 py-4 text-sm font-semibold text-ink-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-cyan-700 focus-visible:outline-offset-2">
                  <item.icon size={20} className="shrink-0 text-brand-600" aria-hidden="true" />
                  <span className="flex-1 min-w-0">{item.title}</span>
                  <ChevronDown size={18} className="shrink-0 text-ink-500 group-open:rotate-180" aria-hidden="true" />
                </summary>
                <p className="pl-8 pb-4 text-sm text-ink-600 leading-relaxed">{item.detail}</p>
              </details>
            ))}
          </div>
        </Reveal>

        <Reveal delay={100}>
          <p className="text-xs font-semibold text-cyan-700 mb-3">Feuille de route</p>
          <h2 className="text-2xl font-bold text-ink-900 mb-3">Évolutions prévues</h2>
          <p className="text-sm text-ink-600 leading-relaxed mb-6">
            Ces projets ne font pas partie des fonctionnalités actuellement disponibles.
          </p>
          <div className="border-l-2 border-cyan-500 pl-5 space-y-7">
            <div>
              <p className="text-xs font-semibold text-cyan-700 mb-2">Objectif : année scolaire 2027–2028</p>
              <h3 className="font-bold text-ink-900 mb-2">Emplois du temps assistés par IA</h3>
              <p className="text-sm text-ink-600 leading-relaxed">
                Une fonction de proposition automatique d’emploi du temps est
                prévue. Cette fonctionnalité sera disponible gratuitement pour les utilisateurs de la
                saison 2026–2027, lors de sa mise à disposition.
              </p>
            </div>
            <div>
              <p className="text-xs font-semibold text-brand-700 mb-2">Extension progressive envisagée</p>
              <h3 className="font-bold text-ink-900 mb-2">Le suivi des élèves</h3>
              <p className="text-sm text-ink-600 leading-relaxed">
                Étendre le suivi de l’assiduité et de la ponctualité aux élèves
                pour accompagner la lutte contre l’absentéisme. Disponible dès l'année scolaire 2027–2028.
              </p>
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  );
}
