import { Mail, MessageCircle, Phone } from 'lucide-react';
import { LEGAL_DOCUMENTS, SUPPORT_EMAIL } from '@/content/legal';

const FOOTER_LINKS = {
  Établissement: [
    { label: 'Présences et assiduité', href: '/#features' },
    { label: 'Suivi pédagogique', href: '/#screenshots' },
    { label: 'Offres et abonnements', href: '/#pricing' },
  ],
  Équipe: [
    { label: 'Direction et administration', href: '/#benefits' },
    { label: 'Application mobile', href: '/#mobile' },
    { label: 'Demander une démonstration', href: '/#cta' },
  ],
};

export function Footer({ activeLegalPath }: { activeLegalPath?: string }) {
  return (
    <footer id="contact" className="relative bg-ink-900 text-white overflow-hidden">
      <div className="max-w-7xl mx-auto px-4 md:px-6">
        <div className="py-14 md:py-16 grid sm:grid-cols-2 lg:grid-cols-4 gap-8">
          <div>
            <a href="/" className="font-display font-bold text-lg flex items-center gap-2 mb-4">
              <img src="/logo.png" alt="" width={40} height={40} className="w-10 h-10 object-contain shrink-0" />
              <span>AUDITRON<span className="text-cyan-400">-X</span></span>
            </a>
            <p className="text-sm text-ink-300 leading-relaxed max-w-xs">
              Présences du personnel enseignant et administratif, assiduité et
              suivi pédagogique pour les établissements scolaires.
            </p>
          </div>
          {Object.entries(FOOTER_LINKS).map(([category, links]) => (
            <div key={category}>
              <h3 className="text-sm font-bold mb-4">{category}</h3>
              <ul className="space-y-3">
                {links.map((link) => (
                  <li key={link.label}>
                    <a href={link.href} className="text-sm text-ink-300 hover:text-white transition-colors">
                      {link.label}
                    </a>
                  </li>
                ))}
              </ul>
            </div>
          ))}
          <div>
            <h3 className="text-sm font-bold mb-4">Contact Auditron-X</h3>
            <ul className="space-y-3 text-sm text-ink-300">
              <li>
                <a href="mailto:demo@auditronx.com" className="flex items-center gap-2 hover:text-white break-all">
                  <Mail size={16} className="shrink-0" aria-hidden="true" />
                  demo@auditronx.com
                </a>
              </li>
              <li>
                <a href={`mailto:${SUPPORT_EMAIL}`} className="flex items-center gap-2 hover:text-white break-all">
                  <Mail size={16} className="shrink-0" aria-hidden="true" />
                  {SUPPORT_EMAIL}
                </a>
              </li>
              <li>
                <a href="tel:+237679561615" className="flex items-center gap-2 hover:text-white">
                  <Phone size={16} className="shrink-0" aria-hidden="true" />
                  +237 679 561 615
                </a>
              </li>
              <li>
                <a href="https://wa.me/237679561615" target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 hover:text-white">
                  <MessageCircle size={16} className="shrink-0" aria-hidden="true" />
                  WhatsApp
                </a>
              </li>
            </ul>
          </div>
        </div>
        <div className="border-t border-white/10 py-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4 text-xs text-ink-300">
          <p>© {new Date().getFullYear()} Auditron-X. Tous droits réservés.</p>
          <nav aria-label="Informations légales" className="flex flex-wrap gap-x-6 gap-y-3">
            {LEGAL_DOCUMENTS.map((document) => (
              <a key={document.path} href={document.path} aria-current={activeLegalPath === document.path ? 'page' : undefined} className="hover:text-white underline underline-offset-4 decoration-white/30 hover:decoration-white">
                {document.title}
              </a>
            ))}
          </nav>
        </div>
      </div>
    </footer>
  );
}
