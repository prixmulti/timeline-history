export type TimelineLayout = 'vertical' | 'horizontal';
export type TimelineTheme =
  | 'minimal'
  | 'card'
  | 'editorial'
  | 'archive'
  | 'modern'
  | 'heritage';
export type TimelineSkin = 'light' | 'dark';
export type TimelineAnimation = 'none' | 'fade-in';
export type LineStyle = 'solid' | 'dashed' | 'gradient';
/** Frontend: hent farver fra aktivt WordPress-tema når tokens mangler. */
export type ThemeAdaptMode = 'auto' | 'off';

export interface TokenOverrides {
  colors?: {
    line?: string;
    accent?: string;
    cardBg?: string;
    text?: string;
    muted?: string;
    pageBg?: string;
  };
  fonts?: {
    display?: string;
    body?: string;
  };
  radius?: string;
}

export interface TimelineConfig {
  layout: TimelineLayout;
  theme: TimelineTheme;
  skin: TimelineSkin;
  animation: TimelineAnimation;
  showMedia: boolean;
  dateFormat: string;
  categorySlugs: string[];
  maxStories: number;
  lineStyle: LineStyle;
  /** Default `auto` på sider; sæt `off` for at låse farver fra Studio. */
  themeAdapt?: ThemeAdaptMode;
  tokens?: TokenOverrides;
}

export interface TimelineStory {
  id: string;
  date: string;
  title: string;
  excerpt?: string;
  content?: string;
  /** Prefereret: flere billeder (slideshow hvis > 1). */
  images?: string[];
  /** Legacy enkeltbillede – bruges hvis `images` mangler. */
  image?: string;
  /** Billedtekster pr. billede (samme rækkefølge som `images`), fx fra arkiv.dk Indhold/Tags. */
  imageCaptions?: string[];
  icon?: string;
  accentColor?: string;
  order?: number;
  /** Link til kilden (fx arkiv.dk-registrering). */
  sourceUrl?: string;
  /** Arkiv.dk Bemærkning – vises i billed-popup. */
  bemaerkning?: string;
}

export interface TimelinePayload {
  config: TimelineConfig;
  stories: TimelineStory[];
  /** Server-side farvehints (block-tema / customizer) før DOM-udlæsning. */
  themeHints?: Partial<NonNullable<TokenOverrides['colors']>>;
}

export const defaultConfig = (): TimelineConfig => ({
  layout: 'vertical',
  theme: 'card',
  skin: 'light',
  animation: 'fade-in',
  showMedia: true,
  dateFormat: 'd. MMM yyyy',
  categorySlugs: [],
  maxStories: 0,
  lineStyle: 'solid',
  themeAdapt: 'auto',
  tokens: {},
});
