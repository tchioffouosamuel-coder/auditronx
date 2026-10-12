import { useEffect } from 'react';
import { ArrowLeft, ArrowRight, FileText, Mail, ShieldCheck } from 'lucide-react';
import { LEGAL_DOCUMENTS, SUPPORT_EMAIL, type LegalDocument } from '@/content/legal';

export function LegalPage({ document }: { document: LegalDocument }) {
  const Icon = document.kind === 'privacy' ? ShieldCheck : FileText;
  const relatedDocument = LEGAL_DOCUMENTS.find((item) => item.path !== document.path)!;

  useEffect(() => {
    window.document.title = `${document.title} | Auditron-X`;
    for (const selector of ['meta[name="description"]', 'meta[property="og:description"]']) {
      window.document.querySelector(selector)?.setAttribute('content', document.description);
    }
    window.document.querySelector('meta[property="og:title"]')?.setAttribute('content', `${document.title} | Auditron-X`);
  }, [document]);

  return (
    <article className="pt-16">
      <div className="bg-brand-50 border-b border-brand-100">
        <div className="max-w-7xl mx-auto px-4 md:px-6 py-10 md:py-14">
          <a href="/" className="inline-flex items-center gap-2 text-sm font-semibold text-brand-700 hover:text-brand-900 mb-8">
            <ArrowLeft size={16} aria-hidden="true" />
            Retour à l’accueil
          </a>
          <div className="flex items-center gap-2 text-brand-700 text-sm font-semibold mb-3">
            <Icon size={20} aria-hidden="true" />
            <span>Auditron-X</span>
          </div>
          <h1 className="font-display text-[28px] sm:text-4xl font-bold leading-tight break-words">{document.title}</h1>
          <p className="mt-4 max-w-2xl text-ink-700 leading-relaxed">{document.description}</p>
          <p className="mt-5 text-sm text-ink-600">Dernière mise à jour : <time dateTime="2026-10-12">12 octobre 2026</time></p>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 md:px-6 py-10 md:py-14 grid lg:grid-cols-[240px_minmax(0,1fr)] gap-10 lg:gap-16">
        <aside>
          <nav aria-label="Sommaire" className="lg:sticky lg:top-24 border-l-2 border-brand-200 pl-5">
            <h2 className="text-sm font-bold text-ink-900 mb-4">Sommaire</h2>
            <ol className="space-y-3 text-sm leading-relaxed">
              {document.sections.map((section, index) => (
                <li key={section.id}>
                  <a href={`#${section.id}`} className="text-ink-600 hover:text-brand-700">
                    {index + 1}. {section.title}
                  </a>
                </li>
              ))}
            </ol>
          </nav>
        </aside>

        <div className="min-w-0 max-w-3xl legal-content">
          {document.sections.map((section, index) => (
            <section id={section.id} key={section.id} aria-labelledby={`${section.id}-title`} className="mb-10 last:mb-0">
              <h2 id={`${section.id}-title`} className="font-display text-xl sm:text-2xl font-bold leading-snug mb-4 text-ink-900">
                {index + 1}. {section.title}
              </h2>
              <div className="space-y-4 text-[15px] sm:text-base leading-7 text-ink-700">{section.content}</div>
            </section>
          ))}

          <div className="border-t border-ink-200 pt-8 mt-12 space-y-5">
            <a href={`mailto:${SUPPORT_EMAIL}`} className="inline-flex items-center gap-2 text-brand-700 font-semibold break-all">
              <Mail size={18} className="shrink-0" aria-hidden="true" />
              {SUPPORT_EMAIL}
            </a>
            <div>
              <a href={relatedDocument.path} className="inline-flex items-center gap-2 text-sm font-semibold text-brand-700">
                {relatedDocument.title}
                <ArrowRight size={16} className="shrink-0" aria-hidden="true" />
              </a>
            </div>
          </div>
        </div>
      </div>
    </article>
  );
}
