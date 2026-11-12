import {
  defaultConfig,
  type TimelineConfig,
  type TimelineStory,
} from '@timeline-history/timeline-core';
import { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { DisplaySettings } from './DisplaySettings';
import { StoryForm } from './StoryForm';
import { TimelineBuilder } from './TimelineBuilder';

export type TimelineSet = {
  id: number;
  name: string;
  slug: string;
  count?: number;
};

export type AdminStudioApi = {
  restBase: string;
  nonce: string;
};

async function apiFetch<T>(
  api: AdminStudioApi,
  path: string,
  init?: RequestInit
): Promise<T> {
  const res = await fetch(`${api.restBase.replace(/\/$/, '')}${path}`, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': api.nonce,
      ...(init?.headers || {}),
    },
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    const msg =
      (data && (data.message || data.code)) || `HTTP ${res.status}`;
    throw new Error(String(msg));
  }
  return data as T;
}

export function AdminStudio({
  api,
  initialConfig,
  initialStories = [],
  initialTimelines = [],
  initialTimelineSlug = '',
}: {
  api: AdminStudioApi;
  initialConfig?: TimelineConfig;
  initialStories?: TimelineStory[];
  initialTimelines?: TimelineSet[];
  initialTimelineSlug?: string;
}) {
  const [config, setConfig] = useState<TimelineConfig>(
    () => initialConfig ?? defaultConfig()
  );
  const [timelines, setTimelines] = useState<TimelineSet[]>(initialTimelines);
  const [timelineSlug, setTimelineSlug] = useState<string>(() => {
    const pick = initialTimelineSlug.trim();
    if (pick) {
      return pick;
    }
    return initialTimelines[0]?.slug ?? '';
  });
  const [newTimelineName, setNewTimelineName] = useState('');
  const [newTimelineOpen, setNewTimelineOpen] = useState(false);
  const [stories, setStories] = useState<TimelineStory[]>(initialStories);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [status, setStatus] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [copyShortcodeLabel, setCopyShortcodeLabel] = useState('Kopiér');
  const [gearMount, setGearMount] = useState<HTMLElement | null>(null);
  const saveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const copyTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const activeTimeline =
    timelines.find((t) => t.slug === timelineSlug) ?? null;

  const editing =
    selectedId != null
      ? stories.find((s) => s.id === selectedId) ?? null
      : null;

  const flash = (msg: string) => {
    setStatus(msg);
    window.setTimeout(() => setStatus(null), 2200);
  };

  const loadStories = useCallback(
    async (slug: string) => {
      const qs = slug ? `?timeline=${encodeURIComponent(slug)}` : '';
      const data = await apiFetch<{
        stories: TimelineStory[];
        timelines?: TimelineSet[];
        config?: TimelineConfig;
      }>(api, `/stories${qs}`);
      setStories(data.stories || []);
      if (data.timelines) setTimelines(data.timelines);
      if (data.config) setConfig(data.config);
      setSelectedId(null);
    },
    [api]
  );

  useEffect(() => {
    if (!timelineSlug && timelines.length > 0) {
      setTimelineSlug(timelines[0].slug);
      return;
    }
    if (!timelineSlug) return;
    loadStories(timelineSlug).catch((err) =>
      setError(err instanceof Error ? err.message : String(err))
    );
  }, [timelineSlug]); // eslint-disable-line react-hooks/exhaustive-deps

  const persistConfig = useCallback(
    (next: TimelineConfig) => {
      if (saveTimer.current) clearTimeout(saveTimer.current);
      saveTimer.current = setTimeout(async () => {
        try {
          const data = await apiFetch<{ config: TimelineConfig }>(
            api,
            '/settings',
            {
              method: 'POST',
              body: JSON.stringify({ config: next }),
            }
          );
          setConfig(data.config);
          flash('Visning gemt');
        } catch (err) {
          setError(err instanceof Error ? err.message : String(err));
        }
      }, 450);
    },
    [api]
  );

  useEffect(() => {
    return () => {
      if (saveTimer.current) clearTimeout(saveTimer.current);
      if (copyTimer.current) clearTimeout(copyTimer.current);
    };
  }, []);

  useEffect(() => {
    setGearMount(document.getElementById('th-studio-gear-mount'));
  }, []);

  const activeShortcode = activeTimeline
    ? `[timeline_history timeline="${activeTimeline.slug}"]`
    : '';

  const copyActiveShortcode = async () => {
    if (!activeShortcode) return;
    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(activeShortcode);
      } else {
        window.prompt('Kopiér shortcode:', activeShortcode);
        return;
      }
      setCopyShortcodeLabel('Kopieret!');
      if (copyTimer.current) clearTimeout(copyTimer.current);
      copyTimer.current = setTimeout(() => setCopyShortcodeLabel('Kopiér'), 1600);
    } catch {
      setError('Kunne ikke kopiere shortcode.');
    }
  };

  const handleConfig = (next: TimelineConfig) => {
    setConfig(next);
    setError(null);
    persistConfig(next);
  };

  const withTimeline = (story: TimelineStory) => ({
    ...story,
    timeline: timelineSlug || undefined,
  });

  const handleAdd = async (story: TimelineStory) => {
    if (!timelineSlug) {
      setError('Vælg eller opret en tidslinje først.');
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const data = await apiFetch<{ story: TimelineStory }>(api, '/stories', {
        method: 'POST',
        body: JSON.stringify(withTimeline(story)),
      });
      setStories((prev) =>
        [...prev, data.story].sort((a, b) => a.date.localeCompare(b.date))
      );
      flash('Historie tilføjet');
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    } finally {
      setBusy(false);
    }
  };

  const handleUpdate = async (story: TimelineStory) => {
    setBusy(true);
    setError(null);
    try {
      const data = await apiFetch<{ story: TimelineStory }>(
        api,
        `/stories/${encodeURIComponent(story.id)}`,
        {
          method: 'PUT',
          body: JSON.stringify(withTimeline(story)),
        }
      );
      setStories((prev) =>
        prev
          .map((s) => (s.id === story.id ? data.story : s))
          .sort((a, b) => a.date.localeCompare(b.date))
      );
      setSelectedId(null);
      flash('Historie gemt');
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    } finally {
      setBusy(false);
    }
  };

  const handleDelete = async (id: string) => {
    const story = stories.find((s) => s.id === id);
    const label = story?.title ? `“${story.title.slice(0, 80)}”` : 'historien';
    if (
      !window.confirm(
        `Slet ${label} permanent? Handlingen kan ikke fortrydes.`
      )
    ) {
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await apiFetch(api, `/stories/${encodeURIComponent(id)}`, {
        method: 'DELETE',
      });
      setStories((prev) => prev.filter((s) => s.id !== id));
      setSelectedId(null);
      flash('Historie slettet');
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    } finally {
      setBusy(false);
    }
  };

  const createTimeline = async () => {
    const name = newTimelineName.trim();
    if (!name) return;
    setBusy(true);
    setError(null);
    try {
      const data = await apiFetch<{ timeline: TimelineSet }>(api, '/timelines', {
        method: 'POST',
        body: JSON.stringify({ name }),
      });
      setTimelines((prev) => {
        const exists = prev.some((t) => t.slug === data.timeline.slug);
        return exists ? prev : [...prev, data.timeline];
      });
      setTimelineSlug(data.timeline.slug);
      setNewTimelineName('');
      setNewTimelineOpen(false);
      flash('Tidslinje oprettet');
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="th-studio">
      <aside className="th-studio__aside">
        <div className="th-studio__card">
          <div className="th-studio__card-head">
            <h2>Tidslinje</h2>
          </div>
          <div className="th-sf-field">
            <span className="th-sf-field__label">Aktiv tidslinje</span>
            <div className="th-studio__timeline-row">
              <select
                className="th-studio__timeline-select"
                value={timelineSlug}
                onChange={(e) => setTimelineSlug(e.target.value)}
                disabled={busy || timelines.length === 0}
              >
                {timelines.length === 0 ? (
                  <option value="">Ingen tidslinjer endnu</option>
                ) : null}
                {timelines.map((t) => (
                  <option key={t.slug} value={t.slug}>
                    {t.name}
                    {typeof t.count === 'number' ? ` (${t.count})` : ''}
                  </option>
                ))}
              </select>
              <button
                type="button"
                className="th-sf-btn th-sf-btn--icon"
                onClick={() => {
                  setNewTimelineName('');
                  setNewTimelineOpen(true);
                }}
                disabled={busy}
                title="Opret ny tidslinje"
                aria-label="Opret ny tidslinje"
              >
                +
              </button>
            </div>
          </div>
          {activeTimeline ? (
            <div
              className="th-studio__shortcode th-studio__shortcode--compact"
              title={activeShortcode}
            >
              <span className="th-studio__shortcode-label">Shortcode</span>
              <code className="th-studio__embed-code th-studio__embed-code--inline">
                {activeShortcode}
              </code>
              <button
                type="button"
                className="th-sf-btn th-sf-btn--copy"
                onClick={() => void copyActiveShortcode()}
                disabled={busy}
              >
                {copyShortcodeLabel}
              </button>
            </div>
          ) : (
            <p className="th-studio__hint">
              Opret en tidslinje, eller importer fra Arkiv-import med et navn.
            </p>
          )}
        </div>

        <div className="th-studio__card">
          <div className="th-studio__card-head">
            <h2>Indhold</h2>
            <span className="th-studio__pill">
              {stories.length} {stories.length === 1 ? 'historie' : 'historier'}
            </span>
          </div>
          <StoryForm
            editing={editing}
            busy={busy || !timelineSlug}
            useWpMedia
            onAdd={handleAdd}
            onUpdate={handleUpdate}
            onCancelEdit={() => setSelectedId(null)}
            onDelete={handleDelete}
          />
        </div>

        {error ? <p className="th-studio__error">{error}</p> : null}
      </aside>

      <section className="th-studio__main" aria-label="Forhåndsvisning">
        <TimelineBuilder
          previewOnly
          config={config}
          stories={stories}
          selectedStoryId={selectedId}
          onSelectStory={(id) => setSelectedId(id)}
          onDeleteStory={(id) => {
            if (!busy) void handleDelete(id);
          }}
          onConfigChange={handleConfig}
        />
      </section>
      {newTimelineOpen
        ? createPortal(
            <div
              className="th-studio-modal"
              role="presentation"
              onClick={() => !busy && setNewTimelineOpen(false)}
              onKeyDown={(e) => {
                if (e.key === 'Escape' && !busy) setNewTimelineOpen(false);
              }}
            >
              <div
                className="th-studio-modal__panel"
                role="dialog"
                aria-modal="true"
                aria-labelledby="th-new-timeline-title"
                onClick={(e) => e.stopPropagation()}
              >
                <h3 id="th-new-timeline-title" className="th-studio-modal__title">
                  Ny tidslinje
                </h3>
                <label className="th-sf-field" style={{ marginBottom: '1rem' }}>
                  <span className="th-sf-field__label">Navn</span>
                  <input
                    type="text"
                    autoFocus
                    value={newTimelineName}
                    onChange={(e) => setNewTimelineName(e.target.value)}
                    placeholder="Fx. Ansager 1940–1942"
                    disabled={busy}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') {
                        e.preventDefault();
                        void createTimeline();
                      }
                    }}
                  />
                </label>
                <div className="th-studio-modal__actions">
                  <button
                    type="button"
                    className="th-link-btn"
                    onClick={() => setNewTimelineOpen(false)}
                    disabled={busy}
                  >
                    Annuller
                  </button>
                  <button
                    type="button"
                    className="th-sf-btn"
                    onClick={() => void createTimeline()}
                    disabled={busy || !newTimelineName.trim()}
                  >
                    Opret
                  </button>
                </div>
              </div>
            </div>,
            document.body
          )
        : null}
      {gearMount
        ? createPortal(
            <div className="th-studio-gear-panel">
              <div className="th-studio-gear-panel__head">
                <h2>Visning</h2>
                {status ? <span className="th-studio__status">{status}</span> : null}
              </div>
              <DisplaySettings compact config={config} onChange={handleConfig} />
            </div>,
            gearMount
          )
        : null}
    </div>
  );
}
