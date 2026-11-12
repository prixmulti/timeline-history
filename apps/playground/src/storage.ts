import type { TimelineConfig, TimelineStory } from '@timeline-history/timeline-core';
import { trueBeboerforeningPayload } from '../../../packages/timeline-core/src/fixtures/true-beboerforening';

/** Bump når standard-demo skiftes. */
const KEY = 'timeline-history-playground-v4';

const seed = (): PlaygroundState => ({
  config: { ...trueBeboerforeningPayload.config },
  stories: [...trueBeboerforeningPayload.stories],
});

export interface PlaygroundState {
  config: TimelineConfig;
  stories: TimelineStory[];
}

function isLegacyPlaceholder(stories: TimelineStory[]): boolean {
  return (
    stories.length <= 4 &&
    stories.some((s) => s.id === '1' && s.title === 'Grundlagt')
  );
}

export function loadState(): PlaygroundState {
  try {
    const raw = localStorage.getItem(KEY);
    if (raw) {
      const parsed = JSON.parse(raw) as PlaygroundState;
      if (!isLegacyPlaceholder(parsed.stories)) {
        return parsed;
      }
    }
  } catch {
    /* ignore */
  }
  const fresh = seed();
  saveState(fresh);
  return fresh;
}

export function saveState(state: PlaygroundState): void {
  localStorage.setItem(KEY, JSON.stringify(state));
}

export function resetState(): PlaygroundState {
  const fresh = seed();
  saveState(fresh);
  return fresh;
}

export function exportJson(state: PlaygroundState): string {
  return JSON.stringify({ config: state.config, stories: state.stories }, null, 2);
}
