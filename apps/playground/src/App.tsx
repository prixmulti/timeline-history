import { DisplaySettings, TimelineBuilder } from '@timeline-history/timeline-builder';
import { TimelineView } from '@timeline-history/timeline-core';
import '@timeline-history/timeline-core/styles.css';
import { useEffect, useState } from 'react';
import { Link, NavLink, Route, Routes } from 'react-router-dom';
import { StoryForm } from './StoryForm';
import { exportJson, loadState, resetState, saveState, type PlaygroundState } from './storage';
import './app.css';

export default function App() {
  const [state, setState] = useState<PlaygroundState>(() => loadState());
  const [selectedId, setSelectedId] = useState<string | null>(null);

  const editing =
    selectedId != null
      ? state.stories.find((s) => s.id === selectedId) ?? null
      : null;

  useEffect(() => {
    saveState(state);
  }, [state]);

  return (
    <div className="pg-shell">
      <header className="pg-header">
        <div className="pg-brand">
          <span className="pg-brand__mark" aria-hidden="true" />
          <div>
            <div className="pg-brand__title">Timeline History</div>
            <div className="pg-brand__sub">Demo: True Beboerforening · 50 år</div>
          </div>
        </div>
        <nav className="pg-nav" aria-label="Visning">
          <NavLink to="/" end className={({ isActive }) => (isActive ? 'active' : '')}>
            Studio
          </NavLink>
          <NavLink to="/page" className={({ isActive }) => (isActive ? 'active' : '')}>
            Offentlig side
          </NavLink>
        </nav>
        <div className="pg-actions">
          <button
            type="button"
            className="pg-btn pg-btn--ghost"
            onClick={() => {
              setSelectedId(null);
              setState(resetState());
            }}
          >
            Indlæs True-demo
          </button>
          <button
            type="button"
            className="pg-btn"
            onClick={() => {
              const blob = new Blob([exportJson(state)], { type: 'application/json' });
              const url = URL.createObjectURL(blob);
              const a = document.createElement('a');
              a.href = url;
              a.download = 'timeline.json';
              a.click();
              URL.revokeObjectURL(url);
            }}
          >
            Eksporter JSON
          </button>
        </div>
      </header>
      <Routes>
        <Route
          path="/"
          element={
            <main className="pg-studio">
              <aside className="pg-studio__aside">
                <div className="pg-card">
                  <div className="pg-card__head">
                    <h2>Indhold</h2>
                    <span className="pg-pill">
                      {state.stories.length}{' '}
                      {state.stories.length === 1 ? 'historie' : 'historier'}
                    </span>
                  </div>
                  <StoryForm
                    editing={editing}
                    onAdd={(story) =>
                      setState((s) => ({ ...s, stories: [...s.stories, story] }))
                    }
                    onUpdate={(story) =>
                      setState((s) => ({
                        ...s,
                        stories: s.stories.map((x) => (x.id === story.id ? story : x)),
                      }))
                    }
                    onCancelEdit={() => setSelectedId(null)}
                    onDelete={(id) => {
                      setState((s) => ({
                        ...s,
                        stories: s.stories.filter((x) => x.id !== id),
                      }));
                      setSelectedId(null);
                    }}
                  />
                </div>

                <div className="pg-card pg-card--settings">
                  <div className="pg-card__head">
                    <h2>Visning</h2>
                  </div>
                  <DisplaySettings
                    compact
                    config={state.config}
                    onChange={(config) => setState((s) => ({ ...s, config }))}
                  />
                </div>

                <p className="pg-hint">
                  Klik på et kort i forhåndsvisningen for at redigere eller slette
                  historier. Ændringer gemmes lokalt i browseren.
                </p>
              </aside>
              <section className="pg-studio__main" aria-label="Forhåndsvisning">
                <TimelineBuilder
                  previewOnly
                  config={state.config}
                  stories={state.stories}
                  selectedStoryId={selectedId}
                  onSelectStory={(id) => setSelectedId(id)}
                  onConfigChange={(config) => setState((s) => ({ ...s, config }))}
                />
              </section>
            </main>
          }
        />
        <Route
          path="/page"
          element={
            <main className="pg-front">
              <div className="pg-front__chrome">
                <span className="pg-front__url">eksempel.dk/vores-historie</span>
              </div>
              <article className="pg-article">
                <p className="pg-breadcrumb">
                  <Link to="/">← Tilbage til studio</Link>
                </p>
                <p className="pg-article__kicker">True Beboerforening</p>
                <h1>Tidslinie – 50 år</h1>
                <p className="pg-lead">
                  Udsnit af den officielle landsbyhistorie fra{' '}
                  <a
                    href="https://www.true-beboerforening.dk/tidslinie/"
                    target="_blank"
                    rel="noreferrer"
                  >
                    true-beboerforening.dk
                  </a>
                  . Sådan kan tidslinjen se ud på en WordPress-side.
                </p>
                <div className="th-embed-sim">
                  <TimelineView
                    config={state.config}
                    stories={state.stories}
                    imageLightbox
                  />
                </div>
              </article>
            </main>
          }
        />
      </Routes>
    </div>
  );
}
