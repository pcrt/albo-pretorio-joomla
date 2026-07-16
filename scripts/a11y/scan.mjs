/**
 * Scansione axe-core delle pagine pubbliche del componente. Dev-only.
 *
 * Uso (dentro il container):
 *   node /var/www/html/tmp/a11y/scan.mjs https://albo-j5.ddev.site
 *
 * LIMITE DA DICHIARARE: gira su jsdom, non su un browser vero. Quindi:
 *   - il CONTRASTO COLORI non è verificabile (serve il rendering) — e dipende comunque dal
 *     tema dell'ente, che è fuori dal perimetro del componente;
 *   - il layout (zoom 200%, reflow 320px) non è verificabile qui.
 * Copre invece struttura, ruoli, nomi accessibili, etichette, ordine delle intestazioni:
 * cioè esattamente la parte che è NOSTRA e che nessun tema può riparare.
 */

import { JSDOM } from 'jsdom';
import axe from 'axe-core';

const base = process.argv[2] || 'https://albo-j5.ddev.site';

const pagine = [
  ['lista atti', `${base}/index.php?option=com_alboonline&view=atti`],
];

// L'id dell'atto pubblicato e di quello archiviato arrivano da riga di comando, se passati.
if (process.argv[3]) pagine.push(['dettaglio pubblicato', `${base}/index.php?option=com_alboonline&view=atto&id=${process.argv[3]}`]);
if (process.argv[4]) pagine.push(['dettaglio archiviato', `${base}/index.php?option=com_alboonline&view=atto&id=${process.argv[4]}`]);

let violazioniTotali = 0;

for (const [nome, url] of pagine) {
  const risposta = await fetch(url);
  const html = await risposta.text();

  const dom = new JSDOM(html, { url, pretendToBeVisual: true, runScripts: 'outside-only' });
  const { window } = dom;

  // axe si aspetta di girare dentro una finestra: gli passiamo quella di jsdom.
  window.eval(axe.source);

  const esito = await window.axe.run(window.document, {
    resultTypes: ['violations'],
    rules: {
      // Non giudicabile senza rendering reale: dichiarato, non nascosto.
      'color-contrast': { enabled: false },
    },
  });

  const gravi = esito.violations.filter((v) => ['critical', 'serious'].includes(v.impact));
  const minori = esito.violations.filter((v) => !['critical', 'serious'].includes(v.impact));

  console.log(`\n=== ${nome}`);
  console.log(`    ${esito.passes ? '' : ''}violazioni: ${esito.violations.length} (gravi: ${gravi.length})`);

  for (const v of esito.violations) {
    console.log(`  [${v.impact}] ${v.id} — ${v.help}`);
    console.log(`     ${v.nodes.length} elemento/i · ${v.helpUrl.split('?')[0]}`);
    for (const n of v.nodes) {
      console.log(`     → ${n.html.replace(/\s+/g, ' ').slice(0, 110)}`);
    }
  }

  violazioniTotali += gravi.length;
}

console.log(`\n================================`);
console.log(`  violazioni gravi (critical/serious): ${violazioniTotali}`);
console.log(`  NB: contrasto colori e layout NON verificabili qui (serve un browser reale)`);
console.log(`================================`);
process.exit(violazioniTotali === 0 ? 0 : 1);
