import type { TimelineStory } from './types';

export function sortStories(stories: TimelineStory[]): TimelineStory[] {
  return [...stories].sort((a, b) => {
    const custom = (a.order ?? 0) - (b.order ?? 0);
    if (a.order != null && b.order != null && a.order !== b.order) {
      return custom;
    }
    return new Date(a.date).getTime() - new Date(b.date).getTime();
  });
}

export function limitStories(
  stories: TimelineStory[],
  max: number
): TimelineStory[] {
  const sorted = sortStories(stories);
  if (!max || max <= 0) return sorted;
  return sorted.slice(0, max);
}

export function storyYear(iso: string): number | null {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return null;
  return d.getFullYear();
}

const DA = 'da-DK';

/**
 * Format story dates in Danish by default (fx. "29. okt. 2026").
 */
export function formatDate(iso: string, pattern: string): string {
  try {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;

    if (pattern === 'yyyy') {
      return String(d.getFullYear());
    }

    if (pattern === 'MMMM yyyy' || pattern === 'MMMM YYYY') {
      return d.toLocaleDateString(DA, { month: 'long', year: 'numeric' });
    }

    // Default card date: Danish short month (also covers legacy "MMM d, yyyy").
    return d.toLocaleDateString(DA, {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    });
  } catch {
    return iso;
  }
}
