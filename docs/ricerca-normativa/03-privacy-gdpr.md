# Ricerca normativa — Privacy / GDPR nell'albo pretorio

**Ambito:** trattamento dati personali nella pubblicità legale online.

## Fonte cardine

**Garante Privacy — "Linee guida in materia di trattamento di dati personali… per finalità di pubblicità e trasparenza sul web da soggetti pubblici"** — provv. n. 243 del **15 maggio 2014**, doc. web **3134436** (G.U. 134 del 12/6/2014).
https://www.garanteprivacy.it/home/docweb/-/docweb-display/docweb/3134436

Distingue due finalità con regimi diversi:
- **Trasparenza** (D.Lgs 33/2013): pubblicazione tipicamente quinquennale.
- **Pubblicità legale** (albo): durata **tassativa e breve** (per gli enti locali **15 giorni** ex art. 124 TUEL). Scaduto il termine, la permanenza online è priva di base giuridica.

FAQ Garante "Trasparenza online": https://www.garanteprivacy.it/faq/trasparenza-online

## Principi applicabili

- **Conoscibilità temporanea**: consultabile solo per il periodo previsto; poi **rimosso dalla vista pubblica** o privato degli elementi identificativi.
- **Deindicizzazione**: impedire l'indicizzazione dei motori esterni con **`noindex` e `noarchive`** e/o `robots.txt`; dati sensibili/giudiziari **espressamente** sottratti all'indicizzazione. Privilegiare la ricerca interna per evitare la "decontestualizzazione".
- **Minimizzazione** (art. 5.1.c GDPR): pubblicare solo dati adeguati/pertinenti/limitati; **oscuramento/omissis** dei dati eccedenti già in fase di redazione.
- **Categorie particolari** (art. 9 GDPR): divieto di diffondere dati sulla salute; dati giudiziari/sensibili solo se indispensabili e previsti da legge; cautela rafforzata per **minori** e per elementi di disagio economico-sociale (art. 26 c.4 D.Lgs 33/2013).
- **Limitazione conservazione / oblio** (artt. 5.1.e e 17 GDPR): dopo la scadenza rimozione o anonimizzazione.
- **Base giuridica**: **art. 6.1.c GDPR** (obbligo legale) + **art. 2-ter** (necessità di norma) e **art. 2-septies** (misure di garanzia per categorie particolari) del Codice Privacy (D.Lgs 196/2003, mod. D.Lgs 101/2018).

## Checklist privacy che il software DEVE garantire

| Requisito | Riferimento |
|---|---|
| **Durata configurabile** per tipologia (data inizio/fine) | art. 124 TUEL |
| **Rimozione automatica alla scadenza** (job, senza intervento manuale) | par. 7 / 2.b |
| **noindex/noarchive** + `X-Robots-Tag` + `robots.txt`; atto scaduto non reperibile dai motori | par. 8 |
| **Ricerca interna** (no URL decontestualizzati) | par. 2.a P.II |
| **Oscuramento/omissis** dati eccedenti e categorie particolari | par. 2, 9 |
| **Blocco/alert categorie vietate** (salute, giudiziari, minori) | art. 9 GDPR; par. 9.e |
| **Gestione oblio** (istanze cancellazione/rettifica/deindicizzazione, tracciate) | art. 17 GDPR |
| **Audit/log** immutabile (pubblicazione, scadenza, rimozione, accessi, referto) | accountability art. 5.2 |

## Obbligatori vs raccomandati

**Obbligatori**: rispetto della durata legale; rimozione alla scadenza; base giuridica; divieto dati salute; minimizzazione; referto di avvenuta pubblicazione.
**Raccomandati**: `noindex`/`robots.txt` già in vigenza; anonimizzazione al posto della cancellazione quando serve valore probatorio; DPIA per grandi volumi; sezione "archivio" ad accesso non pubblico.

## Zone grigie / rischi

- **Archivio storico**: dopo la scadenza l'atto conserva valore documentale, ma non può restare **liberamente consultabile online**; scelta tra rimozione totale dal pubblico e accesso ristretto interno.
- **Deindicizzazione retroattiva**: rimuovere l'URL non basta se resta in cache dei motori; richiedere rimozione cache + mantenere `noindex`.
- **Permanenza indebita**: oltre i termini è trattamento illecito nonostante l'originaria liceità. Il **titolare (ente)** ne risponde; il fornitore software è tipicamente **responsabile del trattamento (art. 28 GDPR)** e deve garantire le misure tecniche. Il Garante ha sanzionato enti (es. pubblicazione oltre i termini, dati sanitari, IBAN — sanzione da **€10.000**).
  https://www.agendadigitale.eu/sicurezza/privacy/albo-pretorio-online-e-privacy-come-evitare-sanzioni-per-trattamenti-illeciti/
- **Oblio vs pubblicità legale**: durante il termine l'oblio non prevale (art. 17.3.b GDPR); prevale dopo la scadenza.

## Fonti
- Garante LG 15/5/2014 doc-web 3134436 — https://www.garanteprivacy.it/home/docweb/-/docweb-display/docweb/3134436
- FAQ Trasparenza online — https://www.garanteprivacy.it/faq/trasparenza-online
- G.U. 12/6/2014 — https://www.gazzettaufficiale.it/eli/id/2014/06/12/14A04344/sg
- Sanzioni albo pretorio — https://www.agendadigitale.eu/sicurezza/privacy/albo-pretorio-online-e-privacy-come-evitare-sanzioni-per-trattamenti-illeciti/
- FPA, oltre i 15 giorni — https://www.forumpa.it/pa-digitale/vietata-la-pubblicazione-dei-dati-personali-sullalbo-on-line-oltre-i-15-giorni-previsti/
