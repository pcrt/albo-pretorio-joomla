# Diritto all'oblio — workflow tracciato (attivazione e revoca)

**Deliverable S4-T4.**

## Provenienza

- 📜 **Norma diretta:** **art. 17 GDPR** (diritto alla cancellazione/oblio), **art. 12** (l'ente deve dar seguito alle istanze), **art. 5.2** (accountability: dimostrare di aver agito), **art. 17.3.b** (durante l'obbligo di pubblicazione l'oblio non prevale). Garante 2014.
- 🛠️ **Scelta di progetto:** un workflow **tracciato con audit** per queste istanze, simmetrico nelle due direzioni (attivazione ↔ revoca), con motivazione obbligatoria.

---

## Il ciclo completo dell'oblio

L'oblio si può **attivare** e **revocare**; entrambe le azioni sono **motivate** e **registrate** nell'audit log.

### Attivazione (istanza di cancellazione)

Il cittadino chiede la rimozione di un atto che lo riguarda. Il responsabile usa il pannello **«Attiva il diritto all'oblio»** (richiede un permesso dedicato) e **una motivazione** (il riferimento all'istanza). Effetto sulla visibilità, secondo la fase dell'atto:

| Fase dell'atto | Effetto dell'attivazione |
|---|---|
| **Archiviato** (termine concluso) | **Rimozione immediata**: l'atto diventa invisibile (`state = 0`) e non più raggiungibile per URL diretto. |
| **In pubblicazione** (nei termini) | Solo il **flag**: durante i termini la pubblicazione **prevale** (art. 17.3.b GDPR); la rimozione scatterà alla defissione, via scheduler. |
| **Bozza / in attesa** | Solo il flag (l'atto non è ancora pubblico); scatterà all'archiviazione. |

L'azione è registrata come **`oblio_attivato`** nell'audit log (chi, quando, motivazione).

> Nota: sugli atti **blindati** il campo oblio del form è disabilitato (parte del record congelato). Questa azione dedicata è quindi **l'unica via** per applicare l'oblio a un atto già pubblicato o archiviato — che è il caso reale di un'istanza sopravvenuta.

### Revoca

Se l'oblio è stato applicato per errore o l'istanza viene meno, il responsabile usa il pannello **«Attiva/Revoca»** con una motivazione. L'azione è registrata come **`oblio_revoked`**. La revoca **non basta** a riportare in pubblicazione un atto i cui PDF siano già stati distrutti (serve un atto nuovo) — vedi M16.

### Perché motivazione + audit e non un semplice interruttore

Un semplice flag, cambiabile senza traccia, non permetterebbe di **dimostrare** come e perché si è agito (accountability, art. 5.2). La motivazione obbligatoria + il log rendono ogni passaggio ricostruibile davanti al Garante. È lo stesso principio per cui la revoca è tracciata invece che vietata (M16): un'azione tracciata è preferibile a un divieto che verrebbe aggirato a mano nel database.

---

## Audit trail risultante

Per un atto oggetto di istanze si ottiene una sequenza ricostruibile, es.:

```
published      · System/Responsabile · <data>
archived       · System Task         · <data>
oblio_attivato · Responsabile         · «Istanza di cancellazione del sig. … prot. …»
(eventuale) oblio_revoked · Responsabile · «Istanza ritirata in data …»
```

---

## Nota tecnica (bug latente risolto)

Durante S4-T4 è emerso che `AlboonlineHelper::getActions()` **non calcolava** il permesso `alboonline.revokeoblio`: di conseguenza né il pannello di revoca (M16) né quello di attivazione comparivano nell'interfaccia (la revoca era stata provata solo via CLI). Corretto aggiungendo il permesso alla lista. Ora entrambi i pannelli sono operativi.

---

## Riferimenti nel codice

- `src/administrator/components/com_alboonline/src/Model/AttoModel.php` — `attivaOblio()`, `revokeOblio()`.
- `src/administrator/components/com_alboonline/src/Controller/AttoController.php` — azioni `attivaOblio`, `revokeOblio`.
- `src/administrator/components/com_alboonline/tmpl/atto/edit.php` — pannelli attivazione/revoca.
- `src/administrator/components/com_alboonline/src/Helper/AlboonlineHelper.php` — `getActions()` (permesso `alboonline.revokeoblio`), `logAction()`.
- `access.xml` — permesso `alboonline.revokeoblio`.
