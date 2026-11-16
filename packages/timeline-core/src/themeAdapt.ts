import {
  blendHex,
  contrastRatio,
  ensureMuted,
  normalizeCssColor,
  parseHex,
  readableText,
  relativeLuminance,
} from './colorUtils';
import type { TimelineConfig, TokenOverrides } from './types';

export type ThemeColorHints = NonNullable<TokenOverrides['colors']>;

const COLOR_KEYS = [
  'pageBg',
  'cardBg',
  'line',
  'accent',
  'text',
  'muted',
] as const satisfies readonly (keyof ThemeColorHints)[];

const WP_VARS: Array<{ name: string; key: (typeof COLOR_KEYS)[number] }> = [
  { name: '--wp--style--color--background', key: 'pageBg' },
  { name: '--wp--preset--color--background', key: 'pageBg' },
  { name: '--wp--preset--color--contrast', key: 'text' },
  { name: '--wp--preset--color--foreground', key: 'text' },
  { name: '--wp--preset--color--primary', key: 'accent' },
  { name: '--wp--preset--color--base', key: 'accent' },
  { name: '--wp--preset--color--secondary', key: 'line' },
  { name: '--wp--preset--color--tertiary', key: 'line' },
];

const GENERIC_LINK_ACCENTS = new Set([
  '#0000ee',
  '#0000ff',
  '#2271b1',
  '#0066cc',
  '#3366cc',
  '#21759b',
  '#0073aa',
]);

function isNeutralSurface(hex: string): boolean {
  const rgb = parseHex(hex);
  if (!rgb) return false;
  const lum = relativeLuminance(hex);
  const max = Math.max(rgb.r, rgb.g, rgb.b);
  const min = Math.min(rgb.r, rgb.g, rgb.b);
  const sat = max === 0 ? 0 : (max - min) / max;
  return lum > 0.92 || lum < 0.1 || sat < 0.14;
}

function isTransparentBg(value: string): boolean {
  const v = value.trim().toLowerCase();
  return (
    v === 'transparent' ||
    v === 'rgba(0, 0, 0, 0)' ||
    v === 'rgba(0,0,0,0)'
  );
}

function sampleContentBackground(anchor: HTMLElement): string | null {
  let node: Element | null = anchor.parentElement;
  while (node && node !== document.documentElement) {
    const style = getComputedStyle(node);
    if (!isTransparentBg(style.backgroundColor)) {
      const hex = normalizeCssColor(style.backgroundColor);
      if (hex && isNeutralSurface(hex)) {
        return hex;
      }
    }
    node = node.parentElement;
  }
  return null;
}

function readVar(name: string, el: Element): string {
  return getComputedStyle(el).getPropertyValue(name).trim();
}

function sampleDocumentColors(embed?: HTMLElement): Partial<ThemeColorHints> {
  if (typeof document === 'undefined') return {};

  const out: Partial<ThemeColorHints> = {};
  const root = document.documentElement;
  const body = document.body;

  for (const { name, key } of WP_VARS) {
    if (out[key]) continue;
    const hex = normalizeCssColor(readVar(name, root));
    if (!hex) continue;
    if (key === 'pageBg' && !isNeutralSurface(hex)) continue;
    out[key] = hex;
  }

  if (embed) {
    const hostBg = sampleContentBackground(embed);
    if (hostBg) out.pageBg = hostBg;
  }

  if (body) {
    const bodyStyle = getComputedStyle(body);
    if (!out.pageBg) {
      const bg = normalizeCssColor(bodyStyle.backgroundColor);
      if (bg && isNeutralSurface(bg)) out.pageBg = bg;
    }
    if (!out.text) {
      const text = normalizeCssColor(bodyStyle.color);
      if (text) out.text = text;
    }
  }

  if (!out.accent) {
    const probe = document.createElement('a');
    probe.href = '#th-theme-probe';
    probe.setAttribute('aria-hidden', 'true');
    probe.style.cssText =
      'position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);';
    const host = body ?? root;
    host.appendChild(probe);
    const linkColor = normalizeCssColor(getComputedStyle(probe).color);
    probe.remove();
    if (
      linkColor &&
      !GENERIC_LINK_ACCENTS.has(linkColor.toLowerCase())
    ) {
      out.accent = linkColor;
    }
  }

  return out;
}

function derivePalette(partial: Partial<ThemeColorHints>): ThemeColorHints {
  const pageBg = partial.pageBg ?? '#f4f0e8';
  const text = partial.text ?? readableText(pageBg);
  const lightPage = relativeLuminance(pageBg) > 0.55;
  const cardBg =
    partial.cardBg ??
    blendHex(pageBg, lightPage ? '#ffffff' : '#000000', lightPage ? 0.14 : 0.1);
  let accent = partial.accent ?? (lightPage ? '#c4693a' : '#e8925f');
  if (contrastRatio(accent, cardBg) < 3) {
    accent = blendHex(accent, text, 0.25);
  }
  const line =
    partial.line ??
    blendHex(accent, cardBg, lightPage ? 0.55 : 0.45);
  const muted = partial.muted ?? ensureMuted(text, cardBg);

  return { pageBg, cardBg, line, accent, text, muted };
}

/**
 * Udfyld manglende token-farver ud fra WordPress-tema (hints fra PHP + live DOM).
 * Eksplicit gemte farver i Studio overskrives ikke.
 */
export function applyThemeAdapt(
  config: TimelineConfig,
  hints?: Partial<ThemeColorHints> | null,
  embed?: HTMLElement | null
): TimelineConfig {
  if (config.themeAdapt === 'off') {
    return config;
  }

  const existing = config.tokens?.colors ?? {};
  const phpHints = { ...hints };
  if (phpHints.pageBg && !isNeutralSurface(phpHints.pageBg)) {
    delete phpHints.pageBg;
  }
  const sampled = { ...phpHints, ...sampleDocumentColors(embed ?? undefined) };

  const picked: Partial<ThemeColorHints> = {};
  for (const key of COLOR_KEYS) {
    if (existing[key]) {
      picked[key] = existing[key];
    } else if (sampled[key]) {
      picked[key] = sampled[key];
    }
  }

  const complete = derivePalette(picked);
  const mergedColors: ThemeColorHints = { ...existing };
  for (const key of COLOR_KEYS) {
    if (!existing[key]) {
      mergedColors[key] = complete[key];
    }
  }

  const adaptedSkin =
    !existing.pageBg && complete.pageBg
      ? relativeLuminance(complete.pageBg) < 0.42
        ? 'dark'
        : 'light'
      : config.skin;

  return {
    ...config,
    skin: adaptedSkin,
    tokens: {
      ...config.tokens,
      colors: mergedColors,
    },
  };
}
