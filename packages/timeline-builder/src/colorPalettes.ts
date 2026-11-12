export type PaletteColorKey =
  | 'pageBg'
  | 'cardBg'
  | 'line'
  | 'accent'
  | 'text'
  | 'muted';

export type PaletteColors = Record<PaletteColorKey, string>;

/** Neutral lys palette – matcher standard ArkivTimeline. */
export const NEUTRAL_PALETTE: PaletteColors = {
  pageBg: '#f4f0e8',
  cardBg: '#faf8f4',
  line: '#3d5a4c',
  accent: '#c4693a',
  text: '#152019',
  muted: '#5f6e66',
};

export type CuratedPalette = {
  id: string;
  name: string;
  colors: PaletteColors;
};

export const CURATED_PALETTES: CuratedPalette[] = [
  {
    id: 'arkiv-natur',
    name: 'Arkiv natur',
    colors: { ...NEUTRAL_PALETTE },
  },
  {
    id: 'pergament',
    name: 'Pergament',
    colors: {
      pageBg: '#f2ebe0',
      cardBg: '#faf6ef',
      line: '#6b5344',
      accent: '#a85c32',
      text: '#2a2118',
      muted: '#7a6b5c',
    },
  },
  {
    id: 'nordisk',
    name: 'Nordisk',
    colors: {
      pageBg: '#eef1f4',
      cardBg: '#f7f9fb',
      line: '#4a6278',
      accent: '#3d7a9e',
      text: '#1a2430',
      muted: '#5c6d7d',
    },
  },
  {
    id: 'skov',
    name: 'Skov',
    colors: {
      pageBg: '#e8efe9',
      cardBg: '#f3f8f4',
      line: '#2f5c45',
      accent: '#6b8f4e',
      text: '#142018',
      muted: '#4d6356',
    },
  },
  {
    id: 'efteraar',
    name: 'Efterår',
    colors: {
      pageBg: '#f3ebe3',
      cardBg: '#faf4ed',
      line: '#7a4e3a',
      accent: '#c45c2a',
      text: '#2b1c14',
      muted: '#7d6558',
    },
  },
  {
    id: 'kyst',
    name: 'Kyst',
    colors: {
      pageBg: '#e6eef0',
      cardBg: '#f2f7f8',
      line: '#3a6670',
      accent: '#d4843a',
      text: '#152428',
      muted: '#5a6d72',
    },
  },
  {
    id: 'blaek',
    name: 'Blæk og papir',
    colors: {
      pageBg: '#f7f5f2',
      cardBg: '#ffffff',
      line: '#1c1c1c',
      accent: '#b83232',
      text: '#121212',
      muted: '#5c5c5c',
    },
  },
  {
    id: 'museumsnat',
    name: 'Museumsnat',
    colors: {
      pageBg: '#121614',
      cardBg: '#1e2421',
      line: '#6b9080',
      accent: '#d4a24c',
      text: '#eef2ef',
      muted: '#9aa8a1',
    },
  },
];

function clamp(n: number, min: number, max: number): number {
  return Math.min(max, Math.max(min, n));
}

function parseHex(hex: string): { r: number; g: number; b: number } {
  let h = hex.trim().replace(/^#/, '');
  if (h.length === 3) {
    h = h
      .split('')
      .map((c) => c + c)
      .join('');
  }
  const n = parseInt(h, 16);
  if (Number.isNaN(n)) return { r: 0, g: 0, b: 0 };
  return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 };
}

export function toHex(r: number, g: number, b: number): string {
  return (
    '#' +
    [r, g, b]
      .map((v) =>
        Math.round(clamp(v, 0, 255))
          .toString(16)
          .padStart(2, '0')
      )
      .join('')
  );
}

function hslToRgb(
  h: number,
  s: number,
  l: number
): { r: number; g: number; b: number } {
  const hh = ((h % 360) + 360) % 360;
  const ss = clamp(s, 0, 100) / 100;
  const ll = clamp(l, 0, 100) / 100;
  const c = (1 - Math.abs(2 * ll - 1)) * ss;
  const x = c * (1 - Math.abs(((hh / 60) % 2) - 1));
  const m = ll - c / 2;
  let rp = 0;
  let gp = 0;
  let bp = 0;
  if (hh < 60) [rp, gp, bp] = [c, x, 0];
  else if (hh < 120) [rp, gp, bp] = [x, c, 0];
  else if (hh < 180) [rp, gp, bp] = [0, c, x];
  else if (hh < 240) [rp, gp, bp] = [0, x, c];
  else if (hh < 300) [rp, gp, bp] = [x, 0, c];
  else [rp, gp, bp] = [c, 0, x];
  return {
    r: (rp + m) * 255,
    g: (gp + m) * 255,
    b: (bp + m) * 255,
  };
}

export function hslToHex(h: number, s: number, l: number): string {
  const { r, g, b } = hslToRgb(h, s, l);
  return toHex(r, g, b);
}

function relativeLuminance(hex: string): number {
  const { r, g, b } = parseHex(hex);
  const lin = [r, g, b].map((c) => {
    const v = c / 255;
    return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
  });
  return 0.2126 * lin[0] + 0.7152 * lin[1] + 0.0722 * lin[2];
}

export function contrastRatio(a: string, b: string): number {
  const L1 = relativeLuminance(a);
  const L2 = relativeLuminance(b);
  const lighter = Math.max(L1, L2);
  const darker = Math.min(L1, L2);
  return (lighter + 0.05) / (darker + 0.05);
}

function blendHex(fg: string, bg: string, amount: number): string {
  const f = parseHex(fg);
  const b = parseHex(bg);
  const t = clamp(amount, 0, 1);
  return toHex(
    f.r * (1 - t) + b.r * t,
    f.g * (1 - t) + b.g * t,
    f.b * (1 - t) + b.b * t
  );
}

function readableText(
  bg: string,
  preferDark = '#152019',
  preferLight = '#f2f5f3'
): string {
  const darkRatio = contrastRatio(preferDark, bg);
  const lightRatio = contrastRatio(preferLight, bg);
  if (darkRatio >= 4.5) return preferDark;
  if (lightRatio >= 4.5) return preferLight;
  return darkRatio >= lightRatio ? preferDark : preferLight;
}

function ensureMuted(text: string, cardBg: string): string {
  let muted = blendHex(text, cardBg, 0.42);
  if (contrastRatio(muted, cardBg) < 3) {
    muted = blendHex(text, cardBg, 0.28);
  }
  if (contrastRatio(muted, cardBg) < 3) {
    muted = text;
  }
  return muted;
}

function ensureAccentOnCard(accent: string, cardBg: string): string {
  if (contrastRatio(accent, cardBg) >= 3) return accent;
  const { r, g, b } = parseHex(accent);
  const lum = relativeLuminance(accent);
  const cardLum = relativeLuminance(cardBg);
  const factor = cardLum > 0.5 ? 0.82 : 1.22;
  return toHex(r * factor, g * factor, b * factor);
}

/**
 * Genererer en sammenhængende palette ud fra én tilfældig accentfarve (triadisk / analog).
 */
export function shufflePalette(): PaletteColors {
  const hue = Math.random() * 360;
  const dark = Math.random() < 0.22;

  const pageBg = dark
    ? hslToHex(hue, 10 + Math.random() * 8, 9 + Math.random() * 4)
    : hslToHex(hue, 14 + Math.random() * 12, 93 + Math.random() * 4);

  const cardBg = dark
    ? hslToHex(hue, 8 + Math.random() * 6, 14 + Math.random() * 5)
    : hslToHex(hue, 10 + Math.random() * 10, 97 + Math.random() * 2);

  const lineHue = (hue + 35 + Math.random() * 50) % 360;
  const line = dark
    ? hslToHex(lineHue, 22 + Math.random() * 18, 58 + Math.random() * 12)
    : hslToHex(lineHue, 28 + Math.random() * 22, 32 + Math.random() * 14);

  const accentHue =
    Math.random() < 0.55
      ? (hue + 150 + Math.random() * 60) % 360
      : (hue + 300 + Math.random() * 40) % 360;
  let accent = hslToHex(
    accentHue,
    48 + Math.random() * 22,
    dark ? 58 + Math.random() * 12 : 42 + Math.random() * 12
  );
  accent = ensureAccentOnCard(accent, cardBg);

  const text = readableText(cardBg);
  const muted = ensureMuted(text, cardBg);

  if (contrastRatio(text, pageBg) < 3) {
    const textPage = readableText(pageBg);
    return {
      pageBg,
      cardBg,
      line,
      accent,
      text: textPage,
      muted: ensureMuted(textPage, cardBg),
    };
  }

  return { pageBg, cardBg, line, accent, text, muted };
}
