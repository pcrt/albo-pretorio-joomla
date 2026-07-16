# Nomina a responsabile del trattamento (art. 28 GDPR) — fac-simile

> ## ⚠️ Leggi prima di usare questo modello
>
> **1) Serve davvero nel tuo caso?** L'atto ex art. 28 va stipulato **solo se un soggetto esterno tratta i dati personali per conto dell'ente** (chi ospita il sito, o chi fa manutenzione/assistenza con accesso ai dati). Distinzione cruciale per un software **open source**:
> - se l'ente **installa e gestisce tutto in autonomia**, sui propri server e col proprio personale, **non c'è un responsabile esterno**: l'ente è titolare e basta. Usare un software open source **non** crea di per sé un rapporto ex art. 28 con chi lo ha scritto (che non tocca i dati dell'ente);
> - se invece l'ente **affida a terzi** hosting, manutenzione o assistenza **con accesso ai dati**, allora **quel terzo** va nominato responsabile con l'atto qui sotto.
>
> In breve: si nomina **chi mette le mani sui dati**, non genericamente "il software".
>
> **2) Non è consulenza legale.** È un fac-simile da adattare al caso concreto e da far verificare al proprio DPO / ufficio legale prima dell'uso. Le parti tra `[…]` vanno compilate.

**Deliverable S4-T6.**

## 1. I ruoli, in breve

- **Titolare del trattamento** = **l'ente** (Comune, ASL, Università, Conservatorio…). Decide finalità e mezzi, ed è il responsabile ultimo dei dati pubblicati all'albo.
- **Responsabile del trattamento** = **chi tratta i dati personali per conto dell'ente**. Nel mondo di questo software può essere:
  - chi **ospita** il sito (hosting/data center) se i dati stanno sui suoi server;
  - chi fa **manutenzione o assistenza** con accesso ai dati (es. la società che gestisce il Joomla dell'ente).

## 2. Contenuti obbligatori (art. 28.3)

L'atto deve definire: **oggetto, durata, natura e finalità** del trattamento; il **tipo di dati** e le **categorie di interessati**; **obblighi e diritti** del titolare; e i seguenti obblighi del responsabile (lett. a–h):

a) tratta i dati **solo su istruzione documentata** del titolare;
b) garantisce che le persone autorizzate al trattamento siano tenute alla **riservatezza**;
c) adotta le **misure di sicurezza** adeguate (art. 32);
d) ricorre a **sub-responsabili** solo con autorizzazione e alle stesse condizioni;
e) **assiste** il titolare nel dar seguito alle richieste degli interessati (accesso, cancellazione/oblio, rettifica…);
f) assiste il titolare su **sicurezza, violazioni dei dati (data breach) e valutazioni d'impatto** (artt. 32–36);
g) al termine del servizio **cancella o restituisce** i dati, salvo obblighi di conservazione;
h) mette a disposizione le **informazioni per dimostrare la conformità** e consente **audit**.

---

## 3. Fac-simile dell'atto

> ATTO DI NOMINA A RESPONSABILE DEL TRATTAMENTO
> ai sensi dell'art. 28 del Regolamento (UE) 2016/679 (GDPR)
>
> **Tra**
> `[Denominazione dell'ente]`, con sede in `[…]`, C.F./P.IVA `[…]`, in persona di `[…]`, in qualità di **Titolare del trattamento** (di seguito "Titolare");
>
> **e**
> `[Denominazione del fornitore/gestore]`, con sede in `[…]`, C.F./P.IVA `[…]`, in persona di `[…]`, in qualità di **Responsabile del trattamento** (di seguito "Responsabile");
>
> **Premesso che** il Titolare gestisce l'Albo Pretorio Online e affida al Responsabile `[hosting / manutenzione / assistenza]` che comporta il trattamento di dati personali per conto del Titolare;
>
> **si conviene quanto segue.**
>
> **Art. 1 — Oggetto, natura, finalità e durata.** Il Responsabile tratta i dati personali contenuti negli atti pubblicati all'albo e nei relativi allegati e log, per le sole finalità di `[hosting/manutenzione/assistenza]`, per la durata del contratto `[…]`.
>
> **Art. 2 — Tipi di dati e categorie di interessati.** Dati anagrafici e di contatto, e — ove presenti negli atti — eventuali categorie particolari (da minimizzare/oscurare a cura del Titolare). Interessati: cittadini, dipendenti, controparti degli atti.
>
> **Art. 3 — Obblighi del Responsabile.** Il Responsabile si obbliga a quanto previsto dall'art. 28.3, lett. a)–h): `[riportare le lettere a–h del §2]`.
>
> **Art. 4 — Sub-responsabili.** `[Elenco o divieto; condizioni ex art. 28.2/28.4]`.
>
> **Art. 5 — Misure di sicurezza.** `[Riferimento alle misure ex art. 32: cifratura in transito, controllo accessi, backup, log, ecc.]`.
>
> **Art. 6 — Assistenza e violazioni.** Il Responsabile assiste il Titolare per le richieste degli interessati e notifica **senza ingiustificato ritardo** ogni violazione dei dati di cui venga a conoscenza.
>
> **Art. 7 — Cessazione.** Al termine, il Responsabile **cancella o restituisce** i dati e le copie, salvo obblighi di legge.
>
> **Art. 8 — Audit.** Il Responsabile mette a disposizione le informazioni necessarie a dimostrare la conformità e consente verifiche/audit.
>
> Luogo e data `[…]` — Firme del Titolare e del Responsabile `[…]`

---

## 4. Cosa fa il software per aiutare la conformità

Anche senza nominare nessuno, il componente fornisce già alcune delle **misure tecniche** che un responsabile dovrebbe garantire (e che il titolare deve poter dimostrare):

- **audit trail** delle azioni (pubblicazione, blindatura, defissione, oblio attivato/revocato…) — art. 5.2 accountability;
- **impronta SHA-256** e verifica d'integrità dei documenti — art. 32;
- **deindicizzazione** e **defissione automatica** — limitazione della conservazione (art. 5.1.e);
- **workflow oblio tracciato** e **avviso categorie particolari** — assistenza alle richieste degli interessati (artt. 15–22).

## Riferimenti

- Reg. (UE) 2016/679 (GDPR), **art. 28**; artt. 32–36; art. 5.
- `docs/ricerca-normativa/03-privacy-gdpr.md` — ruoli titolare/responsabile, misure.
- `docs/compliance/` — i deliverable che implementano le misure tecniche citate.
