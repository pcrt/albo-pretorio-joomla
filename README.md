# Albo Pretorio Online

**Componente Joomla per la pubblicità legale delle Pubbliche Amministrazioni italiane.**
Gestisce il ciclo di vita di un atto all'albo pretorio — dalla redazione al ritiro — con date
certe, documenti sigillati e tutela della privacy.

Compatibile con **Joomla 5.1+ e Joomla 6**, PHP 8.3+. Licenza **GPL-2.0-or-later**.

---

## Il problema che risolve

L'albo pretorio è la bacheca ufficiale di un ente pubblico: gli atti vi restano affissi per un
tempo stabilito dalla legge, poi **devono** essere ritirati. Sembra semplice, e invece è pieno
di trappole che si pagano care:

- se un atto **non viene ritirato** alla scadenza, l'ente viola la normativa privacy — il Garante
  ha già sanzionato amministrazioni per la permanenza indebita di atti online;
- se le **date non sono certe**, i termini di impugnazione del cittadino diventano contestabili;
- se un atto pubblicato **viene modificato**, l'attestazione di pubblicazione non vale nulla;
- se il documento **non c'è**, la pubblicazione è vuota.

Questo componente non si limita a mostrare dei PDF: **impedisce** che queste cose accadano. Assegna
protocollo e data certa, sigilla l'atto una volta pubblicato, lo ritira da solo alla scadenza ed
emette il **referto** — la prova di quando e per quanto è stato all'albo. Il cittadino consulta,
scarica e verifica l'integrità dei documenti; non può modificare nulla.

## 📖 Guida e demo

- **[Guida completa per i funzionari »](https://pcrt.github.io/albo-pretorio-joomla/guida/)** —
  dalla bozza all'oblio, con le schermate, i messaggi esatti e *«le norme dietro le regole»*.
  Viaggia anche **dentro il pacchetto** (pulsante «Guida» nell'albo), utile anche offline.
- **Demo online** — *in arrivo.*

## Cosa NON fa (e non finge di fare)

L'onestà su questo punto è parte del prodotto: un ente che si fida di una funzione inesistente si
espone.

- **Non è un sistema di conservazione a norma** (SInCRO/UNI 11386) né un connettore al **protocollo
  informatico**: ci sono i campi e i ganci, non l'integrazione.
- **Non valida la marca temporale** (RFC 3161): ne rileva la **presenza**, e lo dichiara.
- **Non dichiara mai «firma qualificata valida a norma»**: la verifica interna arriva a «**firma
  tecnicamente integra**» con firmatario e validità del certificato; il resto richiede le Trusted
  List europee.
- **Non oscura i PDF**: l'omissis va fatto **prima** del caricamento (un oscuramento automatico
  darebbe una falsa sicurezza).
- **Non gestisce SPID/CIE.**

## Conformità

- **Pubblicità legale** (L. 69/2009 art. 32, TUEL art. 124) — repertorio univoco, date certe,
  durata per tipologia, immodificabilità durante i termini, registro cronologico, referto.
- **Documento informatico** (Linee Guida AgID) — impronta SHA-256 riverificabile a ogni download,
  verifica delle firme PAdES/CAdES, metadati AgID, segnatura di protocollo.
- **Privacy** (GDPR · Garante) — ritiro automatico, diritto all'oblio tracciato, deindicizzazione
  dai motori di ricerca, avviso sulle categorie particolari, audit log completo.
- **Accessibilità** — conforme alle **WCAG 2.1 AA nel markup generato** (la conformità della *pagina*
  dipende anche dal tema dell'ente).

## Installazione

1. Scarica l'ultima **[release](https://github.com/pcrt/albo-pretorio-joomla/releases)**
   (`com_alboonline-<versione>.zip`).
2. In Joomla: *Sistema → Installa estensioni* → carica lo zip.
3. Configura ente emanante, tipologie e durate, permessi, e crea la **voce di menu** dell'albo.
4. **Attiva i due lavori pianificati** — è il passo che decide se l'albo pubblica davvero — e
   verifica con un atto di prova.

> **Tutti i dettagli, passo per passo e con le insidie, sono nella
> [guida](https://pcrt.github.io/albo-pretorio-joomla/guida/)** (pagine «Requisiti», «Installare»,
> «Configurazione», «I lavori automatici»).

**Requisiti:** Joomla 5.1+ o 6, PHP 8.3+, MySQL/MariaDB, e un modo per far girare le attività
pianificate (cron). `openssl` e `pdftotext` consigliati sul server.

## Per chi sviluppa

```
src/            il PRODOTTO: tutto e solo ciò che finisce nel pacchetto installabile
  alboonline.xml    il manifest — dice a Joomla cosa installare e dove
  administrator/    il componente lato amministrazione (+ layouts, lingue, mPDF)
  components/       il componente lato sito, quello che vede il cittadino
  media/            css, js e la guida (viaggia col prodotto: serve anche offline)
  plugins/          i due plugin: i lavori pianificati e il presidio sulle categorie

bin/            costruzione del pacchetto e installazione sugli ambienti di prova
docs/           conformità normativa, ricerca, architettura del codice
scripts/        dati di prova e suite di collaudo (non si spediscono)
updateserver/   dice a Joomla che esiste una versione nuova
```

**La regola è una: dentro `src/` c'è il prodotto, fuori c'è il progetto.** Se ti chiedi se un file
«c'entra col componente», guarda dov'è.

Tre cose che sembrano sbagliate e non lo sono:

- **`LICENSE.txt` è doppio** (radice e dentro `src/`): quello nel componente viaggia nello zip
  perché la GPL vuole che chi riceve il software riceva anche le condizioni; quello in radice è per
  chi guarda il repository. Un controllo in CI verifica che restino identici.
- **`vendor/` (mPDF) non c'è**: lo scarica il build da `composer.lock`, che è verificabile — una
  cartella copiata a mano non lo è. Chi *installa* l'albo lo riceve dentro lo zip.
- **`docs/ricerca-normativa/`** non è codice ed è tra le cose più importanti qui dentro: è il
  *perché* dei divieti. Chi accorcia una durata «per fare un favore all'utente» fa prendere una
  sanzione a un ente.

Architettura e schema del database: [`docs/dev/architettura.md`](docs/dev/architettura.md).
Come contribuire, costruire e collaudare: [CONTRIBUTING.md](CONTRIBUTING.md).
Approfondimenti normativi in [`docs/compliance/`](docs/compliance/) e
[`docs/ricerca-normativa/`](docs/ricerca-normativa/).

**Integrazione continua** ([GitHub Actions](.github/workflows/ci.yml)): lint PHP su 8.3/8.4,
validazione XML, e — soprattutto — **costruzione del pacchetto** con un guardiano che si accorge se
un file non viene spedito o se il manifest non lo installa. Non è un controllo teorico: fino alla
1.0.0 l'esito della verifica della firma non compariva su nessuna installazione vera, e tutti i test
erano verdi.

## Licenza e autore

Il **codice** è rilasciato sotto **GPL-2.0-or-later** — la stessa di Joomla.

⚠️ Il **pacchetto installabile** va ridistribuito sotto **GPL v2**: include **mPDF** (il generatore
dei PDF del referto, che Joomla non ha), rilasciato come **GPL-2.0-only**. Chi riusa *solo* il
nostro codice, senza mPDF, resta libero di applicare anche la v3. Il pacchetto include anche i font
**DejaVu** (Bitstream Vera): il loro avviso di copyright **non va rimosso**. Dettaglio completo in
[THIRD-PARTY-NOTICES.md](src/administrator/components/com_alboonline/THIRD-PARTY-NOTICES.md).

Sviluppato da **Eddy Prosperi** ([protocollicreativi.it](https://protocollicreativi.it)).
Nato per un ente reale, de-brandizzato e reso adottabile da qualsiasi PA italiana.
