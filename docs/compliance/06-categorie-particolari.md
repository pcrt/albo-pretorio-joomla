# Categorie particolari di dati — l'avviso in redazione

**Deliverable S4-T2.**

## Provenienza (obbligo vs strumento)

- 📜 **L'obbligo viene dalla norma (diretta):** all'albo è vietato diffondere **categorie particolari** di dati.
  - **art. 9 GDPR** — divieto di trattare dati che rivelano salute, origine, convinzioni, dati genetici/biometrici, vita sessuale;
  - **art. 2-octies Codice Privacy** — dati giudiziari (condanne, reati);
  - **art. 26 c. 4 D.Lgs. 33/2013** — cautela rafforzata per dati su **disagio economico-sociale** e **minori**;
  - **Garante, Linee Guida 2014** (doc. web 3134436) — dati sanitari e giudiziari espressamente sottratti a pubblicazione/indicizzazione.
- 🛠️ **Lo strumento lo mettiamo noi (scelta di progetto):** un **avviso automatico** in fase di redazione. Non è imposto da alcuna norma: è una **misura tecnica di supporto** che aiuta il funzionario a non dimenticarsi dell'obbligo. Per questo è **non bloccante** e la responsabilità della valutazione resta dell'ente.

---

## Come funziona

Mentre si redige l'atto, il sistema esamina il testo (titolo, oggetto, descrizione) cercando **termini-spia** raggruppati in quattro categorie:

| Categoria | Esempi di termini rilevati |
|---|---|
| **Salute / disabilità** | salute, malattia, patologia, diagnosi, invalidità, disabile, handicap, legge 104, terapia, ricovero, psichiatrico… |
| **Dati giudiziari** | casellario, carichi pendenti, condanna, penale, reato, indagato, imputato, misura cautelare… |
| **Minori** | minorenne, tutela del minore, affidamento, adozione… |
| **Disagio economico-sociale** | ISEE, indigenza, sfratto, morosità, buoni spesa, disagio economico, sussidio… |

Se trova qualcosa, mostra in cima alla scheda un **avviso** con le categorie e i termini individuati, e invita a **oscurare (omissis)** o a pubblicare in forma **anonimizzata**.

**È volutamente approssimato.** Può dare falsi positivi (una parola usata in un contesto innocuo) e falsi negativi (un dato particolare espresso senza le parole-spia). Proprio perché è un aiuto e non un giudizio, l'esito è un semplice avviso.

**Configurabile:** *Opzioni → Avviso su categorie particolari di dati* (attivo di default). Si può disattivare.

---

## Cosa NON fa (limiti dichiarati)

- Non **oscura** automaticamente i dati (l'oscuramento vero e proprio degli allegati è tema di S4-T3): individua e avvisa, la redazione del testo/omissis resta all'operatore.
- Non **blocca** la pubblicazione (per scelta; un blocco rigido è un'opzione futura possibile).
- Non sostituisce la valutazione del **titolare del trattamento** (l'ente).

---

## Riferimenti nel codice

- `src/administrator/components/com_alboonline/src/Helper/CategorieParticolari.php` — lo scanner (liste di termini per categoria).
- `src/administrator/components/com_alboonline/src/View/Atto/HtmlView.php` — esecuzione della scansione in redazione.
- `src/administrator/components/com_alboonline/tmpl/atto/edit.php` — l'avviso in cima alla scheda.
- `src/administrator/components/com_alboonline/config.xml` — opzione `categorie_particolari_alert`.
- `docs/ricerca-normativa/03-privacy-gdpr.md` — categorie particolari, art. 9 GDPR, Garante 2014.
