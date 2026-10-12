import { useEffect, useState } from 'react';
import { Menu, X } from 'lucide-react';

const NAV_LINKS = [
  { label: 'Fonctionnalités', href: '/#features' },
  { label: 'Avantages', href: '/#benefits' },
  { label: 'Écrans', href: '/#screenshots' },
  { label: 'Tarifs', href: '/#pricing' },
  { label: 'Contact', href: '/#contact' },
];

export function Header({ solid = false }: { solid?: boolean }) {
  const [scrolled, setScrolled] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 16);
    onScroll();
    window.addEventListener('scroll', onScroll);
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  return (
    <header
      className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
        scrolled || solid
          ? 'bg-white/95 backdrop-blur-md shadow-sm border-b border-ink-200/60'
          : 'bg-transparent'
      }`}
    >
      <div className="max-w-7xl mx-auto px-4 md:px-6">
        <div className="flex items-center justify-between h-16 md:h-18">
          {/* Logo */}
          <a href="/" className="flex items-center gap-2 group" aria-label="Auditron-X, accueil">
            <img src="/logo.png" alt="" width={40} height={40} className="w-10 h-10 object-contain shrink-0 group-hover:scale-105 transition-transform" />
            <span className="font-display font-bold text-lg text-ink-900">AUDITRON<span className="text-[#f97316]">-X</span></span>
          </a>

          {/* Desktop nav */}
          <nav className="hidden lg:flex items-center gap-5">
            {NAV_LINKS.map((link) => (
              <a
                key={link.href}
                href={link.href}
                className="text-sm font-medium text-ink-600 hover:text-brand-700 transition-colors relative group"
              >
                {link.label}
                <span className="absolute -bottom-1 left-0 right-0 h-0.5 bg-cyan-500 rounded-full scale-x-0 group-hover:scale-x-100 transition-transform origin-left" />
              </a>
            ))}
          </nav>

          {/* CTA */}
          <div className="hidden lg:flex items-center gap-3">
            <a
              href="/#cta"
              className="px-4 py-2 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold shadow-lg shadow-brand-500/20 hover:shadow-brand-500/30 transition-all hover:-translate-y-0.5"
            >
              Demander une démo
            </a>
          </div>

          {/* Mobile menu button */}
          <button
            className="lg:hidden p-2 rounded-lg hover:bg-ink-100 transition-colors"
            onClick={() => setMobileOpen(!mobileOpen)}
            aria-label={mobileOpen ? 'Fermer le menu' : 'Ouvrir le menu'}
            aria-expanded={mobileOpen}
            aria-controls="mobile-navigation"
          >
            {mobileOpen ? <X size={24} className="text-ink-700" aria-hidden="true" /> : <Menu size={24} className="text-ink-700" aria-hidden="true" />}
          </button>
        </div>

        {/* Mobile menu */}
        {mobileOpen && (
          <div id="mobile-navigation" className="lg:hidden pb-4 border-t border-ink-200/60 pt-3 space-y-1 bg-white/95 backdrop-blur-md -mx-4 px-4">
            {NAV_LINKS.map((link) => (
              <a
                key={link.href}
                href={link.href}
                onClick={() => setMobileOpen(false)}
                className="block px-3 py-2.5 rounded-lg text-sm font-medium text-ink-700 hover:bg-brand-50 hover:text-brand-600 transition-colors"
              >
                {link.label}
              </a>
            ))}
            <div className="pt-2 space-y-2">
              <a href="/#cta" onClick={() => setMobileOpen(false)} className="block px-3 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-semibold text-center">
                Demander une démo
              </a>
            </div>
          </div>
        )}
      </div>
    </header>
  );
}
