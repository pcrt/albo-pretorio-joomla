# Oscuramento (omissis) e checklist di pubblicazione

**Deliverable S4-T3.**

## Provenienza (obbligo vs strumento)

- 📜 **Norma diretta:** l'obbligo di pubblicare **solo i dati necessari** è il principio di **minimizzazione** (art. 5.1.c GDPR); il divieto sui dati particolari è l'**art. 9 GDPR**; il Garante (Linee Guida 2014) chiede l'**oscuramento** dei dati non indispensabili.
- 📋 **Prassi:** l'**omissis** (mascheramento dei dati nel documento) è la tecnica con cui l'ente assolve la minimizzazione.
- 🛠️ **Scelta di progetto:** l'oscuramento *materiale* del PDF lo fa il **funzionario prima di caricarlo** — è più sicuro e affidabile che un mascheramento automatico (che può lasciare il testo estraibile sotto il rettangolo nero). Il componente fornisce quello che il piano chiede — *"almeno warning e checklist in fase di pubblicazione"* — cioè una **checklist di controllo**, come promemoria non bloccante.

---

## Come funziona

Sugli atti **in bozza** (quelli che stanno per essere pubblicati), in cima alla scheda compare un pannello **«Prima di pubblicare, verifica:»** con i controlli consigliati:

1. **Minimizzazione** — solo i dati necessari, nessun dato personale eccedente (art. 5.1.c GDPR).
2. **Omissis** dei dati particolari — salute, giudiziari, minori, disagio economico (art. 9 GDPR).
3. L'**allegato** è la versione corretta e definitiva.
4. **Ente** emanante e (se prevista) **segnatura di protocollo** corretti.
5. **Durata** di pubblicazione adeguata alla tipologia.

Le voci sono **spuntabili** come aiuto per il funzionario, ma le spunte **non vengono salvate** e **non bloccano** la pubblicazione: la responsabilità resta del responsabile. Si combina con l'**avviso automatico sulle categorie particolari** (S4-T2), che segnala i termini sospetti nel testo.

**Configurabile:** *Opzioni → Checklist prima della pubblicazione* (attiva di default).

---

## Perché non l'oscuramento automatico

Un mascheramento automatico del PDF è **rischioso**: un rettangolo nero sopra il testo lascia spesso il testo **ancora estraibile** (copiabile o leggibile dal livello testo), dando una **falsa sicurezza**. L'oscuramento a norma richiede la rimozione effettiva del contenuto, che si fa in modo affidabile con strumenti dedicati **prima** del caricamento. Il componente quindi non promette ciò che non può garantire: aiuta a ricordare, non finge di oscurare.

---

## Riferimenti nel codice

- `src/administrator/components/com_alboonline/src/View/Atto/HtmlView.php` — `showChecklist` (solo bozze, se abilitata).
- `src/administrator/components/com_alboonline/tmpl/atto/edit.php` — il pannello checklist.
- `src/administrator/components/com_alboonline/config.xml` — opzione `checklist_pubblicazione`.
- `docs/compliance/06-categorie-particolari.md` — l'avviso automatico complementare (S4-T2).
