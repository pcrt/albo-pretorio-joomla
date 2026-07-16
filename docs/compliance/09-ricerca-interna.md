# Ricerca interna vs indicizzazione esterna

**Deliverable S4-T5.**

## Provenienza

- 🔗 **Linea guida del Garante (derivata):** non un articolo di legge puntuale, ma una raccomandazione delle **Linee Guida del Garante 2014** (doc. web 3134436), che discende dal principio di deindicizzazione: rendere gli atti consultabili tramite **ricerca interna al sito**, per evitare la **decontestualizzazione** — cioè che un atto sia raggiunto tramite un URL "isolato" trovato su un motore esterno, fuori dal contesto dell'albo.
- 🛠️ **Scelta di progetto:** fornire una ricerca interna adeguata. La metà complementare (niente URL indicizzabili) è già assolta da **S4-T1** (`noindex` ovunque).

---

## Le due facce dello stesso principio

1. **Non trovabile dai motori esterni** → deindicizzazione (S4-T1): meta `robots` + `X-Robots-Tag`, forzato sugli archiviati. Un atto non deve comparire in una ricerca su Google.
2. **Trovabile dentro il sito** → ricerca interna (questo task): il cittadino consulta e trova gli atti navigando l'albo, nel suo contesto.

Insieme evitano la decontestualizzazione: l'atto è accessibile **nel suo contesto** (l'albo dell'ente, con le sue date e il suo stato), non come pagina isolata pescata da un motore.

---

## Cosa offre il componente (già presente, verificato)

La lista pubblica dell'albo ha una **barra di ricerca e filtri**:

- **Ricerca testuale** su **titolo**, **oggetto** e **numero di repertorio** (`AttiModel::getListQuery`, campo `filter[search]`).
- **Filtri**: categoria, tipologia di atto, anno di protocollo.
- **Ordinamento** per data di pubblicazione.
- La stessa ricerca opera anche sulla vista **«Archivio Atti»** (atti scaduti consultabili), così anche gli atti archiviati si trovano dall'interno, non dai motori.

Rispetta la visibilità: la ricerca vede solo gli atti pubblicamente consultabili (`state IN (1,2)`), quindi **non** espone bozze, atti in oblio o cestinati (coerente con M22).

**Verificato:** cercando un termine presente nell'oggetto di un solo atto, la lista restituisce **solo quell'atto**.

---

## Riferimenti nel codice

- `src/components/com_alboonline/src/Model/AttiModel.php` — `getListQuery()` (ricerca su titolo/oggetto/repertorio; filtri categoria/tipo/anno) e visibilità `state IN (1,2)`.
- `src/components/com_alboonline/tmpl/atti/default.php` — la barra di ricerca e filtri.
- `docs/compliance/05-deindicizzazione.md` — la metà complementare (niente indicizzazione esterna).
