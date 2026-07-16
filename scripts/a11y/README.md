# Scansione automatica di accessibilità (axe-core)

Copre la parte **nostra**: struttura, ruoli ARIA, nomi accessibili, etichette, intestazioni —
cioè ciò che nessun tema dell'ente può riparare.

**Non copre** (e va detto, non nascosto):
- **contrasto colori** → serve un browser vero, e dipende comunque dal **tema dell'ente**;
- **zoom 200% / reflow 320px** → serve il rendering;
- **screen reader e tastiera** → non surrogabili: vanno provati da una persona.

## Uso

```bash
# una volta sola, dentro il container:
ddev exec bash -c "mkdir -p /var/www/html/tmp/a11y && cd /var/www/html/tmp/a11y \
  && npm init -y && npm install axe-core jsdom"

# poi, ogni volta (id atto pubblicato e archiviato come argomenti):
ddev exec node /var/www/html/scripts/a11y/scan.mjs https://albo-online.ddev.site <id_pubbl> <id_arch>
```

Esce con codice 1 se trova violazioni **critical** o **serious**.

## Esito al 2026-07-13

**0 violazioni gravi** su lista atti, dettaglio pubblicato e dettaglio archiviato — su J5 e J6.
L'unica segnalazione (`region`, moderate) riguarda il **modulo di login di Cassiopeia**, non il
componente.
