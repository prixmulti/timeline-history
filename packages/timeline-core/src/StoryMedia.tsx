import { useEffect, useState } from 'react';

export function StoryMedia({
  images,
  captions,
  title,
  zoomable,
  onOpen,
}: {
  images: string[];
  /** Samme længde som `images` (kan være tomme strenge). */
  captions?: string[];
  title: string;
  zoomable?: boolean;
  onOpen?: (index: number) => void;
}) {
  const [index, setIndex] = useState(0);
  const multi = images.length > 1;

  useEffect(() => {
    setIndex(0);
  }, [images.join('|')]);

  if (!images[0]) return null;

  const captionList = captions ?? [];
  const activeCaption = (captionList[index] ?? '').trim();

  const body = (
    <>
      <div
        className={
          'th-story__slides' + (multi ? ' th-story__slides--multi' : '')
        }
      >
        {images.map((src, i) => (
          <img
            key={src}
            src={src}
            alt=""
            decoding="async"
            loading={i === 0 ? 'eager' : 'lazy'}
            className={
              'th-story__slide' +
              (multi && i === index ? ' th-story__slide--active' : '') +
              (multi && i !== index ? ' th-story__slide--hidden' : '')
            }
            aria-hidden={multi ? i !== index : undefined}
          />
        ))}
      </div>
      {multi && activeCaption ? (
        <p className="th-story__slide-caption">{activeCaption}</p>
      ) : null}
      {multi ? (
        <div className="th-story__dots" role="tablist" aria-label="Billeder i serien">
          {images.map((_, i) => (
            <button
              key={i}
              type="button"
              role="tab"
              aria-selected={i === index}
              aria-label={`Billede ${i + 1} af ${images.length}`}
              className={
                'th-story__dot-slide' +
                (i === index ? ' th-story__dot-slide--on' : '')
              }
              onClick={(e) => {
                e.stopPropagation();
                setIndex(i);
              }}
            />
          ))}
        </div>
      ) : null}
    </>
  );

  return (
    <figure
      className={
        'th-story__media' +
        (zoomable ? ' th-story__media--zoomable' : '') +
        (multi ? ' th-story__media--slideshow' : '')
      }
    >
      {zoomable ? (
        <button
          type="button"
          className="th-story__media-btn"
          onClick={(e) => {
            e.stopPropagation();
            onOpen?.(index);
          }}
          aria-label={`Forstør billede: ${title}`}
        >
          {body}
        </button>
      ) : (
        <div className="th-story__media-frame">{body}</div>
      )}
    </figure>
  );
}
