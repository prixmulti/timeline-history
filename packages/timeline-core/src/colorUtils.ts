/** Små farvehjælpere til tematilpasning på frontenden. */

export function parseHex(hex: string): { r: number; g: number; b: number } | null {
  const h = hex.replace('#', '').trim();
  if (h.length === 3) {
    const r = parseInt(h[0] + h[0], 16);
    const g = parseInt(h[1] + h[1], 16);
    const b = parseInt(h[2] + h[2], 16);
    return { r, g, b };
  }
  if (h.length === 6) {
    const r = parseInt(h.slice(0, 2), 16);
    const g = parseInt(h.slice(2, 4), 16);
    const b = parseInt(h.slice(4, 6), 16);
    if ([r, g, b].some((n) => Number.isNaN(n))) return null;
    return { r, g, b };
  }
  return null;
}

export function toHex(r: number, g: number, b: number): string {
  const clamp = (n: number) => Math.max(0, Math.min(255, Math.round(n)));
  return `#${[clamp(r), clamp(g), clamp(b)]
    .map((n) => n.toString(16).padStart(2, '0'))
    .join('')}`;
}

export function relativeLuminance(hex: string): number {
  const c = parseHex(hex);
  if (!c) return 0.5;
  const lin = (v: number) => {
    const s = v / 255;
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  };
  return 0.2126 * lin(c.r) + 0.7152 * lin(c.g) + 0.0722 * lin(c.b);
}

export function blendHex(a: string, b: string, amount: number): string {
  const fa = parseHex(a);
  const fb = parseHex(b);
  if (!fa || !fb) return a;
  const t = Math.max(0, Math.min(1, amount));
  return toHex(
    fa.r * (1 - t) + fb.r * t,
    fa.g * (1 - t) + fb.g * t,
    fa.b * (1 - t) + fb.b * t
  );
}

export function contrastRatio(fg: string, bg: string): number {
  const l1 = relativeLuminance(fg);
  const l2 = relativeLuminance(bg);
  const hi = Math.max(l1, l2);
  const lo = Math.min(l1, l2);
  return (hi + 0.05) / (lo + 0.05);
}

export function readableText(
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

export function ensureMuted(text: string, cardBg: string): string {
  let muted = blendHex(text, cardBg, 0.42);
  if (contrastRatio(muted, cardBg) < 3) {
    muted = blendHex(text, cardBg, 0.28);
  }
  if (contrastRatio(muted, cardBg) < 3) {
    muted = text;
  }
  return muted;
}

export function normalizeCssColor(value: string): string | null {
  const v = value.trim();
  if (!v || v === 'transparent' || v === 'inherit') return null;
  if (v.startsWith('#')) {
    const c = parseHex(v);
    return c ? toHex(c.r, c.g, c.b) : null;
  }
  const m = v.match(
    /^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*[\d.]+)?\s*\)$/i
  );
  if (m) {
    return toHex(Number(m[1]), Number(m[2]), Number(m[3]));
  }
  return null;
}
