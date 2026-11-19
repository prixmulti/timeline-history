/**
 * Eksporterer docs/arkivtimeline-brugerguide.md til Word (.docx) til InDesign.
 * Kør: node scripts/export-brugerguide-docx.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  Document,
  HeadingLevel,
  Packer,
  Paragraph,
  Table,
  TableCell,
  TableRow,
  TextRun,
  WidthType,
  BorderStyle,
  ShadingType,
  AlignmentType,
} from 'docx';

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const argMd = process.argv[2];
const argDocx = process.argv[3];
const src = argMd
  ? path.resolve(root, argMd)
  : path.join(root, 'docs/arkivtimeline-brugerguide.md');
const outDir = path.join(root, 'docs/export');
const outDocx = argDocx
  ? path.resolve(root, argDocx)
  : path.join(outDir, 'ArkivTimeline-brugerguide.docx');

const md = fs.readFileSync(src, 'utf8');
const lines = md.split(/\r?\n/);

function stripMd(s) {
  return s
    .replace(/\*\*([^*]+)\*\*/g, '$1')
    .replace(/\*([^*]+)\*/g, '$1')
    .replace(/`([^`]+)`/g, '$1')
    .trim();
}

const children = [];

for (let i = 0; i < lines.length; i++) {
  const raw = lines[i];
  const line = raw.trimEnd();

  if (line === '---' || line === '') continue;

  if (line.startsWith('# ')) {
    children.push(
      new Paragraph({
        heading: HeadingLevel.TITLE,
        spacing: { after: 200 },
        children: [new TextRun({ text: stripMd(line.slice(2)), bold: true, size: 36 })],
      })
    );
    continue;
  }

  if (line.startsWith('## ')) {
    children.push(
      new Paragraph({
        heading: HeadingLevel.HEADING_1,
        spacing: { before: 280, after: 120 },
        children: [new TextRun({ text: stripMd(line.slice(3)), bold: true, size: 28 })],
      })
    );
    continue;
  }

  if (line.startsWith('### ')) {
    children.push(
      new Paragraph({
        heading: HeadingLevel.HEADING_2,
        spacing: { before: 200, after: 80 },
        children: [new TextRun({ text: stripMd(line.slice(4)), bold: true, size: 24 })],
      })
    );
    continue;
  }

  if (line.startsWith('**[FIGUR')) {
    const figTitle = stripMd(line);
    children.push(
      new Paragraph({
        spacing: { before: 240, after: 80 },
        shading: { type: ShadingType.CLEAR, fill: 'E8EFE9' },
        children: [
          new TextRun({ text: figTitle, bold: true, color: '3D5A4C', size: 22 }),
        ],
      })
    );
    children.push(
      new Paragraph({
        alignment: AlignmentType.CENTER,
        spacing: { before: 40, after: 40 },
        shading: { type: ShadingType.CLEAR, fill: 'F4F0E8' },
        children: [
          new TextRun({
            text: '↳ INDsÆT SCREENDUMP HER (ca. hel spaltebredde)',
            italics: true,
            color: '5F6E66',
            size: 20,
          }),
        ],
      })
    );
    continue;
  }

  if (line.startsWith('*Billede:') || line.startsWith('*Billedtekst')) {
    children.push(
      new Paragraph({
        spacing: { after: 60 },
        children: [
          new TextRun({ text: stripMd(line), italics: true, size: 20, color: '5F6E66' }),
        ],
      })
    );
    continue;
  }

  if (line.startsWith('|') && lines[i + 1]?.includes('---')) {
    const header = line
      .split('|')
      .map((c) => c.trim())
      .filter(Boolean);
    i += 2;
    const rows = [];
    rows.push(
      new TableRow({
        children: header.map(
          (h) =>
            new TableCell({
              width: { size: 33, type: WidthType.PERCENTAGE },
              children: [new Paragraph({ children: [new TextRun({ text: h, bold: true })] })],
            })
        ),
      })
    );
    while (i < lines.length && lines[i].trim().startsWith('|')) {
      const cols = lines[i]
        .split('|')
        .map((c) => c.trim())
        .filter(Boolean);
      rows.push(
        new TableRow({
          children: cols.map(
            (c) =>
              new TableCell({
                children: [new Paragraph({ children: [new TextRun({ text: c })] })],
              })
          ),
        })
      );
      i++;
    }
    children.push(
      new Table({
        width: { size: 100, type: WidthType.PERCENTAGE },
        borders: {
          top: { style: BorderStyle.SINGLE, size: 1, color: 'CCCCCC' },
          bottom: { style: BorderStyle.SINGLE, size: 1, color: 'CCCCCC' },
          left: { style: BorderStyle.SINGLE, size: 1, color: 'CCCCCC' },
          right: { style: BorderStyle.SINGLE, size: 1, color: 'CCCCCC' },
          insideHorizontal: { style: BorderStyle.SINGLE, size: 1, color: 'CCCCCC' },
          insideVertical: { style: BorderStyle.SINGLE, size: 1, color: 'CCCCCC' },
        },
        rows,
      })
    );
    i--;
    continue;
  }

  if (line.startsWith('- ') || /^\d+\.\s/.test(line)) {
    const text = stripMd(line.replace(/^(-|\d+\.)\s*/, ''));
    children.push(
      new Paragraph({
        bullet: { level: 0 },
        spacing: { after: 40 },
        children: [new TextRun({ text, size: 22 })],
      })
    );
    continue;
  }

  if (line.startsWith('*') && line.endsWith('*') && !line.startsWith('**')) {
    children.push(
      new Paragraph({
        spacing: { after: 120 },
        children: [new TextRun({ text: stripMd(line), italics: true, size: 20 })],
      })
    );
    continue;
  }

  const body = stripMd(line);
  if (body) {
    children.push(
      new Paragraph({
        spacing: { after: 100 },
        children: [new TextRun({ text: body, size: 22 })],
      })
    );
  }
}

const doc = new Document({
  styles: {
    default: {
      document: {
        run: { font: 'Arial', size: 22 },
      },
    },
  },
  sections: [
    {
      properties: {
        page: {
          margin: { top: 1134, right: 1134, bottom: 1134, left: 1134 },
        },
      },
      children,
    },
  ],
});

fs.mkdirSync(outDir, { recursive: true });
const buffer = await Packer.toBuffer(doc);
fs.writeFileSync(outDocx, buffer);
console.log(`Skrev ${path.relative(root, outDocx)}`);
console.log('InDesign: Fil → Åbn → vælg .docx → Gem som .indd');
