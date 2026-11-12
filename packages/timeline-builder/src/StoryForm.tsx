import type { TimelineStory } from '@timeline-history/timeline-core';
import { storyImages, withImages } from '@timeline-history/timeline-core';
import { useEffect, useState } from 'react';
import { openWpMediaLibrary } from './wpMedia';

const empty = (): TimelineStory => ({
  id: '',
  date: new Date().toISOString().slice(0, 10),
  title: '',
  excerpt: '',
  bemaerkning: '',
  images: [],
});

export function StoryForm({
  editing,
  busy = false,
  useWpMedia = false,
  onAdd,
  onUpdate,
  onCancelEdit,
  onDelete,
}: {
  editing: TimelineStory | null;
  busy?: boolean;
  /** When true, + opens WP media library (no external URL input). */
  useWpMedia?: boolean;
  onAdd: (story: TimelineStory) => void | Promise<void>;
  onUpdate: (story: TimelineStory) => void | Promise<void>;
  onCancelEdit: () => void;
  onDelete?: (id: string) => void | Promise<void>;
}) {
  const [draft, setDraft] = useState<TimelineStory>(empty);
  const [addingUrl, setAddingUrl] = useState(false);
  const [urlDraft, setUrlDraft] = useState('');
  const [mediaError, setMediaError] = useState<string | null>(null);
  const isEdit = editing != null;
  const images = storyImages(draft);

  useEffect(() => {
    if (editing) {
      setDraft(withImages(editing, storyImages(editing)));
    } else {
      setDraft(empty());
    }
    setAddingUrl(false);
    setUrlDraft('');
    setMediaError(null);
  }, [editing]);

  const setImages = (next: string[]) => {
    setDraft((d) => withImages(d, next));
  };

  const addImageFromUrl = () => {
    const url = urlDraft.trim();
    if (!url) return;
    setImages([...images, url]);
    setUrlDraft('');
    setAddingUrl(false);
  };

  const pickFromLibrary = async () => {
    setMediaError(null);
    try {
      const urls = await openWpMediaLibrary({
        multiple: true,
        title: 'Vælg billeder til historien',
        buttonText: 'Tilføj til historie',
      });
      if (!urls.length) return;
      const merged = [...images];
      for (const url of urls) {
        if (!merged.includes(url)) merged.push(url);
      }
      setImages(merged);
    } catch (err) {
      setMediaError(err instanceof Error ? err.message : String(err));
    }
  };

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!draft.title.trim() || busy) return;
    const payload = withImages(draft, storyImages(draft));
    if (isEdit) {
      await onUpdate({ ...payload, id: editing.id });
    } else {
      await onAdd({ ...payload, id: '' });
      setDraft(empty());
    }
  };

  return (
    <form className="th-story-form" onSubmit={submit}>
      {isEdit ? (
        <p className="th-form-mode">
          <button type="button" className="th-link-btn" onClick={onCancelEdit} disabled={busy}>
            Annuller redigering
          </button>
        </p>
      ) : (
        <p className="th-form-mode th-form-mode--muted">Ny historie</p>
      )}
      <label className="th-sf-field">
        <span className="th-sf-field__label">Titel</span>
        <input
          placeholder="Fx. Grundlagt, nyt kapitel, milepæl"
          value={draft.title}
          onChange={(e) => setDraft({ ...draft, title: e.target.value })}
          required
          disabled={busy}
        />
      </label>
      <label className="th-sf-field">
        <span className="th-sf-field__label">Dato</span>
        <input
          type="date"
          value={(draft.date || '').slice(0, 10)}
          onChange={(e) => setDraft({ ...draft, date: e.target.value })}
          disabled={busy}
        />
      </label>
      <label className="th-sf-field">
        <span className="th-sf-field__label">Beskrivelse</span>
        <textarea
          placeholder="Tekst fra arkiv.dk (Beskrivelse) eller egen brødtekst på kortet."
          value={draft.excerpt ?? ''}
          onChange={(e) => setDraft({ ...draft, excerpt: e.target.value })}
          rows={3}
          disabled={busy}
        />
      </label>
      <label className="th-sf-field">
        <span className="th-sf-field__label">Bemærkning</span>
        <textarea
          placeholder="Fra arkiv.dk – vises når besøgende åbner billedet i popup."
          value={draft.bemaerkning ?? ''}
          onChange={(e) => setDraft({ ...draft, bemaerkning: e.target.value })}
          rows={2}
          disabled={busy}
        />
      </label>

      <div className="th-sf-field">
        <span className="th-sf-field__label">Billeder</span>
        <p className="th-sf-field__hint">
          {useWpMedia
            ? 'Vælg eller upload fra WordPress-mediebiblioteket. Flere billeder = slideshow.'
            : 'Flere billeder = slideshow.'}
        </p>
        <div className="th-thumbs" role="list">
          {images.map((src, i) => (
            <div key={`${src}-${i}`} className="th-thumb" role="listitem">
              <img src={src} alt="" />
              <button
                type="button"
                className="th-thumb__remove"
                aria-label={`Fjern billede ${i + 1}`}
                onClick={() => setImages(images.filter((_, j) => j !== i))}
                disabled={busy}
              >
                ×
              </button>
            </div>
          ))}
          {useWpMedia ? (
            <button
              type="button"
              className="th-thumb th-thumb--add"
              onClick={pickFromLibrary}
              aria-label="Tilføj billede fra mediebibliotek"
              title="Åbn WordPress-mediebibliotek"
              disabled={busy}
            >
              <span aria-hidden="true">+</span>
            </button>
          ) : addingUrl ? (
            <div className="th-thumb th-thumb--add-form">
              <input
                type="url"
                autoFocus
                placeholder="https://"
                value={urlDraft}
                onChange={(e) => setUrlDraft(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault();
                    addImageFromUrl();
                  }
                  if (e.key === 'Escape') {
                    setAddingUrl(false);
                    setUrlDraft('');
                  }
                }}
                disabled={busy}
              />
              <div className="th-thumb__add-actions">
                <button type="button" className="th-link-btn" onClick={addImageFromUrl}>
                  Tilføj
                </button>
                <button
                  type="button"
                  className="th-link-btn"
                  onClick={() => {
                    setAddingUrl(false);
                    setUrlDraft('');
                  }}
                >
                  Annuller
                </button>
              </div>
            </div>
          ) : (
            <button
              type="button"
              className="th-thumb th-thumb--add"
              onClick={() => setAddingUrl(true)}
              aria-label="Tilføj billede"
              disabled={busy}
            >
              <span aria-hidden="true">+</span>
            </button>
          )}
        </div>
        {mediaError ? <p className="th-sf-field__error">{mediaError}</p> : null}
      </div>

      <button type="submit" className="th-sf-btn th-sf-btn--block" disabled={busy}>
        {busy ? 'Gemmer…' : isEdit ? 'Gem ændringer' : 'Tilføj historie'}
      </button>
      {isEdit && onDelete ? (
        <button
          type="button"
          className="th-sf-btn th-sf-btn--danger th-sf-btn--block"
          disabled={busy}
          onClick={() => {
            void onDelete(editing.id);
          }}
        >
          Slet historie
        </button>
      ) : null}
    </form>
  );
}
