import {
  Children,
  isValidElement,
  useCallback,
  useEffect,
  useId,
  useRef,
  useState,
  type ReactNode,
} from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

interface MobileCarouselProps {
  children: ReactNode;
  label: string;
  desktopClassName: string;
  className?: string;
  slideClassName?: string;
}

export function MobileCarousel({
  children,
  label,
  desktopClassName,
  className = '',
  slideClassName = '',
}: MobileCarouselProps) {
  const slides = Children.toArray(children);
  const trackRef = useRef<HTMLDivElement>(null);
  const trackId = useId();
  const [active, setActive] = useState(0);

  const targetFor = useCallback((index: number) => {
    const track = trackRef.current;
    const slide = track?.children[index] as HTMLElement | undefined;
    if (!track || !slide) return 0;
    const padding = parseFloat(getComputedStyle(track).paddingLeft) || 0;
    const left = track.scrollLeft + slide.getBoundingClientRect().left
      - track.getBoundingClientRect().left - padding;
    return Math.max(0, Math.min(left, track.scrollWidth - track.clientWidth));
  }, []);

  const updateActive = useCallback(() => {
    const track = trackRef.current;
    if (!track || window.innerWidth >= 768) return;
    let closest = 0;
    let distance = Infinity;
    for (let index = 0; index < track.children.length; index++) {
      const delta = Math.abs(targetFor(index) - track.scrollLeft);
      if (delta < distance) {
        closest = index;
        distance = delta;
      }
    }
    setActive(closest);
  }, [targetFor]);

  useEffect(() => {
    const track = trackRef.current;
    if (!track) return;
    const observer = new ResizeObserver(updateActive);
    observer.observe(track);
    return () => observer.disconnect();
  }, [slides.length, updateActive]);

  function goTo(index: number) {
    trackRef.current?.scrollTo({
      left: targetFor(index),
      behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
    });
  }

  return (
    <div className={`mobile-carousel ${className}`} role="region" aria-label={label}>
      <div
        id={trackId}
        ref={trackRef}
        className={`mobile-carousel-track ${desktopClassName}`}
        tabIndex={0}
        aria-label={label}
        onScroll={updateActive}
        onKeyDown={(event) => {
          if (event.target !== event.currentTarget || window.innerWidth >= 768) return;
          const destinations: Record<string, number> = {
            ArrowLeft: Math.max(0, active - 1),
            ArrowRight: Math.min(slides.length - 1, active + 1),
            Home: 0,
            End: slides.length - 1,
          };
          if (event.key in destinations) {
            event.preventDefault();
            goTo(destinations[event.key]);
          }
        }}
      >
        {slides.map((slide, index) => (
          <div
            key={isValidElement(slide) ? slide.key : index}
            className={`mobile-carousel-slide ${slideClassName}`}
            role="group"
            aria-label={`${index + 1} / ${slides.length}`}
          >
            {slide}
          </div>
        ))}
      </div>
      <div className="flex md:hidden items-center justify-between gap-2 mt-1">
        <button
          type="button"
          className="carousel-arrow"
          aria-label="Précédent"
          title="Précédent"
          aria-controls={trackId}
          disabled={active === 0}
          onClick={() => goTo(active - 1)}
        >
          <ChevronLeft size={20} aria-hidden="true" />
        </button>
        <div className="flex items-center justify-center">
          {slides.map((_, index) => (
            <button
              key={index}
              type="button"
              className="carousel-dot"
              aria-label={`Afficher l'élément ${index + 1}`}
              title={`Élément ${index + 1}`}
              aria-controls={trackId}
              aria-current={active === index ? 'true' : undefined}
              onClick={() => goTo(index)}
            >
              <span aria-hidden="true" />
            </button>
          ))}
        </div>
        <button
          type="button"
          className="carousel-arrow"
          aria-label="Suivant"
          title="Suivant"
          aria-controls={trackId}
          disabled={active === slides.length - 1}
          onClick={() => goTo(active + 1)}
        >
          <ChevronRight size={20} aria-hidden="true" />
        </button>
      </div>
      <span className="sr-only md:hidden" aria-live="polite" aria-atomic="true">{active + 1} / {slides.length}</span>
    </div>
  );
}
