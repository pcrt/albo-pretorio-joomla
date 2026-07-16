#!/usr/bin/env bash
# Suite dei controlli automatici sul frontend: naviga l'albo come un cittadino e verifica
# che cio' che deve vedersi si veda e cio' che deve essere negato sia negato.
# I test manuali/AT (browser, screen reader, axe) NON sono qui: non sono surrogabili.
#
# Uso: bash scripts/collaudo-auto.sh

# L'URL è parametrizzabile: la stessa suite deve poter girare su J5 e su J6 (un solo
# codice, due installazioni di collaudo). Da J6:
#   cd /home/stage/albo-j6 && ALBO_BASE=https://albo-j6.ddev.site bash /home/stage/albo-j5/scripts/collaudo-auto.sh
# `ddev mysql` usa la directory corrente, quindi lo script va lanciato DENTRO il progetto giusto.
SITE="${ALBO_BASE:-https://albo-j5.ddev.site}"
BASE="$SITE/index.php?option=com_alboonline"
PASS=0
FAIL=0

# Gli id NON sono cablati: si chiedono al database. Un test che si fida di un dataset
# fisso mente appena i dati cambiano (e durante il collaudo cambiano di continuo).
q() { ddev mysql -N -e "$1" 2>/dev/null | tr '\n' ' '; }

BOZZE=$(q "SELECT id FROM jos_alboonline_atti WHERE stato_atto = 0")
OBLIO=$(q "SELECT id FROM jos_alboonline_atti WHERE diritto_oblio = 1 AND state = 0")
PUBBL=$(q "SELECT id FROM jos_alboonline_atti WHERE stato_atto = 2 AND state = 1")
ARCHIV=$(q "SELECT id FROM jos_alboonline_atti WHERE stato_atto = 3 AND state = 2")

ok()   { echo "  PASS  $1"; PASS=$((PASS+1)); }
ko()   { echo "  FAIL  $1"; FAIL=$((FAIL+1)); }
code() { curl -gskL -o /dev/null -w "%{http_code}" "$1"; }
body() { curl -gskL "$1"; }

echo "=== A · Flussi funzionali"

# CT-A01 — la lista mostra solo gli atti consultabili.
# L'invariante non è «compare l'atto 21», è: ogni atto pubblicato compare, e nessuna
# bozza né atto in oblio compare — chiunque essi siano oggi.
L=$(body "$BASE&view=atti")
titolo() { q "SELECT titolo FROM jos_alboonline_atti WHERE id = $1"; }

for id in $PUBBL; do
  t=$(titolo "$id")
  echo "$L" | grep -qF "${t% }" \
    && ok "A01 lista: presente l'atto pubblicato $id" \
    || ko "A01 lista: MANCA l'atto pubblicato $id ($t)"
done

for id in $BOZZE; do
  t=$(titolo "$id")
  echo "$L" | grep -qF "${t% }" \
    && ko "A01 lista: compare la BOZZA $id ($t) — non deve" \
    || ok "A01 lista: assente la bozza $id"
done

for id in $OBLIO; do
  t=$(titolo "$id")
  echo "$L" | grep -qF "${t% }" \
    && ko "A01 lista: compare l'atto in OBLIO $id ($t) — non deve" \
    || ok "A01 lista: assente l'atto in oblio $id"
done

# CT-A02 — ricerca testuale. NB: le quadre di filter[search] vanno protette con
# curl -g, altrimenti curl le interpreta come globbing e NON invia la richiesta
# (risposta vuota che si scambia per "nessun risultato").
S=$(body "$BASE&view=atti&filter[search]=145")
echo "$S" | grep -q "145/2026" \
  && ok "A02 ricerca '145': trova l'atto atteso" \
  || ko "A02 ricerca '145': non trova l'atto atteso"
echo "$S" | grep -qE "Bando di gara n\. 3|Determina n\. 88" \
  && ko "A02 ricerca '145': restano atti non pertinenti (non filtra)" \
  || ok "A02 ricerca '145': esclude gli atti non pertinenti"

# CT-A13 — «Tutte le categorie» deve DAVVERO liberare dal filtro (M38).
# Con una sessione vera: filtro una categoria col form, poi clicco «Tutte le categorie», poi
# ricarico la lista senza parametri. Il filtro non deve tornare a incollarsi.
CATPRIMA=$(q "SELECT c.id FROM jos_categories c JOIN jos_alboonline_atti a ON a.catid = c.id
              WHERE c.extension = 'com_alboonline' AND a.stato_atto = 2 AND a.state = 1
              GROUP BY c.id HAVING COUNT(*) > 0 LIMIT 1")
CATPRIMA="${CATPRIMA% }"
# un atto pubblicato che NON sta in quella categoria: è quello che deve ricomparire
FUORI=$(q "SELECT titolo FROM jos_alboonline_atti
           WHERE stato_atto = 2 AND state = 1 AND catid <> ${CATPRIMA:-0} LIMIT 1")
FUORI="${FUORI% }"

if [ -n "$CATPRIMA" ] && [ -n "$FUORI" ]; then
  JAR=$(mktemp)
  curl -gsk -b "$JAR" -c "$JAR" -o /dev/null "$BASE&view=atti"
  TOK=$(curl -gsk -b "$JAR" -c "$JAR" "$BASE&view=atti" | grep -oE 'name="[a-f0-9]{32}" value="1"' | grep -oE '[a-f0-9]{32}' | head -1)
  curl -gsk -b "$JAR" -c "$JAR" -o /dev/null -X POST \
    -d "filter[search]=&filter[catid]=$CATPRIMA&filter[tipo_atto]=&filter[anno_protocollo]=&$TOK=1" \
    "$BASE&view=atti"

  curl -gskL -b "$JAR" -c "$JAR" "$BASE&view=atti" | grep -qF "$FUORI" \
    && ko "A13 il filtro categoria non si è applicato (test inattendibile)" \
    || ok "A13 filtro categoria applicato dal form"

  curl -gskL -b "$JAR" -c "$JAR" "$BASE&view=atti&catid=0" | grep -qF "$FUORI" \
    && ok "A13 «Tutte le categorie» libera dal filtro" \
    || ko "A13 «Tutte le categorie» NON libera dal filtro (cittadino chiuso in una categoria)"

  curl -gskL -b "$JAR" -c "$JAR" "$BASE&view=atti" | grep -qF "$FUORI" \
    && ok "A13 il filtro resta azzerato anche al ricaricamento successivo" \
    || ko "A13 il filtro si RI-INCOLLA al ricaricamento (azzerato solo per una richiesta)"

  rm -f "$JAR"
fi

# CT-A09 — dettaglio atto pubblicato
for id in $PUBBL; do
  c=$(code "$BASE&view=atto&id=$id")
  [ "$c" = "200" ] && ok "A09 dettaglio pubblicato $id → 200" \
                   || ko "A09 dettaglio pubblicato $id → $c (atteso 200)"
done

echo
echo "=== C · Privacy / GDPR"

# CT-C01 — bozze irraggiungibili per URL diretto
for id in $BOZZE; do
  c=$(code "$BASE&view=atto&id=$id")
  [ "$c" = "404" ] && ok "C01 bozza id=$id → 404" || ko "C01 bozza id=$id → $c (atteso 404)"
done

# CT-C02 — oblio: 404, e nessuna "lapide" che riveli la rimozione
for id in $OBLIO; do
  c=$(code "$BASE&view=atto&id=$id")
  [ "$c" = "404" ] && ok "C02 oblio id=$id → 404" || ko "C02 oblio id=$id → $c (atteso 404)"
  echo "$(body "$BASE&view=atto&id=$id")" | grep -qiE "rimoss|oblio|cancellat" \
    && ko "C02 la pagina dell'atto $id rivela l'oblio (lapide)" \
    || ok "C02 nessuna lapide sull'atto in oblio $id"
done

# CT-C04 — deindicizzazione: vale per OGNI archiviato consultabile, non per un id scelto
for id in $ARCHIV; do
  echo "$(body "$BASE&view=atto&id=$id")" | grep -qi 'name="robots"[^>]*noindex' \
    && ok "C04 archiviato $id: meta robots noindex" \
    || ko "C04 archiviato $id: meta robots noindex ASSENTE"
  curl -gskLI "$BASE&view=atto&id=$id" | grep -qi "x-robots-tag:.*noindex" \
    && ok "C04 archiviato $id: header X-Robots-Tag noindex" \
    || ko "C04 archiviato $id: header X-Robots-Tag ASSENTE"
done

# CT-C05 — default robots prudente
grep -A6 'name="default_robots"' administrator/components/com_alboonline/config.xml \
  | grep -q 'default="noindex, nofollow"' \
  && ok "C05 default_robots = noindex, nofollow" \
  || ko "C05 default_robots NON è noindex, nofollow"

echo
echo "=== D · AgID documento informatico"

# CT-D01 — metadati AgID sul dettaglio
# L'atto con segnatura di protocollo del dataset demo (se c'è ancora).
SEGN=$(q "SELECT id FROM jos_alboonline_atti WHERE segnatura_protocollo <> '' AND stato_atto = 2 LIMIT 1")
D=$(body "$BASE&view=atto&id=${SEGN% }")
for m in "AOO-COM-0012345/2026" "2026/0001"; do
  echo "$D" | grep -q "$m" && ok "D01 metadato presente: $m" || ko "D01 metadato ASSENTE: $m"
done

echo
echo "=== F · Sicurezza"

# CT-F01 — download senza token CSRF
c=$(code "$BASE&task=download.download&id=15")
[ "$c" = "403" ] || [ "$c" = "404" ] \
  && ok "F01 download senza token → $c (negato)" \
  || ko "F01 download senza token → $c (atteso 403/404)"

# CT-F03 — nessuna chiave di lingua grezza, nessun errore PHP
for u in "$BASE&view=atti" "$BASE&view=atto&id=${PUBBL%% *}" "$BASE&view=atto&id=${ARCHIV%% *}"; do
  X=$(body "$u")
  echo "$X" | grep -q "COM_ALBOONLINE_" \
    && ko "F03 chiave di lingua grezza in: $u" \
    || ok "F03 nessuna chiave grezza: ${u##*&}"
  echo "$X" | grep -qE "<b>(Warning|Notice|Fatal error|Deprecated)</b>" \
    && ko "F03 errore PHP in output: $u" \
    || ok "F03 nessun errore PHP: ${u##*&}"
done

echo
echo "================================"
echo "  PASS: $PASS   FAIL: $FAIL"
echo "================================"
[ "$FAIL" -eq 0 ]
