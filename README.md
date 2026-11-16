# Timeline History (MVP)

Modern WordPress timeline plugin with a monorepo for local development in Cursor.

## Quick start (Cursor simulation)

```bash
npm install
npm run build
npm run dev
```

Open [http://localhost:5173](http://localhost:5173):

- **Admin** - add stories, tune layout/theme, live preview
- **Front (side)** - see the embed as on a WordPress page

Playground data is stored in `localStorage`. Use **Eksporter JSON** to save fixtures.

## Rigtig WordPress-side

Pluginnet gemmer historier i WordPress-databasen (custom post type `timeline_story`, post meta og options). Playgroundens `localStorage` bruges kun til lokal demo.

### Installer pluginnet

1. Byg og pack en ren zip fra repo-roden:

```bash
npm install
npm run pack:plugin
```

2. Upload `dist/timeline-history.zip` i wp-admin under **Plugins → Add New → Upload Plugin**, eller kopiér mappen `wordpress-plugin/timeline-history` til `wp-content/plugins/timeline-history/`.
3. Aktiver **Timeline History**.
4. Kræver WordPress 6.4+ og PHP 7.4+.

`npm run pack:plugin` kører `npm run build` først, **hæver patch-version** i `timeline-history.php` (fx 0.3.65 → 0.3.66), og pakker `assets/dist/` plus `blocks/timeline/build/` (uden `node_modules`). Brug `npm run pack:plugin -- --no-bump` hvis versionen ikke skal ændres, eller `--version=0.4.0` for manuelt nummer.

### Navngivne tidslinjer

Hver samling af historier har sit eget navn (taxonomy `timeline_set`). Brug et nyt navn pr. arkiv.dk-import eller emne, så shortcodes kan pege på den rigtige.

### Opret historier (anbefalet: Studio)

1. Gå til **Timeline → Studio**.
2. Vælg eller opret en **tidslinje** øverst i venstre panel.
3. Tilføj/rediger/slet historier (klik på et kort i preview for at redigere).
4. Juster layout/tema/farver under **Visning** - gemmes automatisk.
5. Alternativt: **Timeline → Alle historier** (standard WP-editor) eller **Timeline → Tidslinjer**.

### Kode på siderne

**Shortcode** (klassisk editor, Shortcode-blok, Elementor shortcode-widget, osv.):

```
[timeline_history timeline="ansager-1940"]
```

Med overrides:

```
[timeline_history timeline="ansager-1940" layout="vertical" theme="editorial" skin="light" animation="fade-in" category="company" limit="10"]
```

| Attribute   | Værdier                                      |
|------------|-----------------------------------------------|
| `timeline` | slug for navngiven tidslinje (anbefalet)      |
| `layout`   | `vertical`, `horizontal`                      |
| `theme`    | `minimal`, `card`, `editorial`                |
| `skin`     | `light`, `dark`                               |
| `animation`| `none`, `fade-in`                             |
| `category` | kommaseparerede kategori-slugs                |
| `limit`    | max antal historier                           |
| `show_media` | `1`, `true`, `yes`                          |

Uden `timeline` vises alle historier. Tomme øvrige attributes bruger site-defaults fra **Timeline**.

**Gutenberg-blok:** indsæt blokken **Timeline History** (kategori Widgets) og sæt tidslinje-slug, layout/theme/kategori i sidebaren.

### Menu i wp-admin

- **Timeline** - default settings
- **Timeline → Studio** - vælg tidslinje, historier + visning + live preview
- **Timeline → Arkiv-import** - import fra arkiv.dk med tidslinjenavn (billeder linkes eksternt)
- **Timeline → Tidslinjer** - omdøb/administrer navngivne tidslinjer
- **Timeline → Alle historier** - klassisk CPT-liste (valgfri)

## Local WordPress (wp-env)

Requires [Docker](https://www.docker.com/).

```bash
npm install
npm run build
npm run wp:start
```

Default site: [http://localhost:8888](http://localhost:8888)

## Smoke checklist

- [ ] Create 2+ timeline stories with dates and featured images
- [ ] Insert **Timeline History** block on a page; view on front
- [ ] Add `[timeline_history]` to a post; verify hydration (no empty box)
- [ ] Open **Timeline → Builder**; stories load via REST
- [ ] Switch layout horizontal/vertical on playground and in shortcode
- [ ] Mobile width: vertical timeline single-column rail

## Reference

- **[ArkivTimeline – projektbeskrivelse (dansk)](docs/arkivtimeline.md)** – formål, funktioner, arkitektur og workflows  
- **[Brugervejledning + figurer til InDesign](docs/arkivtimeline-brugerguide.md)** – til brochure med screendumps (`docs/arkivtimeline-indesign.txt` = ren tekst)
- [architecture.md](docs/architecture.md) – dataflow og build-rækkefølge (teknisk)
- `cool-timeline-pro/` – legacy reference plugin (unchanged)
