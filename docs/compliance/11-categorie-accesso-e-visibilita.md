# Categorie: accesso, visibilità e cancellazione

> Cosa governa davvero una categoria dell'Albo, e cosa **non** governa. Da leggere prima di
> usare le categorie come strumento di riservatezza: non lo sono.

**Provenienza:** 🔎 riscontro M28 del collaudo (2026-07-13) — comportamento verificato sul
codice e sul campo, non una supposizione.

---

## 1. Il livello di Accesso di una categoria NON protegge gli atti che contiene

Se si porta una categoria dell'Albo a un livello di accesso riservato (es. *Registrati*),
succede questo:

- la categoria **sparisce** dall'albero e dai filtri del sito, per chi non ha quel livello;
- **tutti i suoi atti restano visibili e apribili** da chiunque, anche in anonimo.

Non è un difetto: il frontend filtra sul livello di accesso **dell'atto**, mai su quello
della categoria. Ed è **giusto che sia così**: un atto pubblicato all'Albo Pretorio deve
essere consultabile **da chiunque, senza autenticazione** — è il senso stesso della
pubblicità legale (art. 32 L. 69/2009). Un atto non può sparire dalla pubblicazione perché
qualcuno ha irrigidito una categoria.

**Il rischio è di percezione, ed è concreto.** Un funzionario può credere di aver «riservato»
una categoria e non averlo fatto per niente — e, credendolo, pubblicarci dentro documenti che
non dovevano essere pubblici.

> **Regola pratica.** Il livello di accesso della categoria governa **solo la navigazione**.
> Se un contenuto non deve essere pubblico, **non va pubblicato all'Albo**: l'Albo è, per
> definizione, il luogo della pubblicità. Per limitare i dati diffusi si interviene **prima**,
> sul documento (omissis, minimizzazione), non dopo, sulla categoria.

## 2. Sospendere una categoria non ritira gli atti

Sospendere (spubblicare) una categoria la fa sparire dalla navigazione, **ma gli atti che
contiene restano pubblicati e consultabili**, e i termini continuano a decorrere.

Anche questo è corretto, e per la stessa ragione: la pubblicità legale non può dipendere dal
flag di una categoria. Per ritirare un atto esiste un solo modo legittimo — la **defissione**
alla scadenza, o il **diritto all'oblio**, che sono tracciati.

## 3. Cancellare una categoria che contiene atti: impedito

Il gestore delle categorie è quello del core di Joomla e non conosce la tabella degli atti:
cancellava senza avvisare, lasciando gli atti **orfani** (classificati in una categoria che
non esiste più). Su un atto blindato il danno era definitivo, perché il campo Categoria è di
sola lettura.

Dal fix del riscontro **M30**, il componente **rifiuta la cancellazione** di una categoria che
contiene atti, spiegando cosa fare (spostarli prima). Se un'installazione aggiornata ha già
atti orfani, la scheda dell'atto mostra un riquadro **«Categoria mancante»** che consente di
riassegnare la categoria — l'unica modifica di categoria ammessa su un atto blindato, e
tracciata nell'audit log. È legittima perché la categoria è una **classificazione**: non
incide su protocollo, date, termini di legge né sul documento.

## 4. Il numero accanto alla categoria

Il conteggio mostra **quanti atti si vedranno cliccandoci**: nella vista Albo i **pubblicati**,
nella vista Archivio gli **archiviati consultabili**. Gli atti in **diritto all'oblio** non
sono mai contati (hanno `state = 0`): **il numero non rivela l'esistenza di un atto rimosso**.
