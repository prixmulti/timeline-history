import { memo, useCallback, useState, type CSSProperties } from 'react';
import { createPortal } from 'react-dom';
import { ImageLightbox } from './ImageLightbox';
import { storyImageCaptions, storyImages } from './media';
import { limitStories, storyYear } from './sort';
import { StoryMedia } from './StoryMedia';
import { cssVariables } from './themes';
import type { TimelineConfig, TimelineStory } from './types';

export interface TimelineViewProps {
  config: TimelineConfig;
  stories: TimelineStory[];
  className?: string;
  interactive?: boolean;
  selectedStoryId?: string | null;
  onStorySelect?: (storyId: string) => void;
  /** Studio: slet historie direkte fra kortet. */
  onStoryDelete?: (storyId: string) => void;
  imageLightbox?: boolean;
}

function storyBody(story: TimelineStory): string | undefined {
  const raw = (story.excerpt || story.content || '').trim();
  if (!raw) return undefined;
  // Hide duplicate body when it only repeats the title (common after old imports).
  const title = (story.title || '').trim();
  if (title && raw.localeCompare(title, undefined, { sensitivity: 'accent' }) === 0) {
    return undefined;
  }
  return raw;
}

const StoryCard = memo(function StoryCard({
  story,
  config,
  index,
  side,
  interactive,
  selected,
  onSelect,
  onDelete,
  imageLightbox,
  onImageOpen,
  yearLabel,
}: {
  story: TimelineStory;
  config: TimelineConfig;
  index: number;
  side?: 'left' | 'right';
  interactive?: boolean;
  selected?: boolean;
  onSelect?: (id: string) => void;
  onDelete?: (id: string) => void;
  imageLightbox?: boolean;
  onImageOpen?: (story: TimelineStory, imageIndex: number) => void;
  /** Årstal ved year-jump – vises ud for bullet, modsat kortet */
  yearLabel?: number | null;
}) {
  const anim = config.animation === 'fade-in' ? 'th-fade-in' : undefined;
  const theme = config.theme;
  const accent = story.accentColor;
  const images = storyImages(story);
  const captions = storyImageCaptions(story, images.length);
  const showYear = yearLabel != null;
  /* År låses til bullet-wrap, altid på modsat side af kortet */
  const yearSide = side === 'right' ? 'left' : 'right';

  return (
    <li
      className={[
        'th-story',
        `th-story--${side ?? 'center'}`,
        theme ? `th-story--theme-${theme}` : '',
        showYear ? 'th-story--year-jump' : '',
        story.icon ? 'th-story--has-icon' : '',
        anim,
      ]
        .filter(Boolean)
        .join(' ')}
      style={
        {
          '--th-story-accent': accent || 'var(--th-accent)',
          animationDelay: anim ? `${index * 80}ms` : undefined,
        } as CSSProperties
      }
    >
      {showYear ? (
        <div
          className={`th-story__year th-story__year--${yearSide}`}
          aria-label={`År ${yearLabel}`}
        >
          {yearLabel}
        </div>
      ) : null}
      <div className="th-story__marker-wrap">
        <div className="th-story__marker" aria-hidden="true">
          {story.icon ? (
            <span className="th-story__icon">{story.icon}</span>
          ) : (
            <span className="th-story__dot" />
          )}
        </div>
      </div>
      <article
        className={[
          'th-story__card',
          interactive ? 'th-story__card--interactive' : '',
          selected ? 'th-story__card--selected' : '',
        ]
          .filter(Boolean)
          .join(' ')}
        onClick={
          interactive && onSelect
            ? (e) => {
                e.stopPropagation();
                onSelect(story.id);
              }
            : undefined
        }
        onKeyDown={
          interactive && onSelect
            ? (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault();
                  onSelect(story.id);
                }
              }
            : undefined
        }
        role={interactive ? 'button' : undefined}
        tabIndex={interactive ? 0 : undefined}
        aria-pressed={interactive ? selected : undefined}
      >
        {onDelete ? (
          <button
            type="button"
            className="th-story__delete"
            aria-label={`Slet: ${story.title}`}
            title="Slet historie"
            onClick={(e) => {
              e.stopPropagation();
              onDelete(story.id);
            }}
          >
            ×
          </button>
        ) : null}
        <h3 className="th-story__title" title={story.title}>
          {story.title}
        </h3>
        {images.length > 0 ? (
          <StoryMedia
            images={images}
            captions={captions}
            title={story.title}
            zoomable={imageLightbox}
            onOpen={(imageIndex) => onImageOpen?.(story, imageIndex)}
          />
        ) : null}
        {storyBody(story) ? (
          <p className="th-story__excerpt">{storyBody(story)}</p>
        ) : null}
        {story.sourceUrl ? (
          <p className="th-story__excerpt th-story__excerpt--link">
            <a
              className="th-story__source"
              href={story.sourceUrl}
              target="_blank"
              rel="noopener noreferrer"
              onClick={(e) => e.stopPropagation()}
            >
              Åbn på arkiv.dk
            </a>
          </p>
        ) : null}
      </article>
    </li>
  );
});

function yearJumpFor(
  items: TimelineStory[],
  index: number
): number | null {
  const year = storyYear(items[index].date);
  if (year == null) return null;
  if (index === 0) return year;
  const prev = storyYear(items[index - 1].date);
  return prev !== year ? year : null;
}

export function TimelineView({
  config,
  stories,
  className,
  interactive,
  selectedStoryId,
  onStorySelect,
  onStoryDelete,
  imageLightbox = !interactive,
}: TimelineViewProps) {
  const [lightbox, setLightbox] = useState<{
    story: TimelineStory;
    index: number;
  } | null>(null);

  const closeLightbox = useCallback(() => setLightbox(null), []);

  const openLightbox = useCallback((story: TimelineStory, imageIndex: number) => {
    setLightbox({ story, index: imageIndex });
  }, []);

  const lightboxIndexChange = useCallback((index: number) => {
    setLightbox((prev) => (prev ? { ...prev, index } : null));
  }, []);

  const items = limitStories(stories, config.maxStories);
  const vars = cssVariables(config);
  const isHorizontal = config.layout === 'horizontal';

  const rootClass = [
    'th-root',
    `th-root--${config.layout}`,
    `th-root--theme-${config.theme}`,
    `th-root--skin-${config.skin}`,
    className,
  ]
    .filter(Boolean)
    .join(' ');

  const lightboxImages = lightbox
    ? (() => {
        const urls = storyImages(lightbox.story);
        const caps = storyImageCaptions(lightbox.story, urls.length);
        return urls.map((src, i) => ({
          src,
          alt: lightbox.story.title,
          caption: caps[i] || undefined,
        }));
      })()
    : [];

  return (
    <div
      className={rootClass}
      style={vars as CSSProperties}
      data-th-layout={config.layout}
      data-th-line-style={config.lineStyle}
    >
      <div className="th-rail" aria-hidden="true" />
      {isHorizontal ? (
        <div className="th-horizontal-scroll" role="region" aria-label="Timeline">
          <ol className="th-track th-track--horizontal">
            {items.map((story, i) => (
              <StoryCard
                key={story.id}
                story={story}
                config={config}
                index={i}
                interactive={interactive}
                selected={selectedStoryId === story.id}
                onSelect={onStorySelect}
                onDelete={onStoryDelete}
                imageLightbox={imageLightbox}
                yearLabel={yearJumpFor(items, i)}
                onImageOpen={openLightbox}
              />
            ))}
          </ol>
        </div>
      ) : (
        <ol className="th-track th-track--vertical">
          {items.map((story, i) => (
            <StoryCard
              key={story.id}
              story={story}
              config={config}
              index={i}
              side={i % 2 === 0 ? 'left' : 'right'}
              interactive={interactive}
              selected={selectedStoryId === story.id}
              onSelect={onStorySelect}
              onDelete={onStoryDelete}
              imageLightbox={imageLightbox}
              yearLabel={yearJumpFor(items, i)}
              onImageOpen={openLightbox}
            />
          ))}
        </ol>
      )}
      {imageLightbox &&
      lightbox &&
      lightboxImages.length > 0 &&
      typeof document !== 'undefined'
        ? createPortal(
            <ImageLightbox
              images={lightboxImages}
              index={lightbox.index}
              onClose={closeLightbox}
              title={lightbox.story.title}
              beskrivelse={storyBody(lightbox.story)}
              bemaerkning={lightbox.story.bemaerkning}
              onIndexChange={lightboxIndexChange}
            />,
            document.body
          )
        : null}
    </div>
  );
}
