# Ricerca normativa — Design PA, riuso open source, interoperabilità

**Ambito:** linee guida di design/riuso rilevanti per un componente albo online open source.

## Design / UX (frontend)

Le **Linee guida di design per i servizi digitali della PA** (AgID, ex art. 53 c.1-ter CAD) sono **cogenti**:
- **Req. 4.5 (Interfaccia utente)**: coerenza di stile/UX, priorità al **Design System .italia**.
- **Req. 4.1**: conformità all'accessibilità (L. 4/2004 + EN 301 549).
- **Req. 4.2**: sicurezza e privacy by design (GDPR art. 25).
- **Req. 4.8**: la PA inserisce nei bandi la clausola di conformità (il fornitore dichiara l'aderenza).
https://docs.italia.it/italia/design/lg-design-servizi-web/it/versione-corrente/requisiti.html

**Design System .italia / Bootstrap Italia**: strumento per il req. 4.5. Bootstrap Italia v2 (tema su Bootstrap 5.2.3); v3 in beta. https://italia.github.io/bootstrap-italia/ · https://github.com/italia/bootstrap-italia
**Modello di sito per i Comuni**: architettura dell'informazione e template pronti; l'albo pretorio rientra nelle sezioni documentali. https://designers.italia.it/modelli/comuni/

> **Impatto sul progetto (decisione template):** sviluppo su **Cassiopeia** (default Joomla, Bootstrap 5) accessibile out-of-the-box; l'allineamento a Bootstrap Italia è un **add-on opzionale**, non dipendenza del core. Nessun obbligo di legge impone .italia a un componente riusabile: l'obbligo di design ricade sul **sito** della PA, non sulla singola estensione. Cassiopeia soddisfa l'accessibilità (req. 4.1), che è il vincolo cogente.

## Riuso open source nella PA (art. 69 CAD)

Le **Linee guida su acquisizione e riuso del software** (Developers Italia/AgID) impongono, per software di/per una PA, pubblicazione in repository pubblico con licenza aperta. Requisiti:
- **Repository pubblico** con **`publiccode.yml`** in root.
- **LICENSE** (testo integrale + identificativo SPDX in testa ai sorgenti).
- **README.md** con descrizione, prerequisiti, installazione, manutentore, **email di sicurezza** dedicata.
- **Rimozione di credenziali/segreti** anche dallo storico dei commit; documentazione in formati testuali (Markdown/HTML, **no PDF/DOCX**).
- **Registrazione** su https://onboarding.developers.italia.it: il crawler indicizza il `publiccode.yml` e genera la scheda nel catalogo.

**Licenza consigliata**: per software nuovo **EUPL-1.2**; per software fruito via rete **AGPL-3.0-or-later**.
https://docs.italia.it/italia/developers-italia/lg-acquisizione-e-riuso-software-per-pa-docs/it/stabile/riuso-software/licenze-aperte-e-scelta-di-una-licenza.html

> **Impatto (decisione #1):** per questo progetto si è scelta **GPL-2.0-or-later** (coerenza con l'ecosistema Joomla, che è GPL). Scelta legittima; da documentare nel README/publiccode.

**`publiccode.yml`** — campi chiave: `publiccodeYmlVersion`, `name`, `url`, `releaseDate`, `softwareVersion`, `legal.license`, `maintenance`; estensione italiana: `it.riuso.codiceIPA` (obbligatorio solo se il repoOwner è una PA — **non** il nostro caso), flag `it.conforme.*` (lineeGuidaDesign, modelloInteroperabilita, misureMinimeSicurezza, gdpr) e `it.piattaforme.*`. Schema/validatore: https://docs.italia.it/italia/developers-italia/publiccodeyml/it/master/schema.it.html

## Interoperabilità / metadati

**Nessuno standard tecnico specifico obbligatorio** per l'esposizione dati dell'albo. Obblighi rilevanti: formato **non modificabile**, integrità di documento e **metadati** (ente, data pubblicazione, scadenza, oggetto, allegati), minimizzazione. **Best practice** (non obbligo): formati aperti machine-readable, feed RSS/Atom+JSON, **Schema.org** (`GovernmentService`), **Dublin Core**. Il **Modello di Interoperabilità (ModI)** si applica alle API/PDND, non obbliga un feed dell'albo.

## Sicurezza minima

Flag `misureMinimeSicurezza` → **Misure Minime di Sicurezza ICT per le PA** (AgID) e perimetro **ACN**. Per un componente pubblicato: gestione vulnerabilità, canale di security disclosure (email dedicata), assenza di segreti nel codice.

## Zone grigie / opportunità

- Assenza di uno standard dati ufficiale per l'albo = **opportunità**: feed RSS/JSON + Schema.org + Dublin Core come tratto distintivo "interoperabile by design".
- Il Modello Comuni non copre esplicitamente l'albo → spazio per un componente di riferimento.
- Flag `it.conforme.*` valorizzati = maggiore visibilità nel catalogo.

## Fonti
- LG design servizi web — https://docs.italia.it/italia/design/lg-design-servizi-web/it/versione-corrente/requisiti.html
- LG riuso software — https://docs.italia.it/italia/developers-italia/lg-acquisizione-e-riuso-software-per-pa-docs
- publiccode.yml schema — https://docs.italia.it/italia/developers-italia/publiccodeyml/it/master/schema.it.html
- Pubblicazione su Developers Italia — https://developers.italia.it/it/riuso/pubblicazione.html
- Bootstrap Italia — https://italia.github.io/bootstrap-italia/
