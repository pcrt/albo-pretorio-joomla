# scripts/ — utilità di sviluppo (dev-only)

> ⚠️ Questa cartella **non fa parte del pacchetto distribuibile** del componente: va esclusa dal build (S7-T4) e serve solo in ambiente di test/sviluppo.

## `dev-seed.php`

Popola l'ambiente di test con dati di prova per `com_alboonline`.

```bash
ddev exec php scripts/dev-seed.php
```

Crea:
- la categoria **"Determine"** (idempotente: non la duplica);
- un atto in **Bozza**;
- un atto **Pubblicato** (portato da Bozza a InAttesa via `AttoModel::publishAtto()`, con assegnazione protocollo).

Gli alias degli atti hanno un suffisso temporale, quindi lo script si può rilanciare più volte senza collisioni.

Per portare un atto in stato **Pubblicato (blindato)** o **Archiviato** durante i test, usare lo scheduler:
```bash
# forza la scadenza del buffer su un atto InAttesa e blinda
ddev mysql -e "UPDATE jos_alboonline_atti SET buffer_expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE) WHERE id=<ID>;"
ddev exec php cli/joomla.php scheduler:run --all
```
