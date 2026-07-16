# Contribuire ad Albo Pretorio Online

Grazie per l'interesse. Questo è un componente Joomla per la **pubblicità legale** delle Pubbliche
Amministrazioni italiane: molte delle sue regole non sono scelte di comodo, ma **presìdi giuridici**
(un atto pubblicato non si modifica, i termini decorrono da una data certa, un atto senza documento è
vuoto…). Prima di cambiare un comportamento, vale la pena capire *perché* è così — la risposta è
quasi sempre in [`docs/compliance/`](docs/compliance/) (un documento per adempimento, con la norma e
il punto del codice che la presidia), in [`docs/ricerca-normativa/`](docs/ricerca-normativa/) o nei
commenti del codice, che spiegano il perché e non il cosa.

## Ambiente di sviluppo

Il progetto si sviluppa dentro un'installazione Joomla gestita con **[DDEV](https://ddev.com)**.

- **Joomla 5.1+ e Joomla 6** — un **solo** codice deve funzionare su entrambe (vedi sotto)
- **PHP 8.3+**
- **MariaDB 10.11** / MySQL
- **Composer 2** (per le dipendenze del componente, es. mPDF)

Comandi utili (dalla radice del progetto):

```bash
ddev start                 # avvia l'ambiente
ddev mysql db              # accesso al database
ddev exec php ...          # esegue PHP dentro il container
```

## Com'è organizzato il repository

```
src/          il PRODOTTO: tutto e solo ciò che finisce nel pacchetto installabile
bin/          costruzione del pacchetto e installazione sugli ambienti di prova
docs/         conformità, ricerca normativa, note per chi sviluppa
scripts/      dati di prova e suite di collaudo (dev-only, non si spediscono)
updateserver/ il file che dice a Joomla che esiste una versione nuova
```

La regola è una sola: **dentro `src/` c'è il prodotto, fuori c'è il progetto.** Se ti chiedi se un
file «c'entra col componente», guarda dov'è — non serve chiederlo a qualcuno.

Dentro `src/` l'albero rispecchia quello di Joomla (`administrator/components/com_alboonline/`,
`components/com_alboonline/`, `media/com_alboonline/`, `plugins/`), così il pacchetto è una copia
del sorgente e non un rimontaggio. Prima non era così, e da quel rimontaggio si perdevano i file.

Due cose che sembrano sbagliate e non lo sono:

- **`LICENSE.txt` è doppio** (radice e dentro `src/`). Non è una svista: quello nel componente
  viaggia dentro lo zip perché la GPL vuole che chi riceve il software riceva anche le condizioni;
  quello in radice è per chi guarda il repository.
- **`composer.json` sta fuori da `src/`** ed è giusto: dice quale mPDF ridistribuiamo, serve a noi
  e a chi verifica la licenza, non all'ente che installa. `vendor/` invece finisce dentro `src/`
  perché va spedito — lo scarica il build, non è versionato.

## Costruire il pacchetto

```bash
bash bin/build.sh          # → dist/com_alboonline-<versione>.zip
```

**Serve Composer**: mPDF non è nel repository, lo scarica il build da `composer.lock`. Chi
*installa* l'albo non ne ha bisogno — riceve tutto dentro lo zip.

Lo script **legge la versione dal manifest** (`src/alboonline.xml`, unica fonte di verità), esegue il
lint, copia `src/`, e prima di chiudere lo zip passa dal **guardiano**
(`bin/verifica-pacchetto.php`), che controlla tre cose:

| | |
|---|---|
| **[A]** | c'è nel sorgente ma non nel pacchetto → qualcuno l'ha dimenticato |
| **[B]** | il manifest lo promette ma non c'è → il build è sbagliato |
| **[C]** | c'è nel pacchetto ma nessun manifest lo installa → **Joomla lo ignora in silenzio** |

Il **[C]** è quello che serve di più, ed è il meno intuitivo: fino alla 1.0.0 il file che disegna
l'esito della verifica della firma viaggiava nello zip ma il manifest non lo dichiarava, quindi su
ogni installazione vera quel riquadro **non compariva** — senza un errore, senza un log.

Se il guardiano si lamenta di un file che *non deve* essere spedito, non zittirlo: aggiungilo a
`bin/non-spedito.txt` **scrivendo il motivo**. Senza motivo, «non spedito» e «dimenticato»
diventano la stessa cosa, e il guardiano smette di servire.

## Provare il pacchetto vero

```bash
bash bin/dev-deploy.sh entrambi          # costruisce e INSTALLA su J5 e J6
bash scripts/collaudo-installazione.sh ~/albo-j5   # aggiornamento, disinstallazione, install pulita
```

**Il collaudo si fa sul pacchetto installato, non sull'albero di file.** È la regola che manca da
questo progetto fin dall'inizio ed è il motivo per cui difetti veri sono arrivati a una release
pubblica con tutti i test verdi: gli ambienti erano popolati copiando i file a mano, quindi il
manifest non lo esercitava nessuno. `bin/dev-deploy.sh` esiste per questo — **niente symlink**, o si
torna al punto di partenza.

## Test

Le suite non sono test di unità: **esercitano il componente come lo esercita un funzionario** e
verificano che i divieti siano davvero divieti.

```bash
# 1. Dati di prova (SEMPRE in quest'ordine: demo poi categorie)
ddev exec php scripts/seed-demo.php
ddev exec php scripts/seed-categorie.php

# 2. Suite HTTP (frontend/pubblico) — l'URL è parametrizzabile
bash scripts/collaudo-auto.sh

# 3. Suite backend (presìdi del modello) — CONSUMA i dati: ri-seed prima di ogni giro
ddev exec php scripts/collaudo-backend.php
```

Una modifica si considera pronta quando **entrambe le suite sono verdi su Joomla 5 e su Joomla 6**.

## Standard di codice

- **Licenza:** ogni file sorgente porta in testa l'header **SPDX** `GPL-2.0-or-later` e l'intestazione
  di copyright. Nessun file senza.
- **Namespace:** `Pcrt\Component\Alboonline\…` (Administrator / Site). Non rinominarlo.
- **Stile:** convenzioni di codice Joomla (tab per l'indentazione, PSR-4 per l'autoload).
- **Filesystem:** usare il framework `Joomla\Filesystem\File`/`Folder`, **non** `Joomla\CMS\Filesystem\…`
  (rimosso in Joomla 6). Vedi decisione D85.
- **Etichette:** mai inventare diciture nei messaggi all'utente. Se un pulsante o un avviso rimanda a
  una voce di Joomla, deve combaciare **esattamente** con quella che l'utente vede a schermo.
- **Versioni:** una versione = un file di update SQL in `sql/updates/mysql/<versione>.sql`, **anche se
  lo schema non cambia** (fa avanzare `#__schemas` ed evita falsi «Duplicate column» in aggiornamento —
  decisione D78).

## Due Joomla, un codice

Il componente deve funzionare **con lo stesso identico codice** su Joomla 5 e Joomla 6:

```
~/albo-pretorio-joomla   questo repo — la fonte, il codice vive solo qui
~/albo-j5                Joomla 5, usa-e-getta    https://albo-j5.ddev.site
~/albo-j6                Joomla 6, usa-e-getta    https://albo-j6.ddev.site
```

I due ambienti **non sono due sorgenti**: sono destinazioni. Ci arriva tutto con
`bin/dev-deploy.sh entrambi`, che installa il pacchetto vero e rispecchia gli strumenti di
collaudo. **Non copiare file a mano fra le cartelle**: è così che in passato la guida è finita
aggiornata su uno solo dei due, e che un ambiente è rimasto indietro di una versione mentre
l'altro era già stato collaudato. Se un ambiente si sporca, si butta e si rifà.

Una copia di `scripts/` **deve** stare dentro l'ambiente — `ddev exec php scripts/seed-demo.php`
gira nel container, che vede solo la cartella del progetto — ma è **derivata**: la rispecchia
`dev-deploy.sh` a ogni giro, in una direzione sola. Restano fuori dal rispecchiamento
`scripts/firma-test/` (certificati di prova, contengono una **chiave privata**: non si committa) e
`scripts/a11y/node_modules/`: vivono solo in locale, apposta.

## Le lingue

Le traduzioni stanno **dentro l'estensione** a cui appartengono, non nelle cartelle di lingua di
Joomla:

```
src/administrator/components/com_alboonline/language/{it-IT,en-GB}/
src/components/com_alboonline/language/{it-IT,en-GB}/
src/plugins/{task,content}/alboonline/language/{it-IT,en-GB}/
```

Non è una preferenza di stile. Joomla installa i file dichiarati in `<languages>` nelle cartelle del
core **solo se quella lingua è già presente sul sito**: altrimenti li scarta in silenzio e non li
recupera mai più. Siccome Joomla si installa in inglese per impostazione predefinita e l'italiano si
aggiunge dopo, chi seguiva quell'ordine si ritrovava l'albo **in inglese per sempre**, senza un
motivo visibile. Tenendole qui dentro, Joomla ci ripiega e l'ordine di installazione non conta più.

**L'`en-GB` non è una lingua che curiamo.** È la **rete di sicurezza**: Joomla carica sempre prima la
lingua predefinita (en-GB) e poi ci sovrascrive l'italiano, quindi senza en-GB una chiave dimenticata
in italiano diventerebbe un codice grezzo (`COM_ALBOONLINE_…`) e un Joomla in inglese sarebbe
illeggibile. Ma il prodotto è per la PA italiana: **non serve tradurre in inglese le stringhe nuove**,
e se l'en-GB resta indietro va bene così. La lingua da curare è l'italiano.

## Invarianti da non rompere

Alcune cose il sistema **non deve** permettere, per legge. Se un tuo cambiamento le tocca, quasi
certamente è un errore:

- un atto **pubblicato/blindato** non si modifica e non si cancella (si **rettifica**);
- la **durata** di pubblicazione è un minimo di legge: si può allungare, mai accorciare;
- non si pubblica un atto **senza documento** (salvo l'opzione dedicata per gli avvisi di solo testo);
- l'atto diventa pubblico e viene ritirato **dai due lavori pianificati**, non a mano;
- i **referti già emessi** sono congelati: append-only, non si riscrivono.

## Proposte di modifica (Pull Request)

1. Lavora su un **branch** dedicato (non sul principale).
2. Assicurati che **le suite siano verdi** su J5 e J6, e che `bash bin/build.sh` completi.
3. Descrivi **cosa** cambia e **perché** — se tocca un presidio giuridico, cita la fonte (norma,
   collaudo, decisione).
4. Un solo tema per PR: più facile da rivedere.

## Segnalare un problema

- **Bug o richieste:** apri una Issue, con passi per riprodurre, versione del componente e di Joomla/PHP.
- **Vulnerabilità di sicurezza:** **non** in una Issue pubblica → vedi [SECURITY.md](SECURITY.md).

## Licenza dei contributi

Contribuendo, accetti che il tuo contributo sia distribuito sotto **GPL-2.0-or-later**, la licenza del
progetto (vedi [LICENSE.txt](LICENSE.txt)). Nota: il **pacchetto** completo, includendo mPDF
(`GPL-2.0-only`), è ridistribuibile alle condizioni della **GPL v2** — vedi
[THIRD-PARTY-NOTICES.md](src/administrator/components/com_alboonline/THIRD-PARTY-NOTICES.md).
