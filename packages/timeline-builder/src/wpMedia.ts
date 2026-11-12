export type WpMediaAttachment = {
  url?: string;
  sizes?: Record<string, { url?: string }>;
};

type WpMediaFrame = {
  on: (event: string, cb: () => void) => void;
  open: () => void;
  state: () => {
    get: (key: string) => {
      toJSON: () => WpMediaAttachment | WpMediaAttachment[];
    };
  };
};

declare global {
  interface Window {
    wp?: {
      media?: (options: Record<string, unknown>) => WpMediaFrame;
    };
  }
}

function bestImageUrl(attachment: WpMediaAttachment): string {
  const large = attachment.sizes?.large?.url;
  const full = attachment.sizes?.full?.url;
  return (large || full || attachment.url || '').trim();
}

/**
 * Open WordPress media library (upload or pick). Returns selected image URLs.
 */
export function openWpMediaLibrary(options?: {
  multiple?: boolean;
  title?: string;
  buttonText?: string;
}): Promise<string[]> {
  return new Promise((resolve, reject) => {
    const media = window.wp?.media;
    if (typeof media !== 'function') {
      reject(new Error('WordPress mediebibliotek er ikke tilgængeligt på denne side.'));
      return;
    }

    const frame = media({
      title: options?.title || 'Vælg billeder',
      button: { text: options?.buttonText || 'Brug billede(r)' },
      multiple: options?.multiple ?? true,
      library: { type: 'image' },
    });

    frame.on('select', () => {
      const selection = frame.state().get('selection').toJSON();
      const items = Array.isArray(selection) ? selection : [selection];
      const urls = items.map(bestImageUrl).filter(Boolean);
      resolve(urls);
    });

    frame.open();
  });
}

export function canOpenWpMedia(): boolean {
  return typeof window.wp?.media === 'function';
}
