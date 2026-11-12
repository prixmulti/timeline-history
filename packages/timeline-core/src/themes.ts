import type { TimelineConfig, TimelineSkin, TimelineTheme, TokenOverrides } from './types';

const baseLight = {
  line: '#3d5a4c',
  accent: '#c4693a',
  cardBg: '#faf8f4',
  text: '#152019',
  muted: '#5f6e66',
};

const baseDark = {
  line: '#5a8f75',
  accent: '#e8925f',
  cardBg: '#1c2420',
  text: '#f2f5f3',
  muted: '#9caaa3',
};

const themePresets: Record<
  TimelineTheme,
  { display: string; body: string; radius: string }
> = {
  minimal: {
    display: '"Instrument Serif", Georgia, serif',
    body: '"Outfit", "Source Sans 3", system-ui, sans-serif',
    radius: '6px',
  },
  card: {
    display: '"Fraunces", Georgia, serif',
    body: '"DM Sans", system-ui, sans-serif',
    radius: '14px',
  },
  editorial: {
    display: '"Newsreader", "Playfair Display", Georgia, serif',
    body: '"Outfit", "Libre Franklin", system-ui, sans-serif',
    radius: '4px',
  },
  archive: {
    display: '"Playfair Display", Georgia, serif',
    body: '"Source Sans 3", system-ui, sans-serif',
    radius: '10px',
  },
  modern: {
    display: '"Outfit", system-ui, sans-serif',
    body: '"DM Sans", system-ui, sans-serif',
    radius: '2px',
  },
  heritage: {
    display: '"Fraunces", "Newsreader", Georgia, serif',
    body: '"Source Sans 3", system-ui, sans-serif',
    radius: '6px',
  },
};

export function resolveTokens(config: TimelineConfig): Required<TokenOverrides> & {
  colors: Required<NonNullable<TokenOverrides['colors']>>;
  fonts: Required<NonNullable<TokenOverrides['fonts']>>;
  radius: string;
} {
  const skin: TimelineSkin = config.skin;
  const palette = skin === 'dark' ? baseDark : baseLight;
  const preset = themePresets[config.theme];
  const o = config.tokens ?? {};

  return {
    colors: {
      line: o.colors?.line ?? palette.line,
      accent: o.colors?.accent ?? palette.accent,
      cardBg: o.colors?.cardBg ?? palette.cardBg,
      text: o.colors?.text ?? palette.text,
      muted: o.colors?.muted ?? palette.muted,
      pageBg: o.colors?.pageBg ?? (skin === 'dark' ? '#0d1110' : ''),
    },
    fonts: {
      display: o.fonts?.display ?? preset.display,
      body: o.fonts?.body ?? preset.body,
    },
    radius: o.radius ?? preset.radius,
  };
}

export function cssVariables(config: TimelineConfig): Record<string, string> {
  const t = resolveTokens(config);
  return {
    '--th-line': t.colors.line,
    '--th-accent': t.colors.accent,
    '--th-card-bg': t.colors.cardBg,
    '--th-text': t.colors.text,
    '--th-muted': t.colors.muted,
    '--th-font-display': t.fonts.display,
    '--th-font-body': t.fonts.body,
    '--th-radius': t.radius,
    '--th-line-style': config.lineStyle === 'dashed' ? 'dashed' : 'solid',
    ...(t.colors.pageBg
      ? { '--th-page-bg': t.colors.pageBg }
      : {}),
  };
}
