# Plugin Check / WordPress.org notes

Last updated after the wp.org-parathed pass (readme, render escaping, external React, PHPCS hygiene).

## Resolved ERROR-level items (re-run Plugin Check to confirm)

| Check | Fix |
|-------|-----|
| Missing `readme.txt` | `wordpress-plugin/timeline-history/readme.txt` (English per wp.org; Danish UI via text domain) |
| `Tested up to` outdated | Bump in readme when WordPress ships a new major (e.g. 7.1) |
| readme non-English | wp.org requires English in readme; plugin strings remain translatable |
| False wp.org update (fx “History Timeline” 1.4) | Use unique wp.org slug folder **`arkivtimeline/`** (no `Update URI` on directory plugins) |
| Text domain vs slug | Must match wp.org slug exactly: **`arkivtimeline`** (no hyphen) |
| Inlined React in `view.js` / `builder.js` | Webpack build in `wordpress-plugin/timeline-history/assets/` with `@wordpress/dependency-extraction-webpack-plugin`; deps `react`, `react-dom`, `react-jsx-runtime` via `*.asset.php` |
| Unescaped block output | `includes/class-embed-render.php`; thin `blocks/timeline/render.php` + shortcode reuse |

## Build / zip

```bash
npm run build
npm run pack:plugin          # bumps patch by default
npm run pack:plugin -- --no-bump
```

Zip: `dist/arkivtimeline.zip` (root folder `arkivtimeline/`, includes `readme.txt`, `assets/dist/*`, block build).

## Local PHPCS (optional)

Install WPCS once:

```bash
composer require --dev wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer
vendor/bin/phpcs -p
```

Config: `phpcs.xml.dist` (prefixes `timeline_history`, `Timeline_History_`, `th_`).

## Expected WARNINGs (acceptable)

These may still appear in Plugin Check; they are intentional for this plugin:

- **Slow DB queries** (`meta_key`, `tax_query`) on timeline story CPT queries and arkiv deduplication - annotated in `class-story-repository.php`, `class-arkiv-client.php`, `class-timeline-sets.php`.
- **`set_time_limit`** on long arkiv.dk admin/ajax/import paths - annotated in `class-admin-arkiv-import.php` and `class-arkiv-client.php`.
- **`the_content` filter** in story payload - core hook, annotated in `class-story-repository.php`.

## Manual verify on staging

1. Install fresh zip; no block editor required on frontend.
2. Shortcode + Gutenberg block on a page; empty timeline message.
3. Studio (`timeline-history-builder`) and arkiv import preview/import.
4. Run **Plugin Check** (Tools → Plugin Check) with "WordPress.org" rules; target **zero ERROR**.

If Plugin Check still flags React, confirm `assets/dist/view.js` is small (~15 KB) and lists script dependencies from `view.asset.php`, not a bundled `react.production.min.js`.