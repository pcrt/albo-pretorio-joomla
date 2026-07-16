# Albo Pretorio ≠ Amministrazione Trasparente — due regimi opposti

**Deliverable S2-T5.** Chiarisce perché l'Albo Pretorio Online e la sezione *Amministrazione Trasparente* di un sito PA, pur pubblicando entrambi documenti, seguono regole **opposte** su durata e indicizzazione. Confonderli è una delle cause più comuni di sanzione del Garante.

> **Questo componente gestisce l'ALBO PRETORIO, non l'Amministrazione Trasparente.** Non usarlo per pubblicare i dati dell'art. 33/2013 (curricula, compensi, bandi permanenti, ecc.): quelli seguono il regime opposto descritto sotto.

---

## 1. Il confronto in una tabella

| | **Albo Pretorio** | **Amministrazione Trasparente** (D.Lgs. 33/2013) |
|---|---|---|
| **Finalità** | Efficacia/opponibilità dell'atto (pubblicità legale/notiziale) | Controllo diffuso, accountability |
| **Base normativa** | art. 32 L. 69/2009; artt. 124/134 TUEL (enti locali) | D.Lgs. 33/2013 |
| **Durata** | **Breve e tassativa** (15–45 gg) | **~5 anni** (art. 8 D.Lgs. 33/2013) |
| **Dopo la scadenza** | **Ritiro** (defissione) | **Permanenza** per il periodo di legge |
| **Indicizzazione motori** | **Vietata** (diritto all'oblio) | **Ammessa** (anzi favorita per l'accessibilità) |
| **Oggetto** | Documento integrale, temporaneo | Dati/informazioni, spesso strutturati |

All'albo **non** si applica l'arco quinquennale dell'art. 8 D.Lgs. 33/2013: sono due binari distinti.

---

## 2. Perché i due regimi sono opposti (e perché conta)

**Durata.** All'albo la pubblicazione ha efficacia *costitutiva/notiziale* per un tempo definito: scaduto il termine, tenere l'atto online **è privo di base giuridica** e diventa un trattamento illecito, nonostante fosse lecito all'origine. In Trasparenza invece la permanenza pluriennale **è** l'adempimento.

**Indicizzazione.** All'albo l'indicizzazione dai motori di ricerca è **vietata**: un atto scaduto non deve restare reperibile con una ricerca su nome e cognome (decontestualizzazione, diritto all'oblio). In Trasparenza l'indicizzazione è invece ammessa e utile.

**Il rischio concreto.** Il Garante (Linee guida 15/5/2014, doc. web 3134436) ha **sanzionato enti** per permanenza indebita oltre i termini, dati sanitari o IBAN esposti, con sanzioni **a partire da €10.000**. Il **titolare del trattamento è l'ente**; il fornitore del software è tipicamente **responsabile del trattamento** (art. 28 GDPR) e deve fornire le **misure tecniche** adeguate — che è esattamente ciò che questo componente implementa.

---

## 3. Come il componente fa rispettare il regime "albo"

Il componente è progettato attorno al regime dell'albo (temporaneità + non indicizzazione), all'opposto della Trasparenza:

- **Defissione automatica** alla scadenza (`publish_down`): lo scheduler archivia l'atto, che **esce dall'elenco pubblico**. Vedi `docs/compliance/01-calcolo-termini.md`.
- **Deindicizzazione**: impostazione predefinita del componente orientata a `noindex` (l'opposto del default `index, follow` di un CMS), con override per singolo atto. *(Chiusura completa — X-Robots-Tag/robots.txt e forzatura su archiviati — pianificata in S4-T1.)*
- **Diritto all'oblio**: dopo la scadenza, rimozione/anonimizzazione; possibilità di cancellare fisicamente i PDF; riattivazione di un atto in oblio **sbarrata** salvo revoca motivata e tracciata (riscontro M16).
- **Area riservata post-scadenza**: gli atti archiviati e i referti restano consultabili solo con permesso dedicato (`alboonline.download.archived`), non pubblicamente.
- **Durata come minimo di legge** e **date certe non alterabili** (riscontri M2, M9): i termini di impugnazione decorrono da date affidabili.

---

## 4. Nota operativa per il funzionario

- Non pubblicare qui i contenuti di *Amministrazione Trasparente*: hanno durata e indicizzazione opposte.
- Non prolungare un atto oltre il necessario "per sicurezza": alla scadenza deve essere ritirato.
- Verifica che il fuso orario del sito sia `Europe/Rome` (incide sulle date, vedi `01-calcolo-termini.md`).
- Per i dati particolari (salute, situazioni di disagio, minori) valuta l'oscuramento prima della pubblicazione: all'albo la diffusione di categorie particolari è fortemente limitata (art. 9 GDPR; art. 26 c.4 D.Lgs. 33/2013).

---

## 5. Riferimenti

- `docs/ricerca-normativa/01-pubblicita-legale.md` — §"Distinzione Albo Pretorio ↔ Amministrazione Trasparente".
- `docs/ricerca-normativa/03-privacy-gdpr.md` — Linee guida Garante 2014 (doc. web 3134436), deindicizzazione, oblio, sanzioni.
- Garante Privacy, FAQ "Trasparenza online" — https://www.garanteprivacy.it/faq/trasparenza-online
