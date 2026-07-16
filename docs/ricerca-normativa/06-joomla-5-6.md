# Ricerca tecnica — Compatibilità Joomla 5 → 6 (metà 2026)

**Ambito:** compatibilità del componente "da Joomla 5 alle versioni più recenti di Joomla 6".

## Stato Joomla 6 (luglio 2026)

- **Joomla 6.0.0**: rilasciato **14/10/2025**. **6.1.0** stabile **14/4/2026**; **6.2** previsto **13/10/2026**. PHP 8.5 supportato da 5.4.2 / 6.0.2.
  https://developer.joomla.org/roadmap.html
- **Requisiti** (minimi):

  | | Joomla 5 | Joomla 6 |
  |---|---|---|
  | PHP | 8.1.0 | **8.3.0** (racc. 8.4) |
  | MySQL | 8.0.13 | 8.0.13 |
  | MariaDB | 10.4.0 | 10.4.0 |

  Il salto reale è **PHP 8.3**. https://mysites.guru/blog/joomla-6-technical-requirements/

## Breaking changes rilevanti per il componente

Fonte primaria: https://api.joomla.org/cms-6/deprecated.html · https://manual.joomla.org/migrations/54-60/

**MVC**: `BaseDatabaseModel::getDbo()/setDbo()` → `getDatabase()/setDatabase()`; `Table::$db` → `getDatabase()`; `addModelPath()/addIncludePath()`; `AbstractView::$document`; `Form::getInstance()` → `FormFactory`; dispatcher legacy del componente rimosso (serve `services/provider.php` con `ComponentDispatcherFactory`).
**Factory/servizi statici**: `Factory::getConfig()/getSession()/getDocument()` deprecati a favore della **DI**. (Nota: `Factory::getApplication()` e `Factory::getContainer()` **non** rimossi.)
**Plugin (task+content)**: `registerListeners()` deprecato → **`SubscriberInterface::getSubscribedEvents()`**; impostare `allowLegacyListeners = false`. I task scheduler restano plugin del gruppo `task` (`onTaskOptionsList`/`onExecuteTask`). https://manual.joomla.org/docs/building-extensions/plugins/joomla-4-and-5-changes/
**Form/Custom Fields**: `UserField::$groups/$excluded` rimossi; gestione OPTGROUP cambiata.
**HTMLHelper/asset**: `HTMLHelper::stylesheet()/script()` → **WebAssetManager**.
**Router SEF**: `getRouter()` statici rimossi → `RouterFactory`/DI; il pattern `RouteHelper` + `Router` del componente resta valido.

## Strategia di compatibilità (stesso codebase su J5 e J6)

Un'estensione Joomla 5 "pulita" gira su Joomla 6 se rimuove il codice deprecato e non dipende dal plugin **Behaviour - Backward Compatibility 6** (ponte temporaneo, sparisce con J7).
Da presidiare: `getDatabase()` invece di `getDbo`; DI invece di Factory statici deprecati; plugin a `SubscriberInterface`; WebAssetManager; niente `Form::getInstance()`/`$this->document`.
**Manifest**: `<php_minimum>8.3</php_minimum>`; compatibilità dichiarata via Update Server (`<targetplatform name="joomla" version="(5\.[0-9]+|6\.[0-9]+)"/>`). Feature detection runtime con `version_compare(JVERSION, '6.0', '>=')` solo se serve.

## Esito per QUESTO componente

Scansione del codice reale: **già ~95% pronto**. Assenti getDbo, `$db`, HTMLHelper::script, Form::getInstance, JLoader, dispatcher legacy. **Plugin già a `SubscriberInterface`**. WebAssetManager già in uso. Da correggere: 1× `Factory::getUser()`, manifest (php_minimum/targetplatform/update server), verifica event object tipizzati, e **test empirico su istanza Joomla 6** (S1-T9).

## Rischi tecnici

- PHP 8.3 minimo: deprecation 8.1→8.3 (dynamic properties, null a parametri non-nullable).
- Rotture comportamentali a runtime non rilevabili da grep → test reale su J6.
- Dipendenza dal plugin b/c: solo ponte temporaneo (~2 anni prima di J7).

## Fonti
- Roadmap — https://developer.joomla.org/roadmap.html
- Deprecated API J6 — https://api.joomla.org/cms-6/deprecated.html
- Migrazione 5.4→6.0 — https://manual.joomla.org/migrations/54-60/
- Plugin changes — https://manual.joomla.org/docs/building-extensions/plugins/joomla-4-and-5-changes/
- Requisiti tecnici J6 — https://mysites.guru/blog/joomla-6-technical-requirements/
