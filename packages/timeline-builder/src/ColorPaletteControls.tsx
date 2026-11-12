import { useEffect, useId, useRef, useState } from 'react';
import {
  CURATED_PALETTES,
  NEUTRAL_PALETTE,
  type PaletteColors,
  shufflePalette,
} from './colorPalettes';

function IconReset() {
  return (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path
        d="M4 4v6h6M20 20v-6h-6M5.6 9a7 7 0 0 1 12.1-3.5M18.4 15a7 7 0 0 1-12.1 3.5"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function IconShuffle() {
  return (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path
        d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

function IconPalette() {
  return (
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path
        d="M12 3a9 9 0 0 0-9 9c0 7 4 10 7 10a3 3 0 0 0 3-3 3 3 0 0 1 3-3h1a3 3 0 0 0 0-6h-1a3 3 0 0 0-3 3 2.5 2.5 0 0 1-2.5 2.5 4 4 0 0 1-1.5-.3A9 9 0 0 0 12 3Z"
        stroke="currentColor"
        strokeWidth="1.75"
        strokeLinejoin="round"
      />
      <circle cx="7.5" cy="10.5" r="1" fill="currentColor" />
      <circle cx="10.5" cy="7.5" r="1" fill="currentColor" />
      <circle cx="15" cy="8.5" r="1" fill="currentColor" />
    </svg>
  );
}

function PaletteSwatchStrip({ colors }: { colors: PaletteColors }) {
  const keys: (keyof PaletteColors)[] = [
    'pageBg',
    'cardBg',
    'line',
    'accent',
    'text',
  ];
  return (
    <span className="th-palette-strip" aria-hidden>
      {keys.map((k) => (
        <span
          key={k}
          className="th-palette-strip__chip"
          style={{ background: colors[k] }}
        />
      ))}
    </span>
  );
}

export function ColorPaletteControls({
  onApply,
}: {
  onApply: (colors: PaletteColors) => void;
}) {
  const menuId = useId();
  const wrapRef = useRef<HTMLDivElement>(null);
  const [menuOpen, setMenuOpen] = useState(false);

  useEffect(() => {
    if (!menuOpen) return;
    const onDoc = (e: MouseEvent) => {
      if (!wrapRef.current?.contains(e.target as Node)) {
        setMenuOpen(false);
      }
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setMenuOpen(false);
    };
    document.addEventListener('mousedown', onDoc);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDoc);
      document.removeEventListener('keydown', onKey);
    };
  }, [menuOpen]);

  return (
    <div className="th-color-palette-tools" ref={wrapRef}>
      <button
        type="button"
        className="th-color-tool-btn"
        onClick={() => onApply({ ...NEUTRAL_PALETTE })}
        title="Nulstil til neutral palette"
        aria-label="Nulstil farver"
      >
        <IconReset />
      </button>
      <button
        type="button"
        className="th-color-tool-btn"
        onClick={() => onApply(shufflePalette())}
        title="Bland harmoniske farver"
        aria-label="Bland farver"
      >
        <IconShuffle />
      </button>
      <div className="th-color-tool-menu">
        <button
          type="button"
          className={
            'th-color-tool-btn th-color-tool-btn--palette' +
            (menuOpen ? ' th-color-tool-btn--on' : '')
          }
          aria-expanded={menuOpen}
          aria-haspopup="menu"
          aria-controls={menuId}
          title="Vælg færdig palette"
          aria-label="Paletvælger"
          onClick={() => setMenuOpen((o) => !o)}
        >
          <IconPalette />
        </button>
        {menuOpen ? (
          <div
            id={menuId}
            className="th-palette-menu"
            role="menu"
            aria-label="Paleter"
          >
            <p className="th-palette-menu__lead">Kuraterede paletter</p>
            <ul className="th-palette-menu__list">
              {CURATED_PALETTES.map((p) => (
                <li key={p.id}>
                  <button
                    type="button"
                    role="menuitem"
                    className="th-palette-menu__item"
                    onClick={() => {
                      onApply({ ...p.colors });
                      setMenuOpen(false);
                    }}
                  >
                    <span className="th-palette-menu__name">{p.name}</span>
                    <PaletteSwatchStrip colors={p.colors} />
                  </button>
                </li>
              ))}
            </ul>
          </div>
        ) : null}
      </div>
    </div>
  );
}
