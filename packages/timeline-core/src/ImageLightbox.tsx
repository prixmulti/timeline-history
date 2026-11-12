import { useEffect } from 'react';

export interface LightboxImage {
  src: string;
  alt: string;
  caption?: string;
}

export function ImageLightbox({
  images,
  index,
  onClose,
  onIndexChange,
  title,
  beskrivelse,
  bemaerkning,
}: {
  images: LightboxImage[];
  index: number;
  onClose: () => void;
  onIndexChange?: (index: number) => void;
  /** Historietitel over billedet. */
  title?: string;
  /** Beskrivelse (kort tekst) under billedet. */
  beskrivelse?: string;
  /** Arkiv Bemærkning under billedet. */
  bemaerkning?: string;
}) {
  const current = images[index];
  const multi = images.length > 1;

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
      if (!multi || !onIndexChange) return;
      if (e.key === 'ArrowRight') {
        onIndexChange((index + 1) % images.length);
      }
      if (e.key === 'ArrowLeft') {
        onIndexChange((index - 1 + images.length) % images.length);
      }
    };
    document.addEventListener('keydown', onKey);
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = prev;
    };
  }, [onClose, onIndexChange, index, images.length, multi]);

  if (!current) return null;

  const slideCaption = (current.caption || '').trim();
  const headerTitle = (title || current.alt || '').trim();
  const desc = beskrivelse?.trim() ?? '';
  const note = bemaerkning?.trim() ?? '';
  const showSeriesDesc = multi ? !slideCaption && !!desc : !!desc;

  return (
    <div
      className="th-lightbox"
      role="dialog"
      aria-modal="true"
      aria-label={current.alt}
      onClick={onClose}
    >
      <div className="th-lightbox__panel" onClick={(e) => e.stopPropagation()}>
        <button
          type="button"
          className="th-lightbox__close"
          onClick={onClose}
          aria-label="Luk billede"
        >
          ×
        </button>
        {multi && onIndexChange ? (
          <>
            <button
              type="button"
              className="th-lightbox__nav th-lightbox__nav--prev"
              aria-label="Forrige billede"
              onClick={() =>
                onIndexChange((index - 1 + images.length) % images.length)
              }
            >
              ‹
            </button>
            <button
              type="button"
              className="th-lightbox__nav th-lightbox__nav--next"
              aria-label="Næste billede"
              onClick={() => onIndexChange((index + 1) % images.length)}
            >
              ›
            </button>
          </>
        ) : null}
        <div className="th-lightbox__body">
          {headerTitle ? (
            <header className="th-lightbox__header">
              <h2 className="th-lightbox__title" id="th-lightbox-title">
                {headerTitle}
                {multi ? (
                  <span className="th-lightbox__count">
                    {' '}
                    ({index + 1}/{images.length})
                  </span>
                ) : null}
              </h2>
            </header>
          ) : null}
          <div className="th-lightbox__frame">
            {images.map((img, i) => (
              <img
                key={img.src}
                src={img.src}
                alt={img.alt}
                decoding="async"
                loading={i <= 1 ? 'eager' : 'lazy'}
                className={
                  'th-lightbox__img' +
                  (i === index
                    ? ' th-lightbox__img--active'
                    : ' th-lightbox__img--hidden')
                }
                aria-hidden={i !== index}
                aria-labelledby={i === index && headerTitle ? 'th-lightbox-title' : undefined}
              />
            ))}
          </div>
          {slideCaption || showSeriesDesc || note ? (
            <div className="th-lightbox__footer">
              {slideCaption ? (
                <div className="th-lightbox__note">
                  <span className="th-lightbox__note-label">Billedtekst</span>
                  <p className="th-lightbox__note-text">{slideCaption}</p>
                </div>
              ) : null}
              {showSeriesDesc ? (
                <div className="th-lightbox__note">
                  <span className="th-lightbox__note-label">Beskrivelse</span>
                  <p className="th-lightbox__note-text">{desc}</p>
                </div>
              ) : null}
              {note ? (
                <div className="th-lightbox__note">
                  <span className="th-lightbox__note-label">Bemærkning</span>
                  <p className="th-lightbox__note-text">{note}</p>
                </div>
              ) : null}
            </div>
          ) : null}
        </div>
      </div>
    </div>
  );
}
