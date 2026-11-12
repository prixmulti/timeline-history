import type { TimelineStory } from './types';

/** Samler billeder fra `images[]` eller legacy `image`. */
export function storyImages(story: TimelineStory): string[] {
  if (story.images && story.images.length > 0) {
    return story.images.filter(Boolean);
  }
  if (story.image) return [story.image];
  return [];
}

/** Billedtekster aligned med `storyImages(story)`. */
export function storyImageCaptions(
  story: TimelineStory,
  count?: number
): string[] {
  const n = count ?? storyImages(story).length;
  const raw = story.imageCaptions ?? [];
  const out: string[] = [];
  for (let i = 0; i < n; i++) {
    out.push((raw[i] ?? '').trim());
  }
  return out;
}

export function withImages(
  story: TimelineStory,
  images: string[]
): TimelineStory {
  const cleaned = images.filter(Boolean);
  return {
    ...story,
    images: cleaned,
    image: cleaned[0],
  };
}
