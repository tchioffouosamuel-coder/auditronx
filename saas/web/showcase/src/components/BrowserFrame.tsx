import type { ReactNode } from 'react';

interface BrowserFrameProps {
  children: ReactNode;
  className?: string;
  url?: string;
}

export function BrowserFrame({
  children,
  className = '',
  url = 'Auditron-X / Tableau de bord',
}: BrowserFrameProps) {
  return (
    <div
      className={`relative rounded-xl bg-ink-800 shadow-2xl overflow-hidden border border-ink-700/50 ${className}`}
    >
      {/* Browser chrome */}
      <div className="flex items-center gap-2 px-4 py-3 bg-ink-800 border-b border-ink-700/50">
        <div className="flex gap-1.5">
          <div className="w-3 h-3 rounded-full bg-red-400/80" />
          <div className="w-3 h-3 rounded-full bg-yellow-400/80" />
          <div className="w-3 h-3 rounded-full bg-green-400/80" />
        </div>
        <div className="flex-1 min-w-0 flex justify-center">
          <div className="flex items-center gap-2 px-2 md:px-4 py-1.5 rounded-md bg-ink-700/60 text-ink-300 text-[10px] md:text-xs font-medium min-w-0 justify-center">
            <svg className="w-3 h-3 text-audit-400" fill="currentColor" viewBox="0 0 16 16">
              <path d="M6 10V8h4v2a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1V6a1 1 0 0 0-.293-.707l-5-5A1 1 0 0 0 8.586 0H4a4 4 0 0 0-4 4v6a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1z" opacity="0.5"/>
            </svg>
            <span className="truncate">{url}</span>
          </div>
        </div>
        <div className="w-12 hidden md:block" />
      </div>
      {/* Content */}
      <div className="bg-white">{children}</div>
    </div>
  );
}
