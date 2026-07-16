# Metadati del documento informatico — mappatura AgID (Allegato 5)

**Deliverable S3-T3.** Mappa i **metadati minimi** richiesti dall'Allegato 5 delle Linee Guida AgID sul documento informatico sui campi effettivamente gestiti dal componente, dichiarando cosa è **presente**, cosa è **derivabile** e cosa è **fuori scope**. I metadati presenti sono **esposti** nella pagina pubblica dell'atto (sezione "Metadati del documento") e assemblati da un'unica fonte (`src/administrator/components/com_alboonline/src/Helper/MetadatiAgid.php`), riusabile per un futuro export.

> Gran parte di questi metadati **non è stata creata per S3-T3**: esisteva già, costruita nei task precedenti (repertorio, oggetto, ente, date, impronta, formato, firma, segnatura). S3-T3 li **raccoglie, mappa ed espone**.

---

## 1. Mappatura

| Metadato AgID (All. 5) | Campo / funzione del componente | Stato |
|---|---|---|
| **Identificativo** univoco e persistente | `numero_protocollo` (repertorio d'albo) | ✅ presente |
| **Data** del documento | `data_atto` (+ `publish_up` come data certa) | ✅ presente |
| **Oggetto** | `oggetto` | ✅ presente |
| **Soggetti** (autore / emittente / assegnatario) | `ente_emanante` (emittente), `responsabile` | ✅ presente (destinatario non pertinente per atti pubblicati) |
| **Tipologia documentale** | `tipo_atto` (determina, delibera, bando…) | ✅ presente |
| **Impronta hash + algoritmo** | `hash` dell'allegato + **SHA-256** | ✅ presente (per file, S3-T5) |
| **Formato** | `mime` + rilevamento **PDF/A** | ✅ presente (per file, S3-T2) |
| **Riferimento alla firma** | verifica firma PAdES/CAdES + firmatario | ✅ presente dove il file è firmato (S3-T1) |
| **Segnatura / dati di protocollo** | `segnatura_protocollo` + `segnatura_data` | ✅ presente (S3-T4) |
| **Riservatezza / regime** | regime "albo pretorio — pubblicità legale (temporanea)"; oblio gestito | ✅ presente |
| **Modalità di formazione** | derivabile dal formato (documento informatico vs copia per immagine) | 🟡 derivabile, non asserito con certezza |
| **Versione** | versionamento nativo Joomla (`save_history`) | 🟡 presente internamente, non esposto come metadato |
| **Classificazione / fascicolazione** | — | ⚪ fuori scope (protocollo informatico: solo ganci) |

---

## 2. Note oneste sulle voci non piene

- **Modalità di formazione.** L'Allegato 5 distingue tra *documento informatico* (nato digitale), *copia per immagine di documento analogico* (scansione), ecc. Il componente **rileva** le scansioni senza testo (S3-T2) ma non può stabilire con certezza la modalità di formazione di ogni file → non la asseriamo come metadato per non dichiarare il falso. È un'informazione **derivabile**, non affermata.
- **Versione.** Joomla mantiene lo storico delle versioni dell'atto (`save_history`), utile in redazione; non è esposto come metadato pubblico perché per un atto pubblicato la versione rilevante è quella blindata.
- **Classificazione / fascicolazione.** Appartiene al **protocollo informatico** dell'ente, che è **fuori scope** per decisione di progetto (il componente offre solo il campo di segnatura e i ganci, nessun connettore). Vedi decisione #3 del brief.

---

## 3. Dove sono esposti

- **Pagina pubblica dell'atto:** sezione a scomparsa "Metadati del documento (AgID)" con le voci a livello di atto; l'**impronta** e il **formato** di ciascun file sono mostrati accanto al singolo allegato.
- **Fonte unica:** `MetadatiAgid::build($atto)` restituisce le voci valorizzate (etichetta + valore), pronta anche per un futuro **export** machine-readable (JSON/XML) senza duplicare la logica.

---

## 4. Riferimenti

- `src/administrator/components/com_alboonline/src/Helper/MetadatiAgid.php` — assemblaggio dei metadati.
- `src/components/com_alboonline/tmpl/atto/default.php` — esposizione nella pagina dell'atto.
- `docs/ricerca-normativa/02-documento-informatico.md` — requisiti All. 5.
- `docs/compliance/03-integrita-documenti.md` (impronta), `01-calcolo-termini.md` (date), e i task S3-T1/T2/T4 per i singoli metadati.
