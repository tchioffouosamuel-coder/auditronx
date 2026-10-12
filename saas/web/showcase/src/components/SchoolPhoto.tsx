const PHOTOS = {
  etablissement: { src: '/1.jpg', width: 5674, height: 3385 },
  lecture: { src: '/2.jpg', width: 4675, height: 3117 },
  accompagnement: { src: '/3.jpg', width: 4804, height: 3203 },
  application: { src: '/4.png', width: 1535, height: 1024 },
  direction: { src: '/5.jpg', width: 4912, height: 7360 },
};

interface SchoolPhotoProps {
  photo: keyof typeof PHOTOS;
  alt: string;
  background?: boolean;
}

export function SchoolPhoto({ photo, alt, background = false }: SchoolPhotoProps) {
  const original = PHOTOS[photo];

  return (
    <picture className={background ? 'absolute inset-0 block pointer-events-none' : 'block'}>
      <source
        type="image/webp"
        srcSet={`/images/${photo}-720.webp 720w, /images/${photo}-1440.webp 1440w`}
        sizes={background ? '100vw' : '(min-width: 1280px) 700px, (min-width: 1024px) 55vw, calc(100vw - 32px)'}
      />
      <img
        src={original.src}
        alt={alt}
        width={original.width}
        height={original.height}
        loading="lazy"
        decoding="async"
        className={background ? 'section-photo block w-full h-full object-cover' : 'block w-full h-auto'}
      />
    </picture>
  );
}
