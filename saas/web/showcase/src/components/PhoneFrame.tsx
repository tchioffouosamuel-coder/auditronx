import type { CSSProperties, ReactNode } from 'react';

interface PhoneFrameProps {
  children: ReactNode;
  className?: string;
  tilt?: 'left' | 'right' | 'front';
}

const TILT_STYLES: Record<NonNullable<PhoneFrameProps['tilt']>, CSSProperties> = {
  left: { transform: 'rotateY(18deg) rotateX(3deg) rotateZ(-2deg)' },
  right: { transform: 'rotateY(-18deg) rotateX(3deg) rotateZ(2deg)' },
  front: { transform: 'rotateY(0deg) rotateX(2deg)' },
};

export function PhoneFrame({ children, className = '', tilt = 'front' }: PhoneFrameProps) {
  return (
    <div
      className={`relative ${className}`}
      style={{ width: 260, height: 540, perspective: 1200 }}
    >
      <div
        className="absolute -bottom-6 left-1/2 w-[82%] h-10 -translate-x-1/2 rounded-[50%] bg-black/40 blur-xl"
        aria-hidden="true"
      />

      <div
        className="absolute inset-0 rounded-[2.4rem] bg-gradient-to-br from-ink-700 via-ink-950 to-black shadow-[18px_24px_35px_rgba(2,6,23,0.42),inset_2px_2px_2px_rgba(255,255,255,0.18),inset_-3px_-3px_5px_rgba(0,0,0,0.7)] border border-white/15 p-2 transition-transform duration-500 hover:-translate-y-2"
        style={{ ...TILT_STYLES[tilt], transformStyle: 'preserve-3d' }}
      >
        <div className="absolute inset-y-8 -left-[5px] w-[5px] rounded-l-md bg-gradient-to-b from-ink-400 via-ink-800 to-black shadow-[inset_1px_0_rgba(255,255,255,0.25)]" aria-hidden="true" />
        <div className="absolute inset-y-10 -right-[5px] w-[5px] rounded-r-md bg-gradient-to-b from-ink-500 via-ink-900 to-black" aria-hidden="true" />

        <div className="absolute -left-[7px] top-24 w-[3px] h-12 rounded-l-sm bg-ink-500 shadow-[inset_1px_0_rgba(255,255,255,0.2)]" aria-hidden="true" />
        <div className="absolute -left-[7px] top-40 w-[3px] h-16 rounded-l-sm bg-ink-600" aria-hidden="true" />
        <div className="absolute -right-[7px] top-32 w-[3px] h-20 rounded-r-sm bg-ink-600" aria-hidden="true" />

        <div className="relative w-full h-full rounded-[1.8rem] overflow-hidden bg-white border border-white/20 shadow-[inset_0_0_0_1px_rgba(15,23,42,0.28)]">
          <div className="absolute inset-x-4 top-0 h-20 bg-gradient-to-b from-white/20 to-transparent z-10 pointer-events-none" aria-hidden="true" />
          <div className="absolute top-0 left-1/2 -translate-x-1/2 w-20 h-5 bg-black rounded-b-2xl z-20 shadow-[0_1px_3px_rgba(0,0,0,0.45)]">
            <div className="absolute top-1.5 left-1/2 -translate-x-1/2 w-8 h-1 rounded-full bg-ink-700" />
            <div className="absolute top-1.5 right-3 w-1.5 h-1.5 rounded-full bg-brand-400/80" />
          </div>
          {children}
        </div>
      </div>

    </div>
  );
}
