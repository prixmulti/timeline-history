/**
 * Simpel IDML (InDesign Markup) – åbnes i InDesign og gemmes som .indd.
 * Kør: node scripts/export-brugerguide-idml.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import JSZip from 'jszip';

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const src = path.join(root, 'docs/arkivtimeline-brugerguide.md');
const outDir = path.join(root, 'docs/export');
const outIdml = path.join(outDir, 'ArkivTimeline-brugerguide.idml');

const md = fs.readFileSync(src, 'utf8');

function stripMd(s) {
  return s
    .replace(/\*\*([^*]+)\*\*/g, '$1')
    .replace(/\*([^*]+)\*/g, '$1')
    .replace(/`([^`]+)`/g, '$1')
    .trim();
}

function escXml(s) {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/** IDML ParagraphStyleRange med Content */
function para(text, style = 'ParagraphStyle/$ID/NormalParagraphStyle') {
  if (!text) return '';
  return `<ParagraphStyleRange AppliedParagraphStyle="${style}"><CharacterStyleRange AppliedCharacterStyle="CharacterStyle/$ID/[No character style]"><Content>${escXml(text)}</Content><Br /></CharacterStyleRange></ParagraphStyleRange>`;
}

function heading(text) {
  return para(text, 'ParagraphStyle/$ID/Heading 1');
}

function figureBlock(figLine, nextLines) {
  let xml = heading(figLine);
  xml += para('——— Indsæt screendump her ———');
  for (const ln of nextLines) {
    if (ln.startsWith('**[FIGUR') || ln === '---' || ln.startsWith('##')) break;
    if (!ln.trim()) continue;
    xml += para(stripMd(ln));
  }
  return xml;
}

const lines = md.split(/\r?\n/);
let storyXml = '';

for (let i = 0; i < lines.length; i++) {
  const line = lines[i].trimEnd();
  if (line === '---' || !line.trim()) continue;

  if (line.startsWith('# ')) {
    storyXml += heading(stripMd(line.slice(2)));
    continue;
  }
  if (line.startsWith('## ')) {
    storyXml += heading(stripMd(line.slice(3)));
    continue;
  }
  if (line.startsWith('### ')) {
    storyXml += para(stripMd(line.slice(4)));
    continue;
  }
  if (line.startsWith('**[FIGUR')) {
    const tail = [];
    for (let j = i + 1; j < Math.min(i + 6, lines.length); j++) {
      tail.push(lines[j]);
    }
    storyXml += figureBlock(stripMd(line), tail);
    i += tail.filter((t) => t.trim() && !t.startsWith('**[FIGUR')).length;
    continue;
  }
  if (line.startsWith('|')) continue;
  if (line.startsWith('- ') || /^\d+\.\s/.test(line)) {
    storyXml += para('• ' + stripMd(line.replace(/^(-|\d+\.)\s*/, '')));
    continue;
  }
  storyXml += para(stripMd(line));
}

const storySelf = 'uStory';
const spreadSelf = 'uSpread';
const masterSelf = 'uMaster';
const textFrameSelf = 'uTextFrame';

const designmap = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<?aid style="50" type="document" readerVersion="6.0" featureSet="257" product="19.0" ?>
<Document DOMVersion="19.0" Self="d" StoryList="${storySelf}" ZeroPoint="0 0" ActiveLayer="u8a" CMYKProfile="$ID/" RGBProfile="$ID/sRGB IEC61966-2.1" SolidColorIntent="UseColorSettings" AfterBlendingIntent="UseColorSettings" DefaultImageIntent="UseColorSettings" RGBPolicy="PreserveEmbeddedProfiles" CMYKPolicy="CombinationOfPreserveAndSafeCmyk" AccurateLABSpots="false">
\t<idPkg:MasterSpread src="MasterSpreads/MasterSpread_${masterSelf}.xml" xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" />
\t<idPkg:Spread src="Spreads/Spread_${spreadSelf}.xml" xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" />
\t<Language Self="Language/$ID/Danish" Name="$ID/Danish" SingleQuotes="‚’" DoubleQuotes="„“" PrimaryLanguageName="$ID/Danish" SublanguageName="$ID/" Id="105" HyphenationVendor="Hunspell" SpellingVendor="Hunspell" />
\t<idPkg:Styles src="Resources/Styles.xml" xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" />
\t<idPkg:Graphic src="Resources/Graphic.xml" xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" />
\t<idPkg:Fonts src="Resources/Fonts.xml" xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" />
\t<idPkg:Preferences src="Resources/Preferences.xml" xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" />
\t<idPkg:Story src="Stories/Story_${storySelf}.xml" xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" />
</Document>`;

const container = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
\t<rootfiles>
\t\t<rootfile full-path="designmap.xml" media-type="text/xml"/>
\t</rootfiles>
</container>`;

const backingStory = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<?aid style="50" type="snippet" readerVersion="6.0" featureSet="257" product="19.0"?>
<idPkg:Snippet xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" DOMVersion="19.0">
\t<Story Self="${storySelf}" UserText="true" IsEndnoteStory="false" AppliedTOCStyle="n" TrackChanges="false" StoryTitle="$ID/" AppliedNamedGrid="n">${storyXml}</Story>
</idPkg:Snippet>`;

const storyFile = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<?aid style="50" type="snippet" readerVersion="6.0" featureSet="257" product="19.0"?>
<idPkg:Story xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" DOMVersion="19.0">
\t<Story Self="${storySelf}" UserText="true" IsEndnoteStory="false" AppliedTOCStyle="n" TrackChanges="false" StoryTitle="$ID/" AppliedNamedGrid="n">${storyXml}</Story>
</idPkg:Story>`;

const masterSpread = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<idPkg:MasterSpread xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" DOMVersion="19.0">
\t<MasterSpread Self="${masterSelf}" ItemTransform="1 0 0 1 0 0" OverprintBlack="true" PageCount="1" ShowMasterItems="true">
\t\t<Page Self="uMasterPage" Name="A" AppliedTrapPreset="TrapPreset/$ID/kDefaultTrapStyleName" GeometricBounds="0 0 841.89 595.276" ItemTransform="1 0 0 1 0 0" />
\t</MasterSpread>
</idPkg:MasterSpread>`;

const spread = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<idPkg:Spread xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" DOMVersion="19.0">
\t<Spread Self="${spreadSelf}" PageCount="1" ShowMasterItems="true">
\t\t<Page Self="uPage" AppliedMaster="${masterSelf}" Name="1" GeometricBounds="0 0 841.89 595.276" ItemTransform="1 0 0 1 0 0">
\t\t\t<MarginPreference ColumnCount="1" ColumnGutter="12" Top="56.69" Bottom="56.69" Left="56.69" Right="56.69" />
\t\t\t<TextFrame Self="${textFrameSelf}" ParentStory="${storySelf}" PreviousTextFrame="n" NextTextFrame="n" ContentType="TextType" ItemTransform="1 0 0 1 56.69 56.69" GeometricBounds="0 0 728.5 482" Visible="true" StrokeWeight="0">
\t\t\t\t<TextFramePreference TextColumnCount="1" TextColumnGutter="12" />
\t\t\t</TextFrame>
\t\t</Page>
\t</Spread>
</idPkg:Spread>`;

const styles = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<idPkg:Styles xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" DOMVersion="19.0">
\t<RootParagraphStyleGroup Self="Pandora">
\t\t<ParagraphStyle Self="ParagraphStyle/$ID/NormalParagraphStyle" Name="$ID/NormalParagraphStyle" />
\t\t<ParagraphStyle Self="ParagraphStyle/$ID/Heading 1" Name="$ID/Heading 1" BasedOn="ParagraphStyle/$ID/NormalParagraphStyle" PointSize="14" SpaceBefore="8" SpaceAfter="4" />
\t</RootParagraphStyleGroup>
\t<RootCharacterStyleGroup Self="Pandora">
\t\t<CharacterStyle Self="CharacterStyle/$ID/[No character style]" Name="$ID/[No character style]" />
\t</RootCharacterStyleGroup>
</idPkg:Styles>`;

const graphic = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<idPkg:Graphic xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" DOMVersion="19.0">
\t<Color Self="Color/$ID/Registration" Model="Registration" Space="All" Visible="false" />
\t<Color Self="Color/$ID/Paper" Model="Process" Space="CMYK" Visible="true" ColorValue="0 0 0 0" />
\t<Color Self="Color/$ID/Black" Model="Process" Space="CMYK" Visible="true" ColorValue="0 0 0 100" />
</idPkg:Graphic>`;

const fonts = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<idPkg:Fonts xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" DOMVersion="19.0" />`;

const preferences = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<idPkg:Preferences xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging" DOMVersion="19.0">
\t<DocumentPreference PageWidth="595.276" PageHeight="841.89" FacingPages="false" DocumentBleedTopOffset="0" DocumentBleedBottomOffset="0" DocumentBleedInsideOrLeftOffset="0" DocumentBleedOutsideOrRightOffset="0" />
</idPkg:Preferences>`;

const zip = new JSZip();
zip.file('mimetype', 'application/vnd.adobe.indesign-idml-package', { compression: 'STORE' });
zip.folder('META-INF')?.file('container.xml', container);
zip.file('designmap.xml', designmap);
zip.folder('MasterSpreads')?.file(`MasterSpread_${masterSelf}.xml`, masterSpread);
zip.folder('Spreads')?.file(`Spread_${spreadSelf}.xml`, spread);
zip.folder('Stories')?.file(`Story_${storySelf}.xml`, storyFile);
zip.folder('XML')?.file('BackingStory.xml', backingStory);
const resources = zip.folder('Resources');
resources?.file('Styles.xml', styles);
resources?.file('Graphic.xml', graphic);
resources?.file('Fonts.xml', fonts);
resources?.file('Preferences.xml', preferences);

// designmap references Story via StoryList - need idPkg:Story in designmap
// Fix: add story reference to designmap - actually stories are linked via spread textframe ParentStory

fs.mkdirSync(outDir, { recursive: true });
const buf = await zip.generateAsync({
  type: 'nodebuffer',
  compression: 'DEFLATE',
  platform: 'UNIX',
});

// IDML = zip; write as .idml
fs.writeFileSync(outIdml, buf);
console.log(`Skrev ${path.relative(root, outIdml)}`);
console.log('Åbn i InDesign → Fil → Gem som .indd');
