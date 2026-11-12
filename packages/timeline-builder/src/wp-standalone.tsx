import {
  defaultConfig,
  type TimelineConfig,
  type TimelineStory,
} from '../../timeline-core/src/index';
import { createRoot } from 'react-dom/client';
import { AdminStudio, type TimelineSet } from './AdminStudio';
import './styles.css';

export function mountAdminBuilder(
  el: HTMLElement,
  options?: {
    stories?: TimelineStory[];
    config?: TimelineConfig;
    timelines?: TimelineSet[];
    initialTimelineSlug?: string;
    restBase?: string;
    nonce?: string;
    onConfigChange?: (c: TimelineConfig) => void;
  }
): void {
  const admin =
    typeof window !== 'undefined'
      ? (window as Window & { timelineHistoryAdmin?: {
          restBase?: string;
          nonce?: string;
          defaultConfig?: TimelineConfig;
          timelines?: TimelineSet[];
        } }).timelineHistoryAdmin
      : undefined;

  const restBase = options?.restBase || admin?.restBase || '';
  const nonce = options?.nonce || admin?.nonce || '';

  const root = createRoot(el);
  root.render(
    <AdminStudio
      api={{ restBase, nonce }}
      initialConfig={options?.config ?? admin?.defaultConfig ?? defaultConfig()}
      initialStories={options?.stories ?? []}
      initialTimelines={options?.timelines ?? admin?.timelines ?? []}
      initialTimelineSlug={options?.initialTimelineSlug ?? ''}
    />
  );
}

declare global {
  interface Window {
    TimelineHistoryBuilder?: { mountAdminBuilder: typeof mountAdminBuilder };
  }
}

if (typeof window !== 'undefined') {
  window.TimelineHistoryBuilder = { mountAdminBuilder };
}
