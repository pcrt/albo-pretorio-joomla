# Ricerca normativa — Referto / relata di pubblicazione all'Albo Pretorio Online

**Data:** 2026-07-09 · **Metodo:** deep-research con verifica avversariale (25 claim, 3 voti ciascuna → 17 confermate, 8 refutate).
**Motivo:** la voce "referto di pubblicazione" nei nostri report precedenti era **derivata senza citazione puntuale**. Questa ricerca colma la lacuna prima di implementare M18.

> ⚠️ **Esito di fondo:** il referto **non ha una base in norma primaria**. La sua disciplina vive nei **regolamenti** degli enti e nella **prassi**. Ne discendono conseguenze dirette sul progetto (vedi §Implementazione).

---

## Le sei domande

### 1. Esiste una norma primaria che impone il referto? — **NO** *(alta confidenza, 3-0)*
Né l'**art. 32 L. 69/2009** né gli **artt. 124 / 134 TUEL** nominano il referto o la relata. I **pareri DAIT** (13180, 14263, 14100) riconoscono la *prassi* dell'apposizione del "referto di pubblicazione" sugli atti, ma **non ne disciplinano** forma, contenuto o sottoscrizione.
- L'obbligo/disciplina risiedono in **regolamenti comunali/provinciali** e prassi (erede dell'attestazione del **messo comunale** del regime cartaceo; il Messo non è più menzionato dal TUEL).
- Matrice amministrativa comune: **Direttiva n. 8/2009** del Ministro PA — *non* norma primaria.

### 2. Chi lo redige e firma? — **"Responsabile della pubblicazione"**, figura di prassi *(alta, 3-0)*
Tipicamente il **Segretario generale** o un **dirigente/funzionario delegato**, o personale del Servizio Protocollo/Notifiche (ex ufficio messi). **Non definita da norma primaria** — solo dai regolamenti (es. Provincia di Pesaro-Urbino art. 4: *"Il responsabile della pubblicazione… è il Segretario generale o dirigente o funzionario dallo stesso delegato"*, con *"la sottoscrizione e il rilascio della relata"*).

### 3. Quando si emette? — **alla defissione (fine), su richiesta** *(media, 2-1)*
Prassi prevalente: emissione **al termine** della pubblicazione, rilasciata **su richiesta** al responsabile del procedimento. **Divergenza tra enti:** alcuni usano **due documenti** (attestazione di avvio + referto di avvenuta pubblicazione alla defissione). Nessuno standard nazionale.

### 4. Cosa deve contenere? — set definito dai regolamenti *(alta, 3-0)*
Elementi "obbligatori e immodificabili" del **registro informatico con valore legale**: **numero cronologico/repertorio · tipologia · oggetto · data iniziale · data finale · estremi del documento**. Deriva dal quadro del protocollo informatico (DPR 445/2000 artt. 53-57). *Esito/opposizioni non risulta elemento uniforme.* (La tesi "solo 3 elementi" è stata **refutata 0-3**: il set è più ampio.)

### 5. Va pubblicato online? — **NO, di regola** *(media, 2-1)*
Il referto **non è affisso** come documento a sé: resta **agli atti** (registro informatico con valore legale) ed è **trasmesso/esibito su richiesta** (o in giudizio). Gli *estremi* di pubblicazione possono essere visibili, ma il **referto standalone non si pubblica**. *(Caveat: per le deliberazioni la relata del Segretario è conservata allegata all'atto; "non pubblicato" vale per il documento a sé, non è assoluto.)*

### 6. Va firmato digitalmente? — **NO, non è l'unica via** *(alta, 3-0)* — ma ⚠️ **l'hash da solo NON basta** *(refutata 0-3)*
**Linee Guida AgID sul documento informatico** (§2.1.1 lett. a, in forza dell'art. 71 CAD): integrità e immodificabilità di un documento formato ex novo si garantiscono con **una o più** operazioni **alternative**:
1. firma elettronica **qualificata / digitale / sigillo elettronico qualificato / FEA**;
2. **memorizzazione su sistema di gestione documentale sicuro** (criteri §3.9);
3. trasferimento via **PEC / recapito certificato**;
4. **versamento in conservazione**.

→ La firma digitale **non è obbligatoria**. **MA** — e questo demolisce una nostra assunzione — **l'impronta hash da sola NON fonda l'immodificabilità probatoria**: la tesi *"referto sigillato con hash = valido anche senza firma"* è stata **refutata 0-3**. L'hash serve all'identificazione/integrità **tecnica**, ma deve essere **inquadrato in una** delle operazioni sopra (tipicamente conservazione o DMS conforme §3.9). Rilevano gli artt. 20-23-ter e 40-44 CAD sul valore probatorio.

---

## Zone grigie (dichiarate)
- **Nessuna figura di "responsabile della pubblicazione" in norma primaria** — solo regolamenti.
- **Uno o due documenti** (avvio + defissione): diverge tra enti (voti 2-1).
- **Variabilità terminologica**: *referto di pubblicazione · referto di affissione · relata/referta di avvenuta pubblicazione · attestazione di avvenuta pubblicazione · notifica di pubblicazione*.
- **PA non-locali** (università, ASL, **conservatori**, ordini): **fuori dal TUEL**, disciplina autonoma e poco documentata. Le università usano spesso "notifica di pubblicazione". → **rilevante**: il nostro componente nasce per un Conservatorio e mira a tutte le PA.
- **Giurisprudenza** sul valore probatorio del referto online non firmato: non emersa.

## Qualità delle fonti
Le uniche **primarie** (TUEL, L. 69/2009, pareri DAIT, Linee Guida AgID) **tacciono** sul referto o lo citano come prassi. La disciplina concreta viene da **regolamenti** (Pesaro-Urbino, Sassari, Arese — primarie di livello locale), un **vademecum commerciale 2011** e il **wiki Maggioli** (secondari). Quindi: *"modello regolamentare tipico"*, **non** obbligo nazionale uniforme.

---

## Implicazioni per l'implementazione (M18 / M10 / M11)

1. **Il referto è uno strumento a supporto di una prassi regolamentare, non l'attuazione di un obbligo di legge.** → dev'essere **configurabile** (attivabile/disattivabile; terminologia scelta dall'ente; uno o due documenti), **non** hardcoded come adempimento statutario. Cruciale per le PA non-locali (conservatori) prive di aggancio TUEL.
2. **Emissione alla defissione e non-pubblicazione al cittadino: CONFERMATE** dalla prassi. Il disegno concordato regge su questi due punti.
3. **⚠️ L'hash da solo non conferisce immodificabilità probatoria.** Non dobbiamo *dichiarare* che "congelato + hash = documento immodificabile a norma". L'hash resta utile per **integrità/identificazione tecnica** e per il **confronto col firmato**, ma la immodificabilità probatoria arriva da **una** delle operazioni AgID: la **firma digitale** del responsabile (via che il componente abilita: genera → l'ente firma → ricarica) **oppure** la conservazione/DMS conforme (**fuori scope**). → la firma non è un "di più" opzionale qualsiasi: è **la** via probatoria che il componente può offrire; resta comunque facoltativa perché l'ente può assolvere con carta+firma o conservazione.
4. **Contenuto**: repertorio, tipologia, oggetto, ente, **data iniziale e finale** — già previsti. Aggiungere il **numero cronologico** del registro (che il nostro "registro cronologico" già fornisce).
5. **Firmatario = "responsabile della pubblicazione"** → coerente con il permesso esistente `alboonline.publish`.
6. **Terminologia neutra/configurabile**: default "referto di pubblicazione", ma prevedere che l'ente possa rinominarlo (relata / attestazione / notifica).

## Fonti principali
- TUEL D.Lgs. 267/2000 artt. 124, 134 — https://www.bosettiegatti.eu/info/norme/statali/2000_0267.htm
- Pareri DAIT — https://dait.interno.gov.it/pareri/13180 · /14263 · /14100
- Linee Guida AgID sul documento informatico (2024-05) — https://www.agid.gov.it/sites/agid/files/2024-05/linee_guida_sul_documento_informatico.pdf
- Art. 20 CAD (AgID) — https://www.agid.gov.it/it/codice-dellamministrazione-digitale/art-20-validita-ed-efficacia-probatoria-documenti-informatici
- Regolamento Provincia Pesaro-Urbino — https://www.provincia.pu.it/fileadmin/grpmnt/1053/Regolamento_gestione_albo_pretorio_on_line.pdf
- Regolamento Comune di Sassari — https://www.comune.sassari.it/.galleries/doc-documenti/albo.pdf
- Linee guida Comune di Arese — https://storico.comune.arese.mi.it/allegati/Doc_23_98%5ELinee%20guida%20albo%20online.pdf
- Vademecum Università di Bari (PA non-locale) — https://www.uniba.it/…/albo-pretorio-online-vademecum-pubblicazione
