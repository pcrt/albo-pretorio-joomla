# Deindicizzazione — perché e come gli atti d'albo non vanno su Google

**Deliverable S4-T1.** Documenta come il componente impedisce ai motori di ricerca di indicizzare gli atti dell'albo, con **tre livelli** di protezione, e perché questo regime è opposto a quello dell'Amministrazione Trasparente.

> **In una riga:** per l'Albo Pretorio l'indicizzazione dai motori è **sconsigliata/vietata** (diritto all'oblio); il componente emette `noindex` come **default**, lo **forza** sugli atti archiviati, e lo applica sia nel meta HTML sia nell'header HTTP (che copre anche i PDF).

---

## 1. Perché

L'albo pretorio pubblica atti **per un tempo limitato**, poi li ritira. Se un atto restasse **reperibile su Google** (magari cercando nome e cognome di una persona) anche dopo la scadenza, si avrebbe una **decontestualizzazione** e una permanenza indebita: esattamente ciò che il **diritto all'oblio** vuole evitare. Il Garante (Linee guida 2014, doc. web 3134436) ha **sanzionato enti** per atti rimasti indicizzati oltre i termini.

Questo è l'**opposto** dell'Amministrazione Trasparente (D.Lgs. 33/2013), dove l'indicizzazione è invece ammessa e utile. Vedi `docs/compliance/02-albo-vs-trasparenza.md`.

---

## 2. Come — tre livelli

**a) Meta tag `robots` nella pagina dell'atto (HTML).**
La vista del singolo atto emette `<meta name="robots" content="...">`. Regola:
- atti **in corso**: default **`noindex, nofollow`** (configurabile; un ente può fare un override per singolo atto);
- atti **archiviati** (post-defissione): **`noindex, noarchive` FORZATO**, non sovrascrivibile — non devono restare reperibili, nemmeno dalla cache.

**b) Header HTTP `X-Robots-Tag` (a livello di risposta).**
Lo stesso valore è emesso come header `X-Robots-Tag`. Serve per due motivi: vale anche per i crawler che ignorano il meta, ed è **l'unico canale per le risorse non-HTML**. Per questo è impostato **anche sul download dei PDF allegati** (`noindex, noarchive`): un PDF non ha meta tag, quindi senza header sarebbe indicizzabile.

**c) `robots.txt` (misura di sito, raccomandata).**
A livello di installazione si può aggiungere una regola in `robots.txt`. **Attenzione al paradosso:** un `Disallow` in robots.txt *impedisce al crawler di leggere la pagina* e quindi di **vedere il `noindex`** — un atto già indicizzato resterebbe indicizzato. Per questo il meccanismo **primario** è il `noindex` (a) + (b); il `robots.txt` è una misura **complementare**, utile soprattutto per aree riservate. Raccomandazione minima (da adattare all'URL dell'albo sul sito dell'ente):

```
# Complementare al noindex: NON usare Disallow sulle pagine che devono
# essere de-indicizzate (il crawler non leggerebbe il noindex).
# Usare Disallow solo per aree che non devono proprio essere raggiunte.
User-agent: *
Disallow: /*?option=com_alboonline*task=download*   # download diretti
```

---

## 3. Configurazione

- **Opzioni del componente → Indicizzazione motori di ricerca**: default **`noindex, nofollow`**. L'ente può cambiarlo per gli atti *in corso*, ma sugli archiviati il `noindex` è comunque forzato dal codice.
- **Per singolo atto**: campo *Indicizzazione* nella scheda dell'atto (override, solo mentre è in corso).

---

## 4. Verifica

Si controlla con gli strumenti per sviluppatori del browser o via `curl -I`:
- la pagina di un atto deve avere `<meta name="robots" content="noindex, ...">` **e** l'header `X-Robots-Tag: noindex, ...`;
- il download di un allegato PDF deve avere l'header `X-Robots-Tag: noindex, noarchive`;
- un atto **archiviato** deve mostrare `noindex, noarchive` a prescindere dall'impostazione globale.

---

## 5. Riferimenti nel codice

- `src/components/com_alboonline/src/View/Atto/HtmlView.php` — meta + header per la pagina dell'atto (default noindex, forzato sugli archiviati).
- `src/components/com_alboonline/src/Controller/DownloadController.php` — `X-Robots-Tag` sul download dei PDF.
- `src/administrator/components/com_alboonline/config.xml` — opzione `default_robots` (default `noindex, nofollow`).
- `docs/ricerca-normativa/03-privacy-gdpr.md` — deindicizzazione, Garante 2014, sanzioni.
