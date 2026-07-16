# Architettura — Albo Pretorio Online

Documentazione tecnica per chi mette le mani nel codice. Per l'ambiente di sviluppo, la build e i test
vedi [CONTRIBUTING.md](../../CONTRIBUTING.md); per il *perché* delle regole,
[`docs/compliance/`](../compliance/) e la
[guida](https://pcrt.github.io/albo-pretorio-joomla/guida/) (che viaggia anche dentro il pacchetto,
in `src/media/com_alboonline/guida/`).

È un **componente Joomla 5.1+/6** (MVC) con **due plugin** e una libreria PDF vendorizzata (mPDF).
Il cuore non è la UI: è una **macchina a stati con presìdi giuridici** — un atto non può essere reso
pubblico senza data certa, non può essere modificato dopo la blindatura, non può restare online oltre
i termini. Molto del codice esiste per *impedire* queste cose.

## Dove sta cosa

Il codice è disposto nel layout d'installazione di Joomla (non in una `src/` unica): la build
(`bin/build.sh`) lo ricompone nel pacchetto.

```
administrator/components/com_alboonline/   BACKEND (funzionario)
  ├── src/Controller|Model|Table|View|Helper|Field|Rule|Service/
  ├── forms/            definizioni XML dei form (atto, filtri…)
  ├── tmpl/             template delle viste backend
  ├── sql/              install.mysql.utf8.sql + updates/mysql/<versione>.sql
  ├── vendor/           mPDF + font (SPEDITO nel pacchetto, non ignorare)
  ├── alboonline.xml    manifest (versione = fonte di verità)
  └── config.xml        le Opzioni del componente
components/com_alboonline/                  FRONTEND (cittadino)
  └── src/Controller|Model|View|Service|Helper|Dispatcher/
api/components/com_alboonline/              endpoint API (minimale)
media/com_alboonline/                       css/, js/, guida/ (spediti); allegati/, referti/ (runtime)
plugins/task/alboonline/                    i DUE lavori pianificati (scheduler)
plugins/content/alboonline/                 presìdi su categorie + aiuti in linea
administrator/language/ , components/.../language/   traduzioni it-IT / en-GB
docs/ , scripts/ , bin/                     documentazione, collaudo, build
```

## Le classi che contano

### Backend — `administrator/…/src`

| Classe | Ruolo |
|--------|-------|
| **`Model/AttoModel`** | Il **cuore**. Salvataggio, `publishAtto()` (protocollo + data certa + buffer), `extendPublishDown()` (proroga/riattivazione), oblio (`attivaOblio`/`revokeOblio`), rettifica, upload allegati. Applica gli sbarramenti: immutabilità del blindato, allegato obbligatorio, durata minima. |
| **`Model/AttiModel`** | La lista backend (filtri, ricerca). |
| **`Model/RefertoModel`** | Ricarica del referto **firmato** (`attachSigned`) e download. |
| **`Table/AttoTable`** | Presidio a livello tabella: cancellare un atto ne elimina i file (`File::delete`). |
| **`Table/AllegatoTable`** | Cancellazione di un allegato + relativo file. |
| **`Helper/AlboonlineHelper`** | Costanti di stato, etichette/badge, `isLocked()`, `logAction()` (audit), moduli home. |
| **`Helper/RefertoGenerator`** | Genera il **referto** (HTML → PDF mPDF), lo congela e lo sigilla (SHA-256). |
| **`Helper/FirmaVerifier`** | Verifica firma **PAdES** (dentro il PDF) e **CAdES** (`.p7m`); integrità tecnica, non qualificata. |
| **`Helper/FormatoPdf`** | Rileva PDF/A e le «scansioni senza testo» (richiede `pdftotext`). |
| **`Helper/CategorieParticolari`** | Euristica sui dati particolari (art. 9 GDPR) nei campi dell'atto. |
| **`Helper/ProtocolloHelper`** | Numerazione progressiva del repertorio (tabella `contatori`). |
| **`Helper/MetadatiAgid`** | Metadati del documento informatico (Allegato 5 AgID). |
| `Controller/*`, `View/*`, `Field/*`, `Rule/*` | Controller (Atto, Atti, Referto, Display), viste (elenco, scheda, referto, registro), campi custom, regole di validazione (rettifica motivata, tipi atto unici). |

### Frontend — `components/…/src`

| Classe | Ruolo |
|--------|-------|
| **`Model/AttiModel`** | Elenco pubblico: filtra su **`a.state IN (1,2)`** e **`a.access`** (mai `c.access`), con sotto-categorie (nested set). |
| **`Model/AttoModel`** | Scheda pubblica del singolo atto (carica i legami di rettifica solo se consultabili). |
| **`Model/CategoriesModel`** | Albero delle categorie col conteggio **coerente con la vista** (Albo vs Archivio; l'oblio non è mai contato). |
| **`Controller/DownloadController`** | Download **sicuro** degli allegati (verifica accesso e integrità dell'impronta). |
| **`Service/Router`** | URL SEF. |

### I due plugin

- **`src/plugins/task/alboonline`** — le due routine dello **scheduler**:
  - `lock` (ogni 5 min): a fine buffer porta l'atto a **Pubblicato**, `state=1`, e calcola l'**impronta SHA-256** di ogni allegato (blindatura).
  - `archive` (ogni 15 min): a scadenza porta l'atto a **Archiviato**, emette il **referto**, e — se `diritto_oblio` + opzione `delete_pdf_on_oblio` — **cancella fisicamente** i PDF.
- **`src/plugins/content/alboonline`** — presìdi su `com_categories`:
  - veto su `onContentBeforeDelete` (non si cancella una categoria che contiene atti);
  - iniezione degli **aiuti in linea** nel form della categoria + pulsante in toolbar.

### Frontend JS — `src/media/com_alboonline/js`

- **`explorer.js`** — l'**albero delle categorie** come widget ARIA `tree` (frecce, roving tabindex, focus).
- **`admin.js`** — piccole interazioni del backend.

## Il modello dei dati (schema)

Cinque tabelle (`#__` = prefisso). Schema completo in `sql/install.mysql.utf8.sql`.

### `#__alboonline_atti` — l'atto
Colonne chiave (oltre ai campi Joomla `id/asset_id/access/language/params/created…`):

| Campo | Significato |
|-------|-------------|
| `stato_atto` | **Stato del workflow**: 0 Bozza · 1 In Attesa · 2 Pubblicato · 3 Archiviato (−2 = cestino, vive su `state`). |
| `state` | Stato Joomla: 0 non pubblico/oblio · 1 pubblico · 2 archiviato-consultabile · −2 cestino. **La visibilità pubblica dipende da questo, non da `stato_atto`.** |
| `numero_protocollo` / `progressivo_protocollo` | Numero di **repertorio** dell'albo (assegnato alla pubblicazione). |
| `segnatura_protocollo` | Protocollo **informatico dell'ente** (dato distinto). |
| `publish_up` / `publish_down` | Inizio (data certa) e fine della pubblicazione. |
| `buffer_expires_at` / `locked_at` | Fine del buffer di sicurezza / momento della blindatura. |
| `rettifica_di` / `rettificato_da` | Legami di rettifica (bidirezionali). `note_rettifica` = motivazione. |
| `diritto_oblio` | Flag oblio (art. 17 GDPR). |
| `tipo_atto` / `ente_emanante` / `responsabile` | Classificazione e soggetti (finiscono nel referto). |

### `#__alboonline_allegati` — i documenti
`atto_id`, `titolo`, `filename` (nome su disco, `<sha256>.<ext>`), `original_name`, `hash` (SHA-256, valorizzato alla blindatura), `size`, `mime`, `ordering`. **Svuotare `filename`** = documento cancellato (oblio con `delete_pdf_on_oblio`).

### `#__alboonline_referti` — i documenti probatori
Snapshot **congelato** dell'atto al momento della defissione: repertorio, oggetto, ente, responsabile,
date **effettive**, `pdf_filename` + **`pdf_hash`** (SHA-256, sigillo), `stato` (`emesso`/`firmato`),
ed eventuale versione firmata (`firmato_filename`/`hash`/`mime`). **Append-only**: non si riscrive.

### `#__alboonline_log` — audit
Ogni transizione: `atto_id`, `azione`, `stato_prima`/`stato_dopo`, `user_id`/`user_name`, `ip_address`,
`note`, `created`. Scritto **sempre** via `AlboonlineHelper::logAction()` (accountability, art. 5.2 GDPR).

### `#__alboonline_contatori` — numerazione
`ultimo_numero` per la progressione del repertorio.

## Il ciclo di vita (macchina a stati)

```
Bozza ──publishAtto()──▶ In Attesa ──[scheduler lock]──▶ Pubblicato ──[scheduler archive]──▶ Archiviato
  ▲          │(annulla, entro il buffer)                    │  │                                  │
  └──────────┘                                   (proroga)◀─┘  └▶ Rettifica (nuovo atto collegato)  ├▶ consultabile
                                                                                                    └▶ oblio → invisibile
```

- **Tu** (funzionario) fai due passi: preparare la bozza e premere *Pubblica*. Gli altri li fa lo **scheduler**.
- `publishAtto()` assegna repertorio + data certa e mette **In Attesa** (`stato_atto=1, state=0`): non ancora pubblico, c'è il **buffer** per annullare.
- Il task `lock` **blinda** (`stato_atto=2, state=1`) e calcola gli hash. Il task `archive` **defigge** (`stato_atto=3`) ed emette il referto.
- La riattivazione di un archiviato (`extendPublishDown` con data futura) apre un **nuovo periodo** (`publish_up=adesso`) → produrrà un secondo referto.

## Build e test

Vedi [CONTRIBUTING.md](../../CONTRIBUTING.md): `bash bin/build.sh` (pacchetto), `scripts/collaudo-auto.sh`
+ `scripts/collaudo-backend.php` (le due suite), `scripts/seed-demo.php` + `scripts/seed-categorie.php`
(dati di prova, in quest'ordine). Regola: verde su **Joomla 5 e 6**.

## Invarianti da non rompere

Riassunti in [CONTRIBUTING.md](../../CONTRIBUTING.md#invarianti-da-non-rompere): immutabilità del blindato,
durata come minimo di legge, allegato obbligatorio, transizioni via scheduler, referti append-only.
Se un tuo cambiamento tocca uno di questi, quasi sempre è un errore — o una decisione da mettere nel registro.
