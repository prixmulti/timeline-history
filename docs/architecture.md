# Architecture

## Single data model

`TimelineConfig` + `TimelineStory[]` is defined in `@timeline-history/timeline-core` and used by:

- Vite playground (`localStorage`)
- WordPress REST `GET /wp-json/timeline-history/v1/preview`
- Shortcode and block output (`data-timeline` JSON on `.th-embed`)

## Rendering

- **Editor / playground**: React `TimelineView` (direct import).
- **WordPress front**: `view.js` hydrates `.th-embed` with the same `TimelineView` via `createRoot`.

No duplicate PHP HTML templates for stories in MVP.

## Build pipeline

1. `packages/timeline-core` → `view.js`, `timeline.css`, types
2. `packages/timeline-builder` → `builder.js`, `builder.css`
3. `scripts/copy-assets.mjs` → `wordpress-plugin/timeline-history/assets/dist/`
4. `wordpress-plugin/.../blocks/timeline` → `@wordpress/scripts` → `build/`

## WordPress content model

- CPT `timeline_story` with meta: date, icon, accent color, optional order
- Taxonomy `timeline_category` for shortcode/block filtering
