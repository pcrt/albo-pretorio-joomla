# Changelog

Tutte le modifiche rilevanti a questo progetto sono annotate qui.

Il formato si ispira a [Keep a Changelog](https://keepachangelog.com/it/1.1.0/) e il progetto adotta il
[Versionamento Semantico](https://semver.org/lang/it/).

## [Non rilasciato]

### Corretto
- Elenco atti (vista `Atti`): il meta `robots` non veniva impostato, quindi l'elenco
  ignorava sia l'opzione della voce di menu sia la Configurazione globale del sito
  (es. un sito dimostrativo in `noindex` restava indicizzabile sull'albo). Ora usa
  la voce di menu e, in mancanza, la configurazione globale. La pagina del singolo
  atto non cambia: ha già la sua regola (`noindex` per archiviati e predefinito).
- `script.php`: le versioni minime controllate all'installazione erano PHP 8.1.0 e
  Joomla 5.0.0, mentre i requisiti dichiarati (README, manifest, update server) sono
  PHP 8.3 e Joomla 5.1: ora coincidono. Emerso installando la 1.2.1 su Joomla 6.2 /
  PHP 8.4 il 7/11/2026 (installazione e pagine pubbliche senza errori).

### Progetto
- Aggiunto `publiccode.yml` in radice, validato con `publiccode-parser-go` 5.4.3,
  per l'inserimento nel catalogo del software open source per la PA di Developers
  Italia. Non entra nel pacchetto. Da aggiornare a ogni release (`softwareVersion`,
  `releaseDate`).

## [1.2.1] — 2026-07-20

Release di sicurezza. Sostituisce la 1.2.0: chi l'ha installata dovrebbe aggiornare. Nessun
cambiamento funzionale per l'uso normale — solo la chiusura di falle e due rinforzi.

### Sicurezza

- **Gli allegati di un atto «In Attesa» erano scaricabili prima della pubblicazione.** Un atto
  schedulato ma non ancora affisso è invisibile in tutto il frontend, eppure il download ne serviva
  comunque gli allegati a chiunque ne indovinasse l'id numerico — anche a un visitatore anonimo,
  aggirando la finestra di pubblicazione. Il gettone nell'URL è anti-CSRF, non un controllo
  d'accesso: chiunque ne ottiene uno valido da una qualsiasi pagina dell'albo. Ora il download
  applica lo stesso confine del resto del frontend — un atto «In Attesa» è raggiungibile solo in
  anteprima dal backend (`core.edit`), mai dal pubblico. La regola vive in un unico punto
  (`AlboonlineHelper::downloadDenial`) ed è coperta da un test che la esercita su tutti gli stati.

- **Il riordino degli atti (`saveOrderAjax`) girava senza gettone né permesso.** Era l'unica
  scrittura del backend priva di verifica anti-CSRF e di controllo dei permessi: una pagina esterna
  poteva far riordinare gli atti a un operatore loggato, e chiunque raggiungesse il backend poteva
  farlo a prescindere dai permessi. Aggiunti gettone e `core.edit.state`, come sul metodo omologo
  del core di Joomla.

- **Nome e username non messi in sicurezza in due campi del form.** I campi «creato da» e
  «modificato da» stampavano nome e username senza escape: un nome contenente HTML poteva eseguire
  codice nel browser di chi apriva la scheda dell'atto. Ora passano per `htmlspecialchars`.

## [1.2.0] — 2026-07-16

**Sostituisce la 1.0.0, che non andrebbe più installata**: i documenti firmati `.p7m` non si
caricavano e la verifica della firma era muta.

> *La 1.1.0 non è mai stata pubblicata: le sue correzioni sono qui dentro.*

Il filo comune di quasi tutti questi difetti è lo stesso: **fallivano in silenzio**. Nessun errore a
video, nessuna riga nei log — semplicemente non succedeva niente. In più erano invisibili in
sviluppo, perché i test esercitavano l'albero di lavoro e non il pacchetto spedito né l'interfaccia
reale. È per questo che sono arrivati fino a una release pubblica.

### Aggiunto

- **Pulsante «Carica» nella scheda allegati.** Aggiunge subito i file scelti, senza dover risalire al
  Salva della barra. Fa solo quello: non salva gli altri campi dell'atto e non tocca gli allegati già
  presenti.

- **Pulsante «Elimina» su ogni allegato.** Prima bisognava spuntare una casella e poi salvare. Ora si
  elimina la singola riga, con conferma. Su un atto blindato resta impossibile: il divieto è nel
  codice, non solo nel pulsante nascosto.

### Corretto

- **I documenti firmati `.p7m` non si caricavano affatto.** Il controllo sul tipo di file pretendeva
  un MIME inesistente (`application/p7m`): una busta CAdES viene riconosciuta come
  `application/octet-stream`, quindi **ogni** `.p7m` veniva rifiutato con «tipo di file non
  consentito», su qualunque server e con qualunque versione di PHP. Il referto firmato invece si
  caricava, perché lì lo stesso controllo era scritto correttamente: la regola esisteva in **due
  copie**, una giusta e una sbagliata. Ora è una sola.

- **Un file scartato veniva saltato in silenzio.** L'atto risultava «salvato» ma senza il suo
  allegato, e niente spiegava perché: è così che il rifiuto dei `.p7m` è rimasto invisibile. Ora ogni
  file non caricato compare a video con il motivo.

- **«Aggiungi un altro file» aggiungeva due righe per clic.** La stessa logica era duplicata in due
  script caricati entrambi. Rimasta una sola copia.

- **Dopo un'operazione sugli allegati si tornava sempre alla scheda «Dettagli».** Ora la scheda su cui
  si sta lavorando viene ricordata.

- **L'esito della verifica della firma non compariva affatto.** Il pacchetto non conteneva il file
  che disegna quel riquadro (`layouts/firma/esito.php`): non era dichiarato nel manifest, quindi non
  veniva installato. Joomla in questi casi non segnala nulla — restituisce una stringa vuota — così
  il riquadro spariva **in silenzio**, senza errori. Chi in 1.0.0 ha caricato un allegato firmato e
  non ha visto alcun esito: **non era il suo documento, era il nostro pacchetto**. La verifica della
  firma funzionava già; semplicemente non aveva modo di mostrarsi.

- **La guida inclusa nel pacchetto aveva un collegamento rotto**: il referto di esempio scaricabile
  (`esempi/referto-di-esempio.pdf`) non veniva spedito, quindi dalla guida offline dava «pagina non
  trovata». La stessa guida online funzionava, il che rendeva il difetto ancora più difficile da
  notare.

- **I due lavori pianificati restavano con le etichette in inglese** nello stesso scenario descritto
  qui sotto (italiano aggiunto a Joomla dopo l'installazione). Stessa causa, stessa correzione.

- **Il componente restava in inglese se l'italiano veniva aggiunto a Joomla dopo l'installazione.**
  Joomla installa i file di lingua di un'estensione nelle cartelle del core **solo se quella lingua è
  già presente**, altrimenti li scarta senza dirlo e non li recupera più: chi installava l'albo su un
  Joomla in inglese — il caso predefinito — e aggiungeva l'italiano in seguito si ritrovava
  l'interfaccia in inglese per sempre, senza un motivo visibile. Ora le traduzioni stanno **dentro il
  componente**, dove Joomla le cerca quando nel core non trova nulla: l'albo parla italiano in
  qualunque ordine lo si installi, e non si perdono più nemmeno se si disinstalla il pacchetto lingua
  italiano di Joomla.

- **Alcune etichette apparivano come codici** (`COM_ALBOONLINE_ALLEGATI_CARICA` al posto di
  «Carica») **aggiornando dalla 1.0.0.** La versione vecchia aveva lasciato i propri file di lingua
  nelle cartelle di Joomla, e quelli coprivano i nuovi: l'albo restava con le parole della versione
  precedente e ogni etichetta aggiunta dopo mostrava il proprio nome tecnico. L'aggiornamento ora si
  porta via i file che ha lasciato in giro. **Non serve fare nulla a mano.**

- **Su un Joomla in inglese la schermata Opzioni mostrava alcuni codici** al posto delle etichette:
  mancavano nove traduzioni inglesi.

## [1.0.0] — 2026-07-15

Prima release pubblica. Componente Joomla (5.1+ e 6) per la **pubblicità legale** delle Pubbliche
Amministrazioni italiane: gestisce il ciclo di vita di un atto all'albo pretorio — dalla redazione alla
defissione — con date certe, documenti sigillati e tutela della privacy.

### Funzionalità

- **Ciclo di vita completo dell'atto**: bozza → pubblicazione (numero di repertorio + data certa) →
  finestra di sicurezza (buffer) → blindatura automatica → defissione → archiviazione, governato da
  **due lavori pianificati** creati all'installazione.
- **Immutabilità dell'atto pubblicato**: dopo la blindatura, contenuto, date e allegati non si toccano;
  le correzioni si fanno per **rettifica** (nuovo atto collegato).
- **Referto di pubblicazione**: documento **congelato** e sigillato (impronta SHA-256) che attesta
  quando e per quanto un atto è stato pubblicato; ricaricabile in versione firmata (PAdES/CAdES).
- **Allegati**: PDF e P7M, con **verifica della firma** (integrità tecnica) e del **formato** (PDF/A,
  scansioni senza testo).
- **Privacy**: diritto all'oblio (art. 17 GDPR) con rimozione immediata o differita alla scadenza,
  avviso euristico sui **dati particolari** (art. 9 GDPR), deindicizzazione dagli archivi, e opzione di
  cancellazione fisica dei documenti. Ogni azione è **tracciata** nell'audit log (accountability).
- **Frontend accessibile**: elenco e archivio degli atti, **albero delle categorie** come widget ARIA,
  progettato secondo le WCAG 2.1 AA nel markup generato.
- **Configurabile e de-brandizzato**: ente emanante, tipologie di atto e durate, buffer, referto,
  permessi — tutto dalle Opzioni; nessun default brandizzato.
- **Guida completa** per i funzionari, integrata nel pacchetto.
- Compatibilità **Joomla 5.1+ e 6**, **PHP 8.3+**. Licenza **GPL-2.0-or-later**.
