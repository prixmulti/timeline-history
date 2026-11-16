import type { CSSProperties } from 'react';
import type { TimelineConfig, TimelineTheme } from '@timeline-history/timeline-core';
import { ColorPaletteControls } from './ColorPaletteControls';
import type { PaletteColors } from './colorPalettes';
import { ThemePreviewShot } from './ThemePreviewShot';

const themes = [
  'minimal',
  'card',
  'editorial',
  'archive',
  'modern',
  'heritage',
] as const satisfies readonly TimelineTheme[];

type ThemeId = (typeof themes)[number];

const themeLabel: Record<ThemeId, string> = {
  minimal: 'Minimal',
  card: 'Kort',
  editorial: 'Editorial',
  archive: 'Arkiv',
  modern: 'Moderne',
  heritage: 'Kulturarv',
};

type ColorKey = 'pageBg' | 'cardBg' | 'line' | 'accent' | 'text' | 'muted';

const colorMeta: Record<ColorKey, string> = {
  pageBg: 'Sidebaggrund',
  cardBg: 'Kort',
  line: 'Tidslinje',
  accent: 'Accent',
  text: 'Tekst',
  muted: 'Dæmpet tekst',
};

const skinDefaults = {
  light: {
    pageBg: '#f4f0e8',
    cardBg: '#faf8f4',
    line: '#3d5a4c',
    accent: '#c4693a',
    text: '#152019',
    muted: '#5f6e66',
  },
  dark: {
    pageBg: '#0d1110',
    cardBg: '#1c2420',
    line: '#5a8f75',
    accent: '#e8925f',
    text: '#f2f5f3',
    muted: '#9caaa3',
  },
} as const;

function resolveColor(config: TimelineConfig, key: ColorKey): string {
  const custom = config.tokens?.colors?.[key];
  if (custom) return custom;
  const skin = config.skin === 'dark' ? skinDefaults.dark : skinDefaults.light;
  return skin[key];
}

function themePreviewVars(config: TimelineConfig): CSSProperties {
  return {
    '--th-page-bg': resolveColor(config, 'pageBg'),
    '--th-card-bg': resolveColor(config, 'cardBg'),
    '--th-line': resolveColor(config, 'line'),
    '--th-accent': resolveColor(config, 'accent'),
    '--th-text': resolveColor(config, 'text'),
    '--th-muted': resolveColor(config, 'muted'),
  } as CSSProperties;
}

function ColorField(props: {
  label: string;
  value: string;
  onChange: (hex: string) => void;
}) {
  return (
    <label className="th-color-field">
      <span className="th-color-field__label">{props.label}</span>
      <span className="th-color-field__swatch" style={{ background: props.value }}>
        <input
          type="color"
          value={props.value}
          onChange={(e) => props.onChange(e.target.value)}
          aria-label={props.label}
        />
      </span>
      <span className="th-color-field__hex">{props.value}</span>
    </label>
  );
}

export function DisplaySettings({
  config,
  onChange,
  compact = false,
}: {
  config: TimelineConfig;
  onChange: (config: TimelineConfig) => void;
  compact?: boolean;
}) {
  const patch = (partial: Partial<TimelineConfig>) => {
    onChange({
      ...config,
      layout: 'vertical',
      showMedia: true,
      ...partial,
    });
  };

  const patchColor = (key: ColorKey, value: string) => {
    patch({
      tokens: {
        ...config.tokens,
        colors: { ...config.tokens?.colors, [key]: value },
      },
    });
  };

  const applyPalette = (colors: PaletteColors) => {
    patch({
      themeAdapt: 'off',
      tokens: {
        ...config.tokens,
        colors: { ...colors },
      },
    });
  };

  const themeAdaptOn = config.themeAdapt !== 'off';

  return (
    <div
      className={
        'th-display th-display--settings' +
        (compact ? ' th-display--compact' : '')
      }
    >
      <section className="th-display__section th-display__section--adapt">
        <label className="th-theme-adapt">
          <input
            type="checkbox"
            checked={themeAdaptOn}
            onChange={(e) =>
              patch({ themeAdapt: e.target.checked ? 'auto' : 'off' })
            }
          />
          <span>Følg WordPress-tema på siderne</span>
        </label>
        <p className="th-theme-adapt__hint">
          {themeAdaptOn
            ? 'Farver hentes fra det aktive tema. Enkelte farver nedenfor overskriver automatisk tilpasning.'
            : 'Farverne nedenfor bruges uændret på alle sider.'}
        </p>
      </section>

      <section className="th-display__section">
        <h2>Tema</h2>
        <div
          className="th-theme-grid"
          role="group"
          aria-label="Tema"
          style={themePreviewVars(config)}
        >
          {themes.map((t) => (
            <button
              key={t}
              type="button"
              data-theme={t}
              className={
                'th-theme-card' +
                (config.theme === t ? ' th-theme-card--active' : '')
              }
              onClick={() => patch({ theme: t })}
              aria-label={themeLabel[t]}
              title={themeLabel[t]}
            >
              <ThemePreviewShot theme={t} />
            </button>
          ))}
        </div>
      </section>

      <section className="th-display__section">
        <div className="th-display__section-head">
          <h2>Farver</h2>
          <ColorPaletteControls onApply={applyPalette} />
        </div>
        <div className="th-color-grid">
          {(Object.keys(colorMeta) as ColorKey[]).map((key) => (
              <ColorField
                key={key}
                label={colorMeta[key]}
                value={resolveColor(config, key)}
                onChange={(v) => patchColor(key, v)}
              />
            ))}
        </div>
      </section>
    </div>
  );
}
