# Eksport til InDesign

## Filer

| Fil | Beskrivelse |
|-----|-------------|
| **ArkivTimeline-brugerguide.docx** | Brugervejledning med overskrifter, figur-pladsholdere og grå “indsæt screenshot”-felter |
| **ArkivTimeline-brugerguide.idml** | IDML (åbnes direkte i InDesign) |

`.indd` kan kun gemmes **inde fra Adobe InDesign** (ej eksporterbar uden InDesign).

## Generer på ny

```bash
node scripts/export-brugerguide-docx.mjs
node scripts/export-brugerguide-idml.mjs
```

## Åbn i InDesign

### IDML (anbefalet)

1. **Fil → Åbn** → vælg `ArkivTimeline-brugerguide.idml`
2. Erstat pladsholdere med dine screendumps
3. **Fil → Gem som** → vælg **InDesign (.indd)**

### Word

1. **Fil → Åbn** → vælg `ArkivTimeline-brugerguide.docx`
2. InDesign konverterer teksten til en flerside-fil
3. **Fil → Gem som** → `.indd`

### Kit med billeder (valgfrit)

1. Opret mappe `ArkivTimeline-brochure/`
2. Kopiér `.docx` eller `.idml` + `Figur-01.png` … `Figur-13.png`
3. **Window → Links** i InDesign til at bytte pladsholderbokse ud med filer
