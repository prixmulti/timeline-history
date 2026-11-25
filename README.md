# Historical Timeline

WordPress-plugin til visuelle tidslinjer med import fra [arkiv.dk](https://www.arkiv.dk), redaktionelt **Studio** og visning på sider via shortcode eller Gutenberg-blok.

| | |
|---|---|
| **WordPress.org slug** | `prix-historical-timeline` (efter godkendelse) |
| **Plugin-kilde** | `wordpress-plugin/timeline-history/` |
| **Licens** | GPL-2.0-or-later |
| **Krav** | WordPress 6.4+, PHP 7.4+ |

## Hvem står bag?

**Historical Timeline** er udviklet af **Palle Rix** ([GitHub: @prixmulti](https://github.com/prixmulti), [prix.dk](https://prix.dk)).

Projektet er bygget til lokalarkiver og historieforeninger, der vil vise kronologiske historier på WordPress uden at genindtaste alt materiale fra arkiv.dk.

Har du spørgsmål, fejl eller idéer: åbn et [issue på GitHub](https://github.com/prixmulti/timeline-history/issues) eller brug kontakt via [prix.dk](https://prix.dk).

## Funktioner (kort)

- Import fra arkiv.dk med forhåndsvisning, årsfilter og valg af poster
- **Studio** med live preview, per-tidslinje visningsindstillinger og shortcode
- Shortcode `[timeline_history]` og blokken **Historical Timeline**
- Tilpasning til aktivt WordPress-tema (farver og typografi)

## Installér plugin (produktion)

### Fra release / zip

```bash
npm install
npm run pack:plugin
```

Upload **`dist/arkivtimeline.zip`** til WordPress.org-indsendelse (mappen i zip hedder `arkivtimeline/` indtil ny slug er reserveret). Til eget site: samme zip via **Plugins → Add New → Upload Plugin**. `pack:plugin` kører build og kan hæve patch-version i `timeline-history.php`. Brug `npm run pack:plugin -- --no-bump` eller `--version=x.y.z` efter behov.

### Når pluginet er på WordPress.org

Opdateringer til sites, der installerede fra **wordpress.org**, kommer via **Plugins → Opdateringer** – ikke automatisk fra GitHub. GitHub er kildekode og dokumentation; udgivelser sker via WordPress.org SVN efter godkendelse.

## Brug på sitet

**Shortcode:**

```
[timeline_history timeline="dit-tidslinje-slug"]
```

**Gutenberg:** blokken **Historical Timeline** (kategori Widgets).

Admin: **Historical Timeline → Import fra Arkiv.dk** og **Studio**.

## Udvikling (dette repo)

Monorepo: React/TypeScript til timeline-UI, PHP til WordPress-plugin, webpack til frontend-scripts med WordPress’ React-afhængigheder.

```bash
npm install
npm run build          # hele monorepo + plugin assets + blok
npm run dev            # lokal playground (Vite)
npm run pack:plugin    # zip til WordPress
```

Valgfri lokal WordPress med [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) (kræver Docker):

```bash
npm run build
npm run wp:start
```

Playground: [http://localhost:5173](http://localhost:5173) (`npm run dev`). WordPress: typisk [http://localhost:8888](http://localhost:8888).

## Repo-struktur

```
packages/timeline-core/     # Tidslinje-UI (view)
packages/timeline-builder/  # Studio (admin)
wordpress-plugin/timeline-history/  # Historical Timeline (PHP, blok, dist)
```

## Links

- **Kildekode:** https://github.com/prixmulti/timeline-history  
- **WordPress-plugin (directory):** https://wordpress.org/plugins/prix-historical-timeline/ *(når godkendt)*

---

Udviklet af **Palle Rix**. Tak til lokalarkiver og testmiljøer, der har givet feedback under udviklingen.