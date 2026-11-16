/**
 * WordPress frontend: hydrate .th-embed elements from JSON payload.
 */
import { createElement } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { applyThemeAdapt } from './themeAdapt';
import { TimelineView } from './TimelineView';
import type { TimelinePayload } from './types';

const roots = new WeakMap<Element, Root>();

function parsePayload(el: HTMLElement): TimelinePayload | null {
  const jsonNode = el.querySelector(':scope > script.th-embed__data[type="application/json"]');
  const raw =
    jsonNode?.textContent?.trim() ||
    el.getAttribute('data-timeline') ||
    '';
  if (!raw) return null;
  try {
    return JSON.parse(raw) as TimelinePayload;
  } catch {
    return null;
  }
}

export function mountTimelineEmbed(el: HTMLElement): void {
  const payload = parsePayload(el);
  if (!payload) return;

  const config = applyThemeAdapt(
    { themeAdapt: 'auto', ...payload.config },
    payload.themeHints,
    el
  );

  // Clear PHP empty-state / JSON script before React mount.
  el.querySelectorAll(':scope > .th-embed__empty, :scope > script.th-embed__data').forEach((n) =>
    n.remove()
  );

  let root = roots.get(el);
  if (!root) {
    root = createRoot(el);
    roots.set(el, root);
  }

  root.render(
    createElement(TimelineView, {
      config,
      stories: payload.stories ?? [],
      imageLightbox: true,
    })
  );
}

export function initTimelineEmbeds(scope: ParentNode = document): void {
  const nodes = scope.querySelectorAll<HTMLElement>(
    '.th-embed[data-th-embed], .th-embed[data-timeline]'
  );
  nodes.forEach((el) => {
    if (el.dataset.thMounted === '1') return;
    el.dataset.thMounted = '1';
    mountTimelineEmbed(el);
  });
}

function boot(): void {
  initTimelineEmbeds();
}

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
}

declare global {
  interface Window {
    TimelineHistoryView?: {
      initTimelineEmbeds: typeof initTimelineEmbeds;
      mountTimelineEmbed: typeof mountTimelineEmbed;
    };
  }
}

if (typeof window !== 'undefined') {
  window.TimelineHistoryView = { initTimelineEmbeds, mountTimelineEmbed };
}
