import type { TimelineStory } from '@timeline-history/timeline-core';
import { storyImages, withImages } from '@timeline-history/timeline-core';
import { useEffect, useState } from 'react';

const empty = (): TimelineStory => ({
  id: crypto.randomUUID(),
  date: new Date().toISOString().slice(0, 10),
  title: '',
  excerpt: '',
  images: [],
});

export function StoryForm({
  editing,
  onAdd,
  onUpdate,
  onCancelEdit,
  onDelete,
}: {
  editing: TimelineStory | null;
  onAdd: (story: TimelineStory) => void;
  onUpdate: (story: TimelineStory) => void;
  onCancelEdit: () => void;
  onDelete?: (id: string) => void;
}) {
  const [draft, setDraft] = useState<TimelineStory>(empty);
  const [addingUrl, setAddingUrl] = useState(false);
  const [urlDraft, setUrlDraft] = useState('');
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
  }, [editing]);

  const setImages = (next: string[]) => {
    setDraft((d) => withImages(d, next));
  };

  const addImage = () => {
    const url = urlDraft.trim();
    if (!url) return;
    setImages([...images, url]);
    setUrlDraft('');
    setAddingUrl(false);
  };

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!draft.title.trim()) return;
    const payload = withImages(draft, storyImages(draft));
    if (isEdit) {
      onUpdate({ ...payload, id: editing.id });
      onCancelEdit();
    } else {
      onAdd({ ...payload, id: crypto.randomUUID() });
      setDraft(empty());
    }
  };

  return (
    <form className="pg-story-form" onSubmit={submit}>
      {isEdit ? (
        <p className="pg-form-mode">
          Redigerer: <strong>{editing.title}</strong>
          <button type="button" className="pg-link-btn" onClick={onCancelEdit}>
            Annuller
          </button>
        </p>
      ) : (
        <p className="pg-form-mode pg-form-mode--muted">Ny historie</p>
      )}
      <label className="pg-field">
        <span className="pg-field__label">Dato</span>
        <input
          type="date"
          value={draft.date.slice(0, 10)}
          onChange={(e) => setDraft({ ...draft, date: e.target.value })}
        />
      </label>
      <label className="pg-field">
        <span className="pg-field__label">Titel</span>
        <input
          placeholder="Fx. Grundlagt, nyt kapitel, milepæl…"
          value={draft.title}
          onChange={(e) => setDraft({ ...draft, title: e.target.value })}
          required
        />
      </label>
      <label className="pg-field">
        <span className="pg-field__label">Brødtekst</span>
        <textarea
          placeholder="Kort beskrivelse der vises på kortet."
          value={draft.excerpt ?? ''}
          onChange={(e) => setDraft({ ...draft, excerpt: e.target.value })}
          rows={3}
        />
      </label>

      <div className="pg-field">
        <span className="pg-field__label">Billeder</span>
        <p className="pg-field__hint">
          Flere billeder = slideshow. Senere vælges fra WordPress-mediebibliotek.
        </p>
        <div className="pg-thumbs" role="list">
          {images.map((src, i) => (
            <div key={`${src}-${i}`} className="pg-thumb" role="listitem">
              <img src={src} alt="" />
              <button
                type="button"
                className="pg-thumb__remove"
                aria-label={`Fjern billede ${i + 1}`}
                onClick={() => setImages(images.filter((_, j) => j !== i))}
              >
                ×
              </button>
            </div>
          ))}
          {addingUrl ? (
            <div className="pg-thumb pg-thumb--add-form">
              <input
                type="url"
                autoFocus
                placeholder="https://…"
                value={urlDraft}
                onChange={(e) => setUrlDraft(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault();
                    addImage();
                  }
                  if (e.key === 'Escape') {
                    setAddingUrl(false);
                    setUrlDraft('');
                  }
                }}
              />
              <div className="pg-thumb__add-actions">
                <button type="button" className="pg-link-btn" onClick={addImage}>
                  Tilføj
                </button>
                <button
                  type="button"
                  className="pg-link-btn"
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
              className="pg-thumb pg-thumb--add"
              onClick={() => setAddingUrl(true)}
              aria-label="Tilføj billede"
              title="Tilføj billede (URL nu – senere WP-bibliotek)"
            >
              <span aria-hidden="true">+</span>
            </button>
          )}
        </div>
      </div>

      <div className="pg-field">
        <span className="pg-field__label">Ikon (valgfrit)</span>
        <div className="pg-icon-picks" role="group" aria-label="Ikon">
          {(
            [
              { value: '', label: 'Ingen' },
              { value: '★', label: 'Stjerne' },
              { value: '●', label: 'Punkt' },
              { value: '◆', label: 'Diamant' },
              { value: '▲', label: 'Trekant' },
              { value: '♥', label: 'Hjerte' },
            ] as const
          ).map((opt) => {
            const active = (draft.icon ?? '') === opt.value;
            return (
              <button
                key={opt.label}
                type="button"
                title={opt.label}
                aria-pressed={active}
                className={
                  'pg-icon-pick' + (active ? ' pg-icon-pick--on' : '')
                }
                onClick={() =>
                  setDraft({
                    ...draft,
                    icon: opt.value || undefined,
                  })
                }
              >
                {opt.value ? (
                  <span aria-hidden="true">{opt.value}</span>
                ) : (
                  <span className="pg-icon-pick__none" aria-hidden="true">
                    –
                  </span>
                )}
              </button>
            );
          })}
        </div>
      </div>
      <button type="submit" className="pg-btn pg-btn--block">
        {isEdit ? 'Gem ændringer' : 'Tilføj historie'}
      </button>
      {isEdit && onDelete ? (
        <button
          type="button"
          className="pg-btn pg-btn--danger pg-btn--block"
          onClick={() => {
            if (window.confirm(`Slet historie “${editing.title}”?`)) {
              onDelete(editing.id);
            }
          }}
        >
          Slet historie
        </button>
      ) : null}
    </form>
  );
}
