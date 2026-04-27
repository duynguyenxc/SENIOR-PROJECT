/**
 * Generates Veg-Buffet-User-Manual.pdf from the 4 docs HTML files.
 * Uses the system-installed Chrome and puppeteer-core.
 * Run: node generate-pdf.js
 */

const puppeteer = require('puppeteer-core');
const { PDFDocument } = require('pdf-lib');
const path = require('path');
const fs = require('fs');

const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const DOCS_DIR = path.resolve(__dirname, 'docs');
const OUTPUT_FILE = path.resolve(__dirname, 'Veg-Buffet-User-Manual.pdf');

const PAGES = [
  { file: 'index.html',          label: 'Introduction' },
  { file: 'getting-started.html', label: 'Dependencies & Setup' },
  { file: 'user-guide.html',     label: 'User Guide' },
  { file: 'administration.html', label: 'Administration & Maintenance' },
];

// Print CSS injected into every page to hide the sidebar and
// make the layout print-friendly.
const PRINT_CSS = `
  .sidebar { display: none !important; }
  .wrapper { display: block !important; }
  .main    { max-width: 100% !important; margin: 0 !important; padding: 0 !important; }
  body     { font-size: 11pt !important; }
  pre, code { font-size: 9pt !important; white-space: pre-wrap !important; word-break: break-all !important; }
  img      { max-width: 100% !important; height: auto !important; }
  a        { color: #1d4ed8; text-decoration: none; }
  .note    { border: 1px solid #ccc; padding: 8px 12px; margin: 12px 0; border-radius: 4px; }
  .note-danger { border-color: #dc2626; }
  .note-warn   { border-color: #d97706; }
  .steps       { padding-left: 0; }
  .step        { display: flex; gap: 12px; margin-bottom: 16px; }
  .step-num    { font-weight: bold; font-size: 14pt; min-width: 28px; }
  figure       { margin: 12px 0; }
  figure img   { border: 1px solid #ddd; }
  figcaption   { font-size: 9pt; color: #555; margin-top: 4px; }
  .hero-links, .card-grid { display: none !important; }
  h1 { font-size: 20pt; margin-top: 0; }
  h2 { font-size: 15pt; margin-top: 24px; page-break-before: auto; }
  h3 { font-size: 12pt; }
  table { border-collapse: collapse; width: 100%; font-size: 10pt; }
  th, td { border: 1px solid #ccc; padding: 5px 8px; text-align: left; }
  th { background: #f5f5f5; }
  .flow { display: flex; flex-wrap: wrap; gap: 6px; margin: 8px 0; }
  .flow-step { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 3px 8px; border-radius: 4px; font-size: 10pt; }
  .flow-arrow { padding: 3px 2px; color: #888; }
  .table-wrap { overflow: visible; }
`;

async function printPageToPdf(browser, filePath) {
  const page = await browser.newPage();
  const url = `file:///${filePath.replace(/\\/g, '/')}`;
  await page.goto(url, { waitUntil: 'networkidle0', timeout: 30000 });

  // Inject print-friendly CSS
  await page.addStyleTag({ content: PRINT_CSS });

  // Wait a moment for any dynamic content
  await new Promise(r => setTimeout(r, 500));

  const pdfBuffer = await page.pdf({
    format: 'A4',
    margin: { top: '20mm', bottom: '20mm', left: '22mm', right: '22mm' },
    printBackground: true,
  });

  await page.close();
  return pdfBuffer;
}

async function main() {
  console.log('Launching Chrome...');
  const browser = await puppeteer.launch({
    executablePath: CHROME_PATH,
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  const pdfBuffers = [];

  for (const { file, label } of PAGES) {
    const filePath = path.join(DOCS_DIR, file);
    if (!fs.existsSync(filePath)) {
      console.warn(`  Skipping (not found): ${file}`);
      continue;
    }
    console.log(`  Printing: ${label} (${file})...`);
    const buf = await printPageToPdf(browser, filePath);
    pdfBuffers.push(buf);
  }

  await browser.close();

  console.log('Merging PDFs...');
  const merged = await PDFDocument.create();

  for (const buf of pdfBuffers) {
    const src = await PDFDocument.load(buf);
    const copiedPages = await merged.copyPages(src, src.getPageIndices());
    copiedPages.forEach(p => merged.addPage(p));
  }

  const finalPdf = await merged.save();
  fs.writeFileSync(OUTPUT_FILE, finalPdf);

  const pageCount = merged.getPageCount();
  const sizeMb = (finalPdf.byteLength / 1024 / 1024).toFixed(1);
  console.log(`\nDone! ${pageCount} pages, ${sizeMb} MB`);
  console.log(`Saved to: ${OUTPUT_FILE}`);
}

main().catch(err => {
  console.error('Error:', err.message);
  process.exit(1);
});
