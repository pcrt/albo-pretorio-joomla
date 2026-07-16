# Calcolo dei termini di pubblicazione — come funziona e perché è affidabile

**Deliverable S2-T4.** Documenta come il componente calcola la **decorrenza**, la **durata** e la **scadenza** della pubblicazione all'albo, con quale **fuso orario**, e cosa vede il cittadino. Il documento è ora scrivibile in modo veritiero perché il fuso è stato unificato in UTC e validato (riscontri **M9** e **M21** del collaudo).

> Perché conta. Dall'ultimo giorno di pubblicazione decorrono i **termini di impugnazione**: un errore sulle date inciderebbe sul diritto di difesa del cittadino. Le date qui non sono un dettaglio tecnico.

---

## 1. Le tre grandezze temporali di un atto

Quando il responsabile **pubblica** un atto (da bozza), il sistema fissa tre istanti (in `AttoModel::publishAtto()`):

| Grandezza | Significato | Come si calcola |
|-----------|-------------|-----------------|
| **`publish_up`** | **Decorrenza**: data certa di inizio pubblicazione | l'istante esatto del clic su "Pubblica" |
| **`buffer_expires_at`** | Fine della finestra tecnica di sicurezza | `publish_up` + *durata buffer* (minuti, default 60) |
| **`publish_down`** | **Scadenza**: ultimo giorno di pubblicazione | `publish_up` + *durata di legge* (giorni) |

La **durata di legge** dipende dalla tipologia dell'atto (es. generico 15 gg, bando 30 gg): si configura in *Opzioni → durata per tipologia*, con un default globale di riserva. Vedi `AlboonlineHelper::getGiorniPubblicazione()`.

---

## 2. La decorrenza: `publish_up` è la "data certa"

`publish_up` è l'istante del clic su "Pubblica" e non è più modificabile a mano. È la **data certa** da cui tutto discende: è il valore che compare nel referto ("Pubblicato dal") e nel registro cronologico, e la base su cui si conta la durata.

---

## 3. La durata: giorni **consecutivi**, contati nel fuso dell'ente

La scadenza minima è:

```
publish_down (minima) = publish_up + N giorni
```

dove `N` è la durata della tipologia. I giorni sono **consecutivi** (di calendario, festivi inclusi): l'albo pretorio pubblica per un numero di giorni pieni, non di giorni lavorativi.

Dettaglio tecnico che garantisce la correttezza a cavallo dell'ora legale: la somma dei giorni è fatta **nel fuso orario dell'ente** e solo dopo convertita in UTC per il salvataggio (`AttoModel.php`, ~riga 780):

```php
$siteTz         = new \DateTimeZone(Factory::getApplication()->get('offset') ?: 'UTC');
$scadenzaMinima = (clone $now)->setTimezone($siteTz)->modify('+' . $publishDays . ' days');
```

Così "+15 giorni" mantiene la **stessa ora** anche se nel mezzo scatta o finisce l'ora legale: se pubblichi alle 10:00, scadrai alle 10:00, non alle 09:00 o 11:00. (Sommare i giorni in UTC avrebbe spostato l'ora di scadenza di 60 minuti attraverso il cambio DST.)

### La durata è un MINIMO, non un default

Il funzionario può indicare a mano una `publish_down` **più lunga** del termine (pubblicazione prolungata), ma **non più corta**: il sistema rifiuta una scadenza anteriore al minimo di legge (riscontro **M2**, errore `COM_ALBOONLINE_ERROR_DURATA_MINIMA`). La durata per tipologia è quindi un **pavimento di legge**, non un valore predefinito aggirabile verso il basso. Anche la **proroga** rispetta questa regola: può solo estendere, mai accorciare.

---

## 4. Il fuso orario: UTC nel database, ora locale in visualizzazione

Tutte le date del componente sono memorizzate in **UTC** nel database (convenzione di Joomla) e convertite al fuso dell'ente **solo in visualizzazione**. Lo scheduler che blinda e archivia confronta anch'esso in UTC.

Questa uniformità **non era garantita prima** del collaudo: coesistevano tre "orologi" diversi (l'ora locale di MySQL via `NOW()`, l'ora locale di PHP via `date()`, e l'UTC di Joomla), e il form salvava l'ora locale spacciandola per UTC. Il difetto era **mascherato** finché il fuso del sito coincideva con UTC, ma si sarebbe **armato al primo ente reale** su `Europe/Rome`: date di referto sfasate di 1–2 ore e possibili incoerenze cronologiche nell'audit log. È stato corretto alla radice (riscontri **M9** e **M21**), con migrazione dei dati esistenti, e **validato anche sotto fuso reale** `Europe/Rome`.

**Conseguenza pratica per il funzionario:** imposta correttamente il **fuso del sito** in *Sistema → Configurazione globale → Server → Fuso orario* su `Europe/Rome`. Le date immesse e mostrate saranno nell'ora italiana; nel database restano in UTC, ma questo è trasparente all'utente.

---

## 5. La finestra di sicurezza (buffer) e la visibilità pubblica

Tra la pubblicazione e la **blindatura** c'è una finestra tecnica (default 60 minuti, configurabile):

```
publish_up ──[ buffer, atto "In attesa" ]──> blindatura ──[ pubblicazione visibile ]──> publish_down ──> archiviazione
```

- **Durante il buffer** l'atto è in stato *In attesa*: **non è ancora visibile al cittadino** (il frontend mostra solo gli atti in stato *Pubblicato*) e il responsabile può ancora correggerlo o annullarlo.
- Allo **scadere del buffer**, lo scheduler **blinda** l'atto: diventa *Pubblicato* e visibile, gli allegati vengono sigillati con impronta SHA-256, e da lì non è più modificabile.
- Alla **scadenza** (`publish_down`) lo scheduler **archivia** l'atto (defissione): esce dall'elenco pubblico ed **emette il referto** di pubblicazione con le date effettive.

**Punto di attenzione da dichiarare all'ente.** La *decorrenza* dei termini è `publish_up` (la data certa), ma la *visibilità pubblica* comincia alla blindatura, cioè fino a ~buffer dopo. Con il default di 60 minuti la differenza è trascurabile rispetto a una durata di 15–45 giorni. Si raccomanda comunque di **tenere il buffer breve** (pochi minuti/decine di minuti): serve solo a intercettare errori subito dopo la pubblicazione, non a ritardare la messa online.

---

## 6. Cosa vede il cittadino, cosa attesta il referto

- **In elenco pubblico**: gli atti in stato *Pubblicato* (dopo la blindatura, fino alla scadenza), con protocollo e data di inizio.
- **Nel dettaglio dell'atto**: il periodo completo `publish_up` → `publish_down`, così il cittadino conosce i termini entro cui agire.
- **Il referto** (interno, non pubblicato) attesta il periodo **effettivo** `publish_up` → `publish_down` a defissione avvenuta, quando le date sono definitive. Vedi `docs/ricerca-normativa/07-referto-pubblicazione.md`.

---

## 7. Riferimenti nel codice

- `src/administrator/components/com_alboonline/src/Model/AttoModel.php` — `publishAtto()` (decorrenza, buffer, durata, minimo di legge), `extendPublishDown()` (proroga: solo estensione).
- `src/administrator/components/com_alboonline/src/Helper/AlboonlineHelper.php` — `getGiorniPubblicazione()` (durata per tipologia).
- `src/plugins/task/alboonline/src/Extension/AlboonlineTask.php` — `lockExpiredBuffer()` (blindatura a fine buffer), `archiveExpired()` (defissione a scadenza, in UTC).
- I difetti sulle date trovati in collaudo sono corretti e coperti dalla suite: `scripts/collaudo-backend.php` verifica che i presidi sui termini siano davvero divieti.
