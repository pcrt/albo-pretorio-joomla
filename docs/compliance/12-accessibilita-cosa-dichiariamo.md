# Accessibilità: cosa possiamo dichiarare, e cosa no

> Documento di **onestà delle dichiarazioni**. Serve a un ente che deve compilare la propria
> dichiarazione di accessibilità: gli dice **cosa abbiamo verificato noi** e **cosa deve
> verificare lui**. Aggiornato al **2026-07-13**, dopo la prova da tastiera e la scansione
> automatica.

**Provenienza:** 🔎 riscontro M36 + prova da tastiera eseguita dall'utente (M39, M40).
Stesso principio applicato alle diciture sulla verifica della firma digitale: non dire mai più
di quello che si è verificato.

---

## La formula corretta

> **Il componente è conforme alle WCAG 2.1 AA nel markup che genera**, verificato con prova da
> tastiera e scansione automatica (axe-core) su Joomla 5 e Joomla 6.
> **La conformità della pagina pubblicata va verificata dall'ente sul proprio tema**:
> contrasto dei colori, visibilità del focus, comportamento a zoom 200% e reflow a 320px
> dipendono dal tema, non dal componente.

## Cosa abbiamo verificato noi (e come)

**Navigazione da sola tastiera** — eseguita a mano, senza mouse:

- l'**albero delle categorie** risponde a `↓` `↑` `Home` `End` `→` `←` `Invio`/`Spazio` come
  prescrive il pattern WAI-ARIA APG (*Navigation Treeview*);
- **si esce dall'albero con Tab**: nessuna trappola del focus;
- dopo aver scelto una categoria **il focus torna sulla voce scelta**, non in cima al documento
  (WCAG 2.4.3 — prima costringeva a ripremere Tab molte volte);
- l'**elenco atti** ha **una sola fermata di Tab per riga**, e **tre link di salto** (vai
  all'elenco · salta l'elenco · torna ai filtri e alle categorie) — WCAG 2.4.1, livello A;
- **senza JavaScript** tutte le categorie restano raggiungibili con Tab (progressive
  enhancement: il roving tabindex è un'aggiunta, non un prerequisito).

**Scansione automatica (axe-core)** su lista atti, dettaglio pubblicato e dettaglio archiviato,
su **entrambe le piattaforme**: **0 violazioni critical/serious**. L'unica segnalazione
(`region`, moderate) riguarda il **modulo di login di Cassiopeia**, non il componente.
Ripetibile: `scripts/a11y/` .

**Difetti reali trovati e corretti** durante questa verifica — nessuno era visibile a occhio:
mancava `aria-expanded` sui nodi con figli; mancavano `aria-posinset`/`aria-setsize`; il
conteggio entrava nel nome del link («Determine 5, link» — cinque cosa?); l'indice del focus non
si risincronizzava; «Tutte le categorie» non era contata nel totale, e le voci annunciavano
«di 3» dove sono **4**.

## Cosa NON abbiamo verificato (e va detto, non nascosto)

- **Contrasto dei colori** (WCAG 1.4.3) — dipende dal **tema**. Il componente è sviluppato su
  **Cassiopeia**, il tema predefinito di Joomla.
- **Zoom 200%** (1.4.4) e **reflow a 320px** (1.4.10) — dipendono dal rendering e dal tema.
- **Prova con screen reader reale** (NVDA/VoiceOver) — il markup è conforme al pattern e la
  scansione automatica è pulita, ma una prova con una persona che usa quotidianamente uno
  screen reader **non è surrogabile**. Chi adotta il componente e ha modo di farla, la faccia.

## Cosa deve fare l'ente

1. Verificare sul **proprio tema** contrasto, focus, zoom 200% e reflow 320px.
2. Compilare la **dichiarazione di accessibilità** su form.agid.gov.it — è un adempimento
   dell'ente, non del componente.
3. Se il tema modifica il markup del componente (override di template), **rifare la verifica**:
   le garanzie qui descritte valgono per il markup che il componente genera, non per un
   override.

## Una nota su cosa NON abbiamo fatto, e perché

Non è stata implementata la **navigazione a frecce nell'elenco atti** (pattern ARIA `grid`).
È una **scelta**, non una dimenticanza: con uno screen reader in modalità lettura le frecce
sono intercettate **dallo screen reader**, non dalla pagina; e una tabella di risultati si
attraversa con Tab in tutto il web. Il guadagno concreto — dimezzare le fermate di Tab e dare
vie di fuga — è stato ottenuto senza allontanarsi dallo standard.
