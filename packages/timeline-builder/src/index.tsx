export { TimelineBuilder } from './TimelineBuilder';
export type { TimelineBuilderProps } from './TimelineBuilder';
export { DisplaySettings } from './DisplaySettings';
export { StoryForm } from './StoryForm';
export { AdminStudio } from './AdminStudio';
export type { AdminStudioApi, TimelineSet } from './AdminStudio';
export { openWpMediaLibrary, canOpenWpMedia } from './wpMedia';

import { createRoot } from 'react-dom/client';
import {
  defaultConfig,
  type TimelineConfig,
  type TimelineStory,
} from '@timeline-history/timeline-core';
import { AdminStudio, type TimelineSet } from './AdminStudio';

/** Mount WP admin Studio on a DOM node. */
export function mountAdminBuilder(
  el: HTMLElement,
  options?: {
    stories?: TimelineStory[];
    config?: TimelineConfig;
    timelines?: TimelineSet[];
    restBase?: string;
    nonce?: string;
    onConfigChange?: (c: TimelineConfig) => void;
  }
): void {
  const root = createRoot(el);
  root.render(
    <AdminStudio
      api={{
        restBase: options?.restBase || '',
        nonce: options?.nonce || '',
      }}
      initialConfig={options?.config ?? defaultConfig()}
      initialStories={options?.stories ?? []}
      initialTimelines={options?.timelines ?? []}
    />
  );
}
