# Ricerca normativa — Pubblicità legale / Albo Pretorio Online

**Ambito:** normativa primaria della pubblicità legale tramite albo pretorio online.

## Quadro normativo primario

**Legge 18 giugno 2009 n. 69, art. 32** ("Eliminazione degli sprechi relativi al mantenimento di documenti in forma cartacea"):
- **Comma 1**: gli obblighi di pubblicazione di atti aventi effetto di pubblicità legale si intendono assolti con la pubblicazione nei siti informatici degli enti obbligati.
- **Comma 5**: **dal 1° gennaio 2011** le pubblicazioni in forma cartacea non hanno più effetto di pubblicità legale. Data cardine: da qui vale solo l'albo online.
- La pubblicazione deve rispettare i requisiti di **accessibilità** (L. 4/2004).
- Fonte: https://www.normattiva.it/uri-res/N2Ls?urn:nir:stato:legge:2009-06-18;69~art32

**TUEL — D.Lgs. 267/2000, art. 124**: l'art. 32 ha sostituito l'affissione all'albo fisico con la pubblicazione sul sito per **15 giorni consecutivi**, salvo diversa disposizione. Fonte: https://dait.interno.gov.it/pareri/14263

**CAD — D.Lgs. 82/2005**: infrastruttura giuridica del documento informatico (integrità, immodificabilità, data certa, conservazione: **artt. 20, 21, 22, 23-ter, 40, 43-44**). ⚠️ Nell'attuale versione del CAD l'**art. 26 è abrogato** e l'**art. 32 disciplina la firma elettronica qualificata** (non l'albo): il valore di pubblicità legale online discende dalla **L. 69/2009**, mentre il CAD garantisce autenticità/integrità del file. Fonte: https://www.bosettiegatti.eu/info/norme/statali/2005_0082.htm

## Distinzione Albo Pretorio ↔ Amministrazione Trasparente

| | Albo pretorio | Amm. Trasparente (D.Lgs 33/2013) |
|---|---|---|
| Finalità | Efficacia/opponibilità dell'atto | Trasparenza/accountability |
| Durata | 15–45 gg (temporanea) | ~5 anni (art. 8 D.Lgs. 33) |
| Indicizzazione | **Vietata** (diritto all'oblio) | Ammessa |
| Ritiro post-scadenza | Sì (defissione) | No (permanenza quinquennale) |

Alle pubblicazioni all'albo **non** si applica l'arco quinquennale dell'art. 8 D.Lgs. 33/2013.

## Obblighi che il software DEVE garantire

- Pubblicazione integrale per il **periodo di legge** (default 15 gg consecutivi; 30/45 per atti urbanistici; termini speciali per gare, ecc.).
- **Referto/relata di pubblicazione**: attestazione con data certa di **inizio** e **fine**, soggetto, oggetto, ufficio.
- **Numero progressivo di repertorio/registro albo** univoco e sequenziale.
- **Pubblicazione continuativa** senza interruzioni; conteggio giorni consecutivi.
- **Immodificabilità** del contenuto durante la pubblicazione.
- **Defissione automatica** alla scadenza + trasferimento in registro/archivio **non indicizzato**.
- **Accessibilità** e **minimizzazione** dei dati personali.

## Requisiti tecnico-funzionali derivati

Numerazione progressiva non riutilizzabile · date certe inizio/fine non alterabili · integrità/autenticità (PDF/A + firma) · ordine cronologico e ricercabilità · blocco modifiche in pubblicazione (rettifica = nuovo atto) · audit trail · esclusione indicizzazione + area riservata post-scadenza · oscuramento dati sensibili.

## Conseguenze della mancata/errata pubblicazione

- **Inefficacia dell'atto** e mancata **opponibilità ai terzi**; per gli atti a efficacia costitutiva la pubblicazione è condizione di validità.
- **Decorrenza termini di impugnazione** dall'**ultimo giorno di pubblicazione** → errori sulle date incidono sulla tutela giurisdizionale.
- Responsabilità del responsabile della pubblicazione.

## Zone grigie / rischi

- Termini non uniformi per tipologia → durate parametrizzabili, non un default rigido.
- Tensione privacy/trasparenza online → defissione puntuale e de-indicizzazione.
- Conteggio giorni (consecutivi, festivi, decorrenza) fonte di contenzioso → dev'essere certo e documentato.
- Non confondere albo e Amm. Trasparente (regimi di durata/indicizzazione opposti).
- Riferimenti CAD: agganciare gli articoli **vigenti** (20, 40, 43-44), non la numerazione storica.

## Fonti
- L. 69/2009 art. 32 — https://www.normattiva.it/uri-res/N2Ls?urn:nir:stato:legge:2009-06-18;69~art32
- CAD D.Lgs. 82/2005 — https://www.bosettiegatti.eu/info/norme/statali/2005_0082.htm
- Min. Interno DAIT parere 14263 — https://dait.interno.gov.it/pareri/14263
- Efficacia pubblicità legale online — https://www.mediappalti.it/lefficacia-della-pubblicita-legale-on-line/
- Termini, oblio, accesso — https://www.ictsecuritymagazine.com/articoli/i-termini-di-pubblicazione-il-diritto-alloblio-e-i-costi-dellaccesso/
- Albo pretorio e trasparenza — https://digitalepopolare.it/lalbo-pretorio-online-e-la-trasparenza-nellera-delle-gov-tra-pubblicita-legale-e-privacy/
