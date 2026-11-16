# ArkivTimeline

Et WordPress-plugin til visuelle tidslinjer med fokus på lokalhistorie og indhold fra [arkiv.dk](https://www.arkiv.dk). Projektet kombinerer import fra det fælles arkiv, en redaktionel Studio-oplevelse og en moderne tidslinjevisning, der kan indlejres på almindelige WordPress-sider.

**Version (plugin):** se `Version` i `wordpress-plugin/timeline-history/timeline-history.php`  
**Krav:** WordPress 6.4+, PHP 7.4+

---

## Formål

Lokalarkiver og historieforeninger vil ofte vise kronologiske historier på deres hjemmeside: bygninger, begivenheder, billedserier med årstal og korte tekster. Arkiv.dk indeholder registreringerne; WordPress er det naturlige sted at præsentere dem.

ArkivTimeline løser tre opgaver i én løsning:

1. **Hente og vælge** materiale fra arkiv.dk (søgning, forhåndsvisning, filtrering på år).
2. **Redigere og kuratere** historier i et Studio med live preview (titel, dato, tekst, billeder, bemærkning).
3. **Vise** tidslinjen på forsiden via shortcode eller Gutenberg-blok, med temaer og automatisk tilpasning til sidens WordPress-tema.

---

## Overblik for redaktører

### wp-admin-menu: ArkivTimeline

| Menu | Beskrivelse |
|------|-------------|
| **Import fra Arkiv.dk** | Vælg lokalarkiv, søgeord, årsinterval. Hent forhåndsvisning, markér poster, angiv tidslinjenavn, importér. |
| **Studio** | Vælg aktiv tidslinje, tilføj/rediger historier, justér visning (tandhjul), kopiér shortcode. |
| **Tidslinjer** | Administrér navngivne samlinger (taxonomy `timeline_set`). |
| **Alle historier** | Klassisk WordPress-liste over CPT `timeline_story` (valgfri). |

### Navngivne tidslinjer

Hver tidslinje er en navngiven samling med eget **slug**, egne **visningsindstillinger** (farver, typografi-tema, animation) og en mængde **historier**.

Eksempel på shortcode:

```
[timeline_history timeline="hodde-paa-arkiv-dk"]
```

### Visning og tema

I Studio åbner **tandhjulet** panel med:

- **Tema** (Minimal, Kort, Editorial, Arkiv, Moderne, Kulturarv) - styrer typografi og kortstil.
- **Farver** - sidebaggrund, kort, linje, accent, tekst, dæmpet tekst; hurtige paletter.
- **Følg WordPress-tema** (standard) - på siderne hentes manglende farver fra aktivt tema; manuelt valgte farver overskriver enkeltfelter.

På **forsiden** hydreres tidslinjen med JavaScript (`view.js`) og samme React-komponent som i preview.

### Indhold på hver historie

| Felt | Brug |
|------|------|
| Titel | Overskrift på kortet |
| Dato | Placering på tidslinjen |
| Beskrivelse | Brødtekst på kortet |
| Bemærkning | Vises i billed-popup (fx fra arkiv.dk) |
| Billeder | Ét eller flere (slideshow); ved import typisk eksterne URLs til arkiv.dk |
| Kilde-link | "Åbn på arkiv.dk" |

---

## Teknisk arkitektur

### Monorepo

```
timeline-history/
├── packages/timeline-core/     # Datamodel, TimelineView, CSS, view.js (hydrering)
├── packages/timeline-builder/  # Studio (React), styles, builder.js til wp-admin
├── apps/playground/            # Lokal Vite-demo uden WordPress
├── wordpress-plugin/timeline-history/
│   ├── includes/               # PHP: CPT, REST, import, shortcode, assets
│   ├── assets/dist/            # Byggede view.js, timeline.css, builder.js, …
│   └── blocks/timeline/        # Gutenberg-blok
├── scripts/                    # copy-assets.mjs, pack-plugin.mjs
└── docs/                       # Dokumentation
```

### Én datamodel

`TimelineConfig` + `TimelineStory[]` er defineret i `@timeline-history/timeline-core` og bruges overalt:

- Playground (`localStorage`)
- REST `GET /wp-json/timeline-history/v1/preview`
- Shortcode/blok (JSON i `<script type="application/json">` på `.th-embed`)

Der er **ingen separat PHP/HTML-skabelon** for hver historie på forsiden. Alt renderes via `TimelineView`.

### Rendering

| Kontekst | Motor |
|----------|--------|
| Studio / playground | React `TimelineView` direkte |
| WordPress frontend | `view.js` finder `.th-embed`, læser JSON, kalder `createRoot` + `TimelineView` |

### WordPress-indholdsmodel

- **CPT** `timeline_story` med meta (dato, billeder, bemærkning, rækkefølge, …)
- **Taxonomy** `timeline_set` - navngivne tidslinjer med gemte visningsindstillinger per term
- **Taxonomy** `timeline_category` - filtrering i shortcode/blok (valgfri)

### REST API (uddrag)

- `GET/POST` historier, `POST` settings per tidslinje
- `GET` preview til Studio
- Arkiv-import: server-side klient mod arkiv.dk (forhåndsvisning og import)

### Tematilpasning på forsiden

1. **PHP** læser hints fra `theme.json` / customizer (`Timeline_History_Theme_Adapt`).
2. **JavaScript** supplerer med faktiske farver fra DOM (block-tema-variabler, linkfarve, neutral baggrund).
3. Eksplicit gemte farver i Studio **overskrives ikke**.

Layout på forsiden: embed med **alignwide** (op til ca. 1400px), container queries ved smal bredde, CSS-reset mod tema-konflikter.

---

## Udvikling og build

### Lokal demo (uden WordPress)

```bash
npm install
npm run build
npm run dev
```

Åbn [http://localhost:5173](http://localhost:5173) for playground med Admin og front-simulation.

### WordPress med Docker (wp-env)

```bash
npm run build
npm run wp:start
```

Standard: [http://localhost:8888](http://localhost:8888)

### Byg plugin til produktion

```bash
npm run pack:plugin
```

- Kører `npm run build`
- Øger **patch-version** automatisk (med `--no-bump` eller `--version=x.y.z` kan det styres)
- Opretter `dist/timeline-history.zip` klar til upload i wp-admin

---

## Integration på sider

### Shortcode

```
[timeline_history timeline="dit-slug"]
```

Valgfrie attributter: `layout`, `theme`, `skin`, `animation`, `category`, `limit`, `show_media`.

### Gutenberg

Blokken **ArkivTimeline** under Widgets. Vælg tidslinje-slug; understøtter **Bred** / **Fuld bredde** i editoren.

---

## Designprincipper

1. **Arkiv først** - import fra arkiv.dk, bevarelse af kildehenvisninger og bemærkninger.
2. **Én sandhed** - samme React-visning i Studio og på live side.
3. **Tema-venlig** - automatisk farvetilpasning som standard; redaktør kan låse look per tidslinje.
4. **Vedligeholdbar kode** - TypeScript/React i packages, tynd PHP-lag, build pipeline uden manuel kopiering.

---

## Roadmap og referencer

- Detaljeret dataflow og build-rækkefølge: [architecture.md](./architecture.md)
- Udvikler-README (kommandoer, checklist): [../README.md](../README.md)
- Mappen `cool-timeline-pro/` er legacy-reference og er ikke en del af ArkivTimeline-produktet.

---

## Licens

GPL-2.0-or-later (som WordPress-plugin).

---

## Relateret materiale

- **[Brugervejledning til brochure/InDesign](arkivtimeline-brugerguide.md)** – ikke-teknisk tekst med plads til 13 screendumps
- Ren tekstversion til *Placér* i InDesign: `arkivtimeline-indesign.txt`
