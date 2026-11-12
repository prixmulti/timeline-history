import {
  TimelineView,
  type TimelineConfig,
  type TimelineStory,
  defaultConfig,
} from '@timeline-history/timeline-core';
import { useEffect, useState } from 'react';
import { DisplaySettings } from './DisplaySettings';
import './styles.css';

export interface TimelineBuilderProps {
  config?: TimelineConfig;
  stories: TimelineStory[];
  onConfigChange?: (config: TimelineConfig) => void;
  selectedStoryId?: string | null;
  onSelectStory?: (storyId: string) => void;
  onDeleteStory?: (storyId: string) => void;
  /** Vis kun preview (design-panel ligger udenfor). Standard true i Studio. */
  previewOnly?: boolean;
}

export function TimelineBuilder({
  config: configProp,
  stories,
  onConfigChange,
  selectedStoryId = null,
  onSelectStory,
  onDeleteStory,
  previewOnly = true,
}: TimelineBuilderProps) {
  const [config, setConfig] = useState<TimelineConfig>(
    () => configProp ?? defaultConfig()
  );

  useEffect(() => {
    if (configProp) setConfig(configProp);
  }, [configProp]);

  const handleConfig = (next: TimelineConfig) => {
    setConfig(next);
    onConfigChange?.(next);
  };

  return (
    <div
      className={
        'th-builder th-builder--studio' +
        (previewOnly ? ' th-builder--preview-only' : '')
      }
    >
      <div className="th-builder__preview-wrap">
        <div className="th-builder__preview-bar">
          <span className="th-builder__live">
            <span className="th-builder__live-dot" aria-hidden="true" />
            Live forhåndsvisning
          </span>
        </div>
        <div className="th-builder__preview">
          <TimelineView
            config={config}
            stories={stories}
            interactive={!!onSelectStory}
            selectedStoryId={selectedStoryId}
            onStorySelect={onSelectStory}
            onStoryDelete={onDeleteStory}
            imageLightbox={false}
          />
        </div>
      </div>

      {!previewOnly ? (
        <aside className="th-builder__panel">
          <DisplaySettings config={config} onChange={handleConfig} />
        </aside>
      ) : null}
    </div>
  );
}

export { DisplaySettings } from './DisplaySettings';
