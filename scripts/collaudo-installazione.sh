#!/usr/bin/env bash
#
# Collauda L'INSTALLATORE: aggiornamento, disinstallazione, installazione pulita.
# Dev-only. DISTRUTTIVO: la disinstallazione cancella le tabelle dell'albo.
#
# USO:  bash scripts/collaudo-installazione.sh <cartella-ambiente>
#       bash scripts/collaudo-installazione.sh ~/albo-j5
#       bash scripts/collaudo-installazione.sh ~/albo-j6
#
# PERCHE' ESISTE
# Le suite esistenti (collaudo-auto.sh, collaudo-backend.php) collaudano l'ALBO: navigano,
# pubblicano, defiggono. Nessuna collauda l'INSTALLATORE — ed e' li' che questo progetto si
# e' rotto ogni volta:
#   - layouts/ non dichiarato nel manifest    -> verifica firma muta in produzione
#   - lingue dei plugin scartate da Joomla    -> etichette inglesi per sempre
#   - Joomla\CMS\Filesystem\File rimosso in J6 -> allegati non salvati, in silenzio
# Tutti e tre invisibili alle prove esistenti, perche' quelle prove girano su un albero di
# file, non su un'installazione.
#
# In piu' un ente reale non fa quasi mai un'installazione pulita: fa un AGGIORNAMENTO sopra
# i suoi dati. Ed e' il percorso che nessuno provava.
#
# LA DISINSTALLAZIONE NON E' UN CAPRICCIO: `uninstallPlugins()` non e' coperto da nulla, e
# un plugin che resta orfano dopo la disinstallazione e' un errore che si vede solo dopo.
#
set -uo pipefail

AMBIENTE="${1:-}"
[ -d "$AMBIENTE" ] || { echo "Uso: bash scripts/collaudo-installazione.sh <cartella-ambiente>" >&2; exit 2; }
AMBIENTE="$(cd "$AMBIENTE" && pwd)"
NOME="$(basename "$AMBIENTE")"
RADICE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

VERSIONE="$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' \
	"$RADICE/src/alboonline.xml" | head -1)"
PACCHETTO="$RADICE/dist/com_alboonline-$VERSIONE.zip"
[ -f "$PACCHETTO" ] || { echo "ERRORE: manca $PACCHETTO — lancia prima bin/build.sh" >&2; exit 1; }

PASS=0; FAIL=0
ok()   { echo "  PASS  $1"; PASS=$((PASS+1)); }
ko()   { echo "  FAIL  $1"; FAIL=$((FAIL+1)); }

# ddev risolve il progetto dalla CARTELLA CORRENTE: tutto va eseguito stando dentro.
cd "$AMBIENTE"
PFX="$(grep -oP "public \\\$dbprefix = '\K[^']+" configuration.php)"

sql() { ddev mysql -N -e "$1" 2>/dev/null; }

# ddev vede solo dentro il progetto, quindi il pacchetto va portato li'. Si usa una cartella
# DEDICATA e non dist/: quando l'ambiente e' anche l'albero da cui si costruisce, dist/
# contiene i pacchetti veri e ripulirla a fine collaudo li cancella. E' successo.
APPOGGIO="$AMBIENTE/.collaudo-pkg"

installa() {
	mkdir -p "$APPOGGIO"
	cp "$1" "$APPOGGIO/"
	ddev exec php cli/joomla.php extension:install \
		--path=".collaudo-pkg/$(basename "$1")" > /dev/null 2>&1
}

echo "════════════════════════════════════════════"
echo "  Collaudo dell'installatore — $NOME"
echo "════════════════════════════════════════════"

# ── Rete di sicurezza ────────────────────────────────────────────────────────
# Il test cancella le tabelle. Il backup non e' per paura di perdere i dati (sono di
# prova): e' per poter rimettere l'ambiente com'era invece di doverlo ricostruire.
BACKUP="$HOME/.cache/albo-prima-collaudo-installazione-$NOME.sql"
mkdir -p "$HOME/.cache"
echo "▸ Salvo il database in $BACKUP"
ddev export-db --file="$BACKUP.gz" > /dev/null 2>&1 || { echo "  ERRORE: backup fallito, mi fermo." >&2; exit 1; }
[ -s "$BACKUP.gz" ] || { echo "  ERRORE: backup vuoto, mi fermo." >&2; exit 1; }

# Il database NON basta. La disinstallazione rimuove media/com_alboonline/, e li' dentro
# vivono allegati/ e referti/ — i documenti veri. Ripristinare le sole tabelle lascia righe
# che puntano a file inesistenti: l'albo sembra a posto e la verifica della firma e' muta,
# perche' FirmaVerifier non ha niente da leggere. E' successo: ci ho perso mezz'ora a
# cercare una regressione che non c'era.
DOCUMENTI="$HOME/.cache/albo-documenti-$NOME"
rm -rf "$DOCUMENTI"; mkdir -p "$DOCUMENTI"
for cartella in allegati referti; do
	[ -d "$AMBIENTE/media/com_alboonline/$cartella" ] \
		&& cp -a "$AMBIENTE/media/com_alboonline/$cartella" "$DOCUMENTI/"
done
echo "  (documenti al sicuro in $DOCUMENTI)"

ATTI_PRIMA=$(sql "SELECT COUNT(*) FROM ${PFX}alboonline_atti;")
ALLEGATI_PRIMA=$(sql "SELECT COUNT(*) FROM ${PFX}alboonline_allegati;")
echo "  ($ATTI_PRIMA atti, $ALLEGATI_PRIMA allegati da conservare)"
echo ""

# ── I01 · AGGIORNAMENTO sopra dati veri ──────────────────────────────────────
# E' cio' che fara' ogni ente: non installa da zero, aggiorna.
echo "▸ I01 — aggiornamento sopra i dati esistenti"
installa "$PACCHETTO"
ATTI_DOPO=$(sql "SELECT COUNT(*) FROM ${PFX}alboonline_atti;")
ALLEGATI_DOPO=$(sql "SELECT COUNT(*) FROM ${PFX}alboonline_allegati;")

[ "$ATTI_DOPO" = "$ATTI_PRIMA" ] \
	&& ok "I01a l'aggiornamento conserva gli atti ($ATTI_DOPO/$ATTI_PRIMA)" \
	|| ko "I01a l'aggiornamento ha perso atti: $ATTI_DOPO invece di $ATTI_PRIMA"

[ "$ALLEGATI_DOPO" = "$ALLEGATI_PRIMA" ] \
	&& ok "I01b l'aggiornamento conserva gli allegati ($ALLEGATI_DOPO/$ALLEGATI_PRIMA)" \
	|| ko "I01b l'aggiornamento ha perso allegati: $ALLEGATI_DOPO invece di $ALLEGATI_PRIMA"

VER=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' \
	"$AMBIENTE/administrator/components/com_alboonline/alboonline.xml" | head -1)
[ "$VER" = "$VERSIONE" ] && ok "I01c la versione installata e' la $VER" \
	|| ko "I01c versione $VER invece di $VERSIONE"

# I lavori pianificati non devono duplicarsi a ogni aggiornamento (M37).
N_TASK=$(sql "SELECT COUNT(*) FROM ${PFX}scheduler_tasks WHERE type LIKE 'plg_task_alboonline%';")
[ "$N_TASK" = "2" ] && ok "I01d i 2 lavori pianificati ci sono e non si sono duplicati" \
	|| ko "I01d lavori pianificati: $N_TASK invece di 2"

# ── I01e · AGGIORNAMENTO DA UNA VERSIONE VECCHIA VERA ────────────────────────
# Installare il pacchetto corrente sopra se stesso NON e' un test di aggiornamento: un ente
# aggiorna da una versione PRECEDENTE, che puo' aver lasciato in giro roba che la nuova non
# spedisce piu'. E' cosi' che e' sfuggito questo difetto: fino alla 1.1.0 le lingue si
# spedivano anche nelle cartelle del core con <languages>; togliendolo, Joomla NON cancella
# i file vecchi (non cancella cio' che un manifest smette di dichiarare) e quelli VINCONO,
# perche' Language::load() cerca prima nel core. Risultato: l'ente si teneva le traduzioni
# vecchie e ogni etichetta nuova gli usciva come codice grezzo.
#
# Il pacchetto vecchio si prende dalla release pubblicata: e' quello che ha in mano un ente,
# non una nostra ricostruzione. Se non c'e' rete, si salta e lo si DICE — un test che non
# gira non deve somigliare a un test verde.
echo ""
echo "▸ I01e — aggiornamento da una release vecchia vera"
VECCHIO="$HOME/.cache/albo-release-precedente.zip"

# La release precedente si CERCA, non si scrive: un numero cablato invecchia al primo
# rilascio e il test finisce per provare l'aggiornamento da una versione che non e' piu'
# quella da cui aggiorna un ente. Si prende la piu' recente diversa da quella corrente.
if [ ! -f "$VECCHIO" ]; then
	PRECEDENTE=$(gh release list --repo pcrt/albo-pretorio-joomla --limit 10 2>/dev/null \
		| awk '{print $1}' | grep -v "^v${VERSIONE}$" | head -1)

	if [ -n "$PRECEDENTE" ]; then
		gh release download "$PRECEDENTE" --repo pcrt/albo-pretorio-joomla --pattern '*.zip' \
			--output "$VECCHIO" --clobber > /dev/null 2>&1 || true
	fi
fi

if [ ! -s "$VECCHIO" ]; then
	echo "  SALTATO: nessuna release precedente da cui aggiornare (o manca la rete). NON e' un PASS."
else
	installa "$VECCHIO"
	installa "$PACCHETTO"

	# -mindepth 2: si guarda DENTRO le cartelle di lingua (it-IT/, en-GB/...), che e' l'unico
	# posto dove Joomla mette i file. Un .ini sciolto in administrator/language/ non lo legge
	# nessuno: e' spazzatura di qualche copia a mano, non un residuo dell'installazione, e
	# segnalarlo qui darebbe un rosso per una cosa che il prodotto non ha fatto.
	RESIDUI=$(find "$AMBIENTE/administrator/language" "$AMBIENTE/language" -mindepth 2 \
		\( -name 'com_alboonline*.ini' -o -name 'plg_*_alboonline*.ini' \) 2>/dev/null | wc -l)
	[ "$RESIDUI" -eq 0 ] \
		&& ok "I01e l'aggiornamento porta via i file di lingua della versione vecchia" \
		|| ko "I01e restano $RESIDUI file di lingua vecchi nelle cartelle del core: oscurerebbero i nuovi"

	# Contro-prova: non devo aver cancellato le lingue di JOOMLA insieme alle nostre.
	CORE=$(find "$AMBIENTE/administrator/language/it-IT" -name '*.ini' 2>/dev/null | wc -l)
	[ "$CORE" -gt 100 ] \
		&& ok "I01f le lingue di Joomla sono intatte ($CORE file)" \
		|| ko "I01f nelle lingue di Joomla ne restano solo $CORE: ho cancellato roba non mia"
fi

# ── I02 · DISINSTALLAZIONE ───────────────────────────────────────────────────
echo ""
echo "▸ I02 — disinstallazione (nessuno l'aveva mai provata)"
# extension:remove vuole l'ID NUMERICO, non il nome: passargli "com_alboonline" fallisce
# in silenzio, il componente resta installato e il test darebbe un verde falso.
EXT_ID=$(sql "SELECT extension_id FROM ${PFX}extensions WHERE element='com_alboonline' AND type='component' LIMIT 1;")

if [ -z "$EXT_ID" ]; then
	ko "I02 il componente non risulta installato: non c'e' niente da disinstallare"
else
	# -n obbligatorio: senza, il comando chiede conferma, non la riceve e si pianta.
	# Il test resterebbe verde per il motivo sbagliato (non ha disinstallato niente).
	ddev exec php cli/joomla.php extension:remove "$EXT_ID" -n > /dev/null 2>&1 || true
fi

TAB=$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE '${PFX}alboonline_%';")
[ "$TAB" = "0" ] && ok "I02a le tabelle dell'albo sono state rimosse" \
	|| ko "I02a restano $TAB tabelle dell'albo dopo la disinstallazione"

# Il pezzo che non era coperto da niente: i plugin li disinstalla script.php a mano.
PLG=$(sql "SELECT COUNT(*) FROM ${PFX}extensions WHERE element='alboonline' AND type='plugin';")
[ "$PLG" = "0" ] && ok "I02b i 2 plugin sono stati disinstallati (uninstallPlugins)" \
	|| ko "I02b restano $PLG plugin orfani: la disinstallazione li ha lasciati indietro"

COMP=$(sql "SELECT COUNT(*) FROM ${PFX}extensions WHERE element='com_alboonline';")
[ "$COMP" = "0" ] && ok "I02c il componente non risulta piu' installato" \
	|| ko "I02c il componente risulta ancora installato"

# ── I03 · INSTALLAZIONE PULITA ───────────────────────────────────────────────
echo ""
echo "▸ I03 — installazione pulita (come la prima volta di un ente)"
installa "$PACCHETTO"

TAB=$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE '${PFX}alboonline_%';")
[ "$TAB" -ge 5 ] && ok "I03a le $TAB tabelle dell'albo sono state create" \
	|| ko "I03a solo $TAB tabelle create (attese almeno 5)"

PLG=$(sql "SELECT COUNT(*) FROM ${PFX}extensions WHERE element='alboonline' AND type='plugin';")
[ "$PLG" = "2" ] && ok "I03b i 2 plugin sono stati installati" || ko "I03b plugin installati: $PLG invece di 2"

N_TASK=$(sql "SELECT COUNT(*) FROM ${PFX}scheduler_tasks WHERE type LIKE 'plg_task_alboonline%';")
[ "$N_TASK" = "2" ] && ok "I03c i 2 lavori pianificati sono stati creati (M37)" \
	|| ko "I03c lavori pianificati: $N_TASK invece di 2"

# Senza layouts/ la verifica firma e' muta: e' il difetto della 1.0.0, e da qui non passa piu'.
[ -f "$AMBIENTE/administrator/components/com_alboonline/layouts/firma/esito.php" ] \
	&& ok "I03d l'installatore ha messo il layout dell'esito firma" \
	|| ko "I03d layouts/firma/esito.php NON installato: la verifica firma sarebbe muta"

[ -f "$AMBIENTE/administrator/components/com_alboonline/language/it-IT/com_alboonline.ini" ] \
	&& ok "I03e la copia interna delle lingue e' stata installata" \
	|| ko "I03e lingue interne non installate: su Joomla inglese resterebbe inglese"

[ -f "$AMBIENTE/media/com_alboonline/guida/esempi/referto-di-esempio.pdf" ] \
	&& ok "I03f il referto di esempio della guida e' stato installato" \
	|| ko "I03f referto di esempio mancante: la guida offline darebbe 404"

# ── Rimetto l'ambiente com'era ───────────────────────────────────────────────
echo ""
echo "▸ Rimetto il database com'era"
ddev import-db --file="$BACKUP.gz" > /dev/null 2>&1 && echo "  ripristinato" || echo "  ⚠️  RIPRISTINO FALLITO: il backup e' in $BACKUP.gz"

echo "▸ Rimetto i documenti (la disinstallazione li porta via insieme a media/)"
for cartella in allegati referti; do
	[ -d "$DOCUMENTI/$cartella" ] && cp -a "$DOCUMENTI/$cartella" "$AMBIENTE/media/com_alboonline/"
done
N=$(find "$AMBIENTE/media/com_alboonline/allegati" -type f ! -name 'index.html' ! -name '.htaccess' 2>/dev/null | wc -l)
echo "  $N allegati rimessi"

rm -rf "$APPOGGIO"

echo ""
echo "════════════════════════════════════════════"
echo "  PASS: $PASS   FAIL: $FAIL"
echo "════════════════════════════════════════════"
[ "$FAIL" -eq 0 ] || exit 1
