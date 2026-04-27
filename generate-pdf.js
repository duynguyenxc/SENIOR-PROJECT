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
  { file: 'index.html',           label: 'Introduction' },
  { file: 'getting-started.html', label: 'Dependencies & Setup' },
  { file: 'user-guide.html',      label: 'User Guide' },
  { file: 'administration.html',  label: 'Administration & Maintenance' },
];

// ─── Cover page ──────────────────────────────────────────────────────────────
const COVER_HTML = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<style>
  @page { size: A4; margin: 0; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    width: 210mm; height: 297mm;
    display: flex; flex-direction: column;
    background: #fff; color: #1a1a1a;
  }
  .top-bar {
    background: #1d4ed8;
    height: 10mm;
    width: 100%;
  }
  .content {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    padding: 20mm 25mm;
    gap: 0;
  }
  .tag {
    font-size: 10pt;
    color: #6b7280;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 10mm;
  }
  .title {
    font-size: 26pt;
    font-weight: 700;
    color: #111827;
    line-height: 1.25;
    margin-bottom: 3mm;
  }
  .subtitle {
    font-size: 13pt;
    color: #374151;
    margin-bottom: 18mm;
  }
  .divider {
    width: 60mm;
    height: 2px;
    background: #1d4ed8;
    margin-bottom: 18mm;
  }
  .meta-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11pt;
    text-align: left;
  }
  .meta-table tr td:first-child {
    color: #6b7280;
    width: 38mm;
    padding: 3mm 0;
    vertical-align: top;
  }
  .meta-table tr td:last-child {
    color: #111827;
    font-weight: 500;
    padding: 3mm 0;
    vertical-align: top;
  }
  .bottom-bar {
    background: #f3f4f6;
    padding: 6mm 25mm;
    display: flex;
    justify-content: space-between;
    font-size: 9pt;
    color: #9ca3af;
    border-top: 1px solid #e5e7eb;
  }
</style>
</head>
<body>
  <div class="top-bar"></div>
  <div class="content">
    <div class="tag">User Manual</div>
    <div class="title">Vegetarian Buffet<br>Restaurant Website</div>
    <div class="subtitle">Veg Buffet — Web-Based Takeout Ordering System</div>
    <div class="divider"></div>
    <table class="meta-table">
      <tr>
        <td>Course</td>
        <td>CSCI 487 &mdash; Senior Project</td>
      </tr>
      <tr>
        <td>Student</td>
        <td>Duy Nguyen &mdash; ddnguyen@go.olemiss.edu</td>
      </tr>
      <tr>
        <td>Sponsor</td>
        <td>Dr. Burger &mdash; cburger@olemiss.edu</td>
      </tr>
      <tr>
        <td>Live Demo</td>
        <td>https://veg-buffet-web.onrender.com/</td>
      </tr>
      <tr>
        <td>Source Code</td>
        <td>https://github.com/duynguyenxc/SENIOR-PROJECT</td>
      </tr>
      <tr>
        <td>Date</td>
        <td>${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</td>
      </tr>
    </table>
  </div>
  <div class="bottom-bar">
    <span>University of Mississippi</span>
    <span>Department of Computer and Information Science</span>
  </div>
</body>
</html>`;

// ─── Print CSS injected into content pages ────────────────────────────────────
const PRINT_CSS = `
  .sidebar { display: none !important; }
  .wrapper { display: block !important; }
  .main {
    max-width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    font-size: 10.5pt !important;
    line-height: 1.65 !important;
  }
  body { font-family: 'Segoe UI', Arial, sans-serif !important; color: #1a1a1a !important; }
  pre, code {
    font-family: 'Consolas', 'Courier New', monospace !important;
    font-size: 8.5pt !important;
    white-space: pre-wrap !important;
    word-break: break-all !important;
    background: #f8f8f8 !important;
    border: 1px solid #e0e0e0 !important;
    border-radius: 3px !important;
    padding: 1px 4px !important;
  }
  pre { padding: 8px 10px !important; }
  img { max-width: 100% !important; height: auto !important; page-break-inside: avoid; }
  a   { color: #1d4ed8 !important; text-decoration: none !important; }

  .note {
    border-left: 4px solid #9ca3af;
    background: #f9fafb !important;
    padding: 8px 12px !important;
    margin: 10px 0 !important;
    border-radius: 0 4px 4px 0 !important;
    page-break-inside: avoid;
  }
  .note-danger { border-left-color: #dc2626 !important; background: #fff5f5 !important; }
  .note-warn   { border-left-color: #d97706 !important; background: #fffbeb !important; }
  .note-info   { border-left-color: #2563eb !important; background: #eff6ff !important; }
  .note-tip    { border-left-color: #16a34a !important; background: #f0fdf4 !important; }
  .note-title  { font-weight: 600 !important; font-size: 9.5pt !important; margin-bottom: 4px !important; }

  .steps { padding-left: 0 !important; }
  .step  { display: flex !important; gap: 14px !important; margin-bottom: 18px !important; page-break-inside: avoid; }
  .step-num {
    font-weight: 700 !important;
    font-size: 16pt !important;
    color: #1d4ed8 !important;
    min-width: 30px !important;
    line-height: 1 !important;
    padding-top: 2px !important;
  }
  .step-body h3 { margin-top: 0 !important; }

  figure      { margin: 10px 0 !important; page-break-inside: avoid; }
  figure img  { border: 1px solid #ddd !important; border-radius: 3px !important; }
  figcaption  { font-size: 8.5pt !important; color: #6b7280 !important; margin-top: 5px !important; font-style: italic; }

  .hero       { border-bottom: 2px solid #e5e7eb; padding-bottom: 14px !important; margin-bottom: 14px !important; }
  .hero-sub   { color: #374151 !important; font-size: 11pt !important; }
  .hero-links, .card-grid { display: none !important; }

  h1 { font-size: 19pt !important; color: #111827 !important; padding-bottom: 6px !important; border-bottom: 2px solid #e5e7eb !important; margin-bottom: 16px !important; }
  h2 { font-size: 14pt !important; color: #1e3a5f !important; margin-top: 22px !important; padding-bottom: 4px !important; border-bottom: 1px solid #e5e7eb !important; page-break-before: auto; }
  h3 { font-size: 11.5pt !important; color: #1a1a1a !important; margin-top: 14px !important; }
  h4 { font-size: 10.5pt !important; margin-top: 10px !important; }

  table { border-collapse: collapse !important; width: 100% !important; font-size: 9.5pt !important; page-break-inside: avoid; margin: 8px 0 !important; }
  th, td { border: 1px solid #d1d5db !important; padding: 5px 8px !important; text-align: left !important; }
  th { background: #f3f4f6 !important; font-weight: 600 !important; }
  tr:nth-child(even) td { background: #fafafa !important; }

  .flow       { display: flex !important; flex-wrap: wrap !important; gap: 6px !important; margin: 8px 0 !important; align-items: center !important; }
  .flow-step  { background: #f0fdf4 !important; border: 1px solid #bbf7d0 !important; padding: 3px 9px !important; border-radius: 4px !important; font-size: 9.5pt !important; }
  .flow-arrow { color: #9ca3af !important; font-size: 11pt !important; }

  .table-wrap { overflow: visible !important; }
  .code-filename {
    font-size: 8pt !important; color: #6b7280 !important;
    background: #e5e7eb !important; padding: 2px 8px !important;
    border-radius: 3px 3px 0 0 !important; display: inline-block !important;
    margin-bottom: -1px !important;
  }
  hr { border: none !important; border-top: 1px solid #e5e7eb !important; margin: 16px 0 !important; }
`;

// ─── Footer template (page numbers) ──────────────────────────────────────────
const FOOTER = `<div style="
  width: 100%; font-size: 8pt; color: #9ca3af;
  display: flex; justify-content: space-between;
  padding: 0 22mm; font-family: Arial, sans-serif;
  border-top: 1px solid #e5e7eb; padding-top: 2mm;
">
  <span>Veg Buffet — User Manual</span>
  <span><span class="pageNumber"></span> / <span class="totalPages"></span></span>
</div>`;

// ─── Helpers ──────────────────────────────────────────────────────────────────
async function renderHtmlToPdf(browser, html, isContentPage = false) {
  const page = await browser.newPage();
  await page.setContent(html, { waitUntil: 'networkidle0' });

  if (isContentPage) {
    await page.addStyleTag({ content: PRINT_CSS });
    await new Promise(r => setTimeout(r, 600));
  }

  const buf = await page.pdf({
    format: 'A4',
    margin: isContentPage
      ? { top: '18mm', bottom: '22mm', left: '22mm', right: '22mm' }
      : { top: '0', bottom: '0', left: '0', right: '0' },
    printBackground: true,
    displayHeaderFooter: isContentPage,
    headerTemplate: '<div></div>',
    footerTemplate: isContentPage ? FOOTER : '<div></div>',
  });

  await page.close();
  return buf;
}

async function renderFileToPdf(browser, filePath) {
  const page = await browser.newPage();
  const url = `file:///${filePath.replace(/\\/g, '/')}`;
  await page.goto(url, { waitUntil: 'networkidle0', timeout: 30000 });
  await page.addStyleTag({ content: PRINT_CSS });
  await new Promise(r => setTimeout(r, 600));

  const buf = await page.pdf({
    format: 'A4',
    margin: { top: '18mm', bottom: '22mm', left: '22mm', right: '22mm' },
    printBackground: true,
    displayHeaderFooter: true,
    headerTemplate: '<div></div>',
    footerTemplate: FOOTER,
  });

  await page.close();
  return buf;
}

// ─── Main ─────────────────────────────────────────────────────────────────────
async function main() {
  console.log('Launching Chrome...');
  const browser = await puppeteer.launch({
    executablePath: CHROME_PATH,
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  const pdfBuffers = [];

  // 1. Cover page
  console.log('  Generating cover page...');
  pdfBuffers.push(await renderHtmlToPdf(browser, COVER_HTML, false));

  // 2. Content pages
  for (const { file, label } of PAGES) {
    const filePath = path.join(DOCS_DIR, file);
    if (!fs.existsSync(filePath)) {
      console.warn(`  Skipping (not found): ${file}`);
      continue;
    }
    console.log(`  Printing: ${label}...`);
    pdfBuffers.push(await renderFileToPdf(browser, filePath));
  }

  await browser.close();

  // 3. Merge all PDFs
  console.log('Merging PDFs...');
  const merged = await PDFDocument.create();
  for (const buf of pdfBuffers) {
    const src = await PDFDocument.load(buf);
    const pages = await merged.copyPages(src, src.getPageIndices());
    pages.forEach(p => merged.addPage(p));
  }

  // 4. Set document metadata
  merged.setTitle('Veg Buffet — User Manual');
  merged.setAuthor('Duy Nguyen');
  merged.setSubject('CSCI 487 Senior Project — User Manual');
  merged.setKeywords(['Veg Buffet', 'User Manual', 'Senior Project', 'CSCI 487']);

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
