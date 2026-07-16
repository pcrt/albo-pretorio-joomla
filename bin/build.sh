#!/usr/bin/env bash
#
# Costruisce il pacchetto installabile di Albo Pretorio Online (S7-T4).
#
# PERCHE' SERVE UNO SCRIPT.
# Il repository e' nel formato "installato" (administrator/…, components/…, media/…, plugins/…),
# che e' comodo per sviluppare ma NON e' la struttura che l'installer di Joomla si aspetta.
# Il manifest (alboonline.xml) pretende un pacchetto fatto cosi':
#
#     alboonline.xml          <- il manifest, in cima
#     script.php              <- lo script di installazione (crea i 2 lavori pianificati, M37)
#     site/                   <- il componente lato sito (src, forms, tmpl)
#     site/languages/         <- lingue del sito
#     administrator/          <- il componente lato amministrazione (+ vendor: mPDF)
#     administrator/languages/
#     media/                  <- css, js, joomla.asset.json
#     plugins/                <- i due plugin (task, content)
#
# Copiare a mano queste cartelle e' il modo migliore per dimenticarne una e accorgersene
# quando il pacchetto e' gia' in mano a un ente.
#
# USO:  bash bin/build.sh            -> produce dist/com_alboonline-<versione>.zip
#
set -euo pipefail

RADICE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# src/ = tutto e solo cio' che finisce nel pacchetto. Fuori sta il progetto (docs, scripts,
# bin, CI): utile a chi sviluppa, inutile a chi installa. La domanda "questo file c'entra
# col componente?" ha una risposta guardando dov'e', non chiedendo a qualcuno.
SORGENTE="$RADICE/src"
MANIFEST="$SORGENTE/alboonline.xml"

# La versione NON si scrive qui: si legge dal manifest, che e' l'unica fonte di verita'.
# Un numero di versione scritto in due posti e' un numero di versione sbagliato in uno dei due.
VERSIONE="$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$MANIFEST" | head -1)"

if [ -z "$VERSIONE" ]; then
	echo "ERRORE: non riesco a leggere la versione da $MANIFEST" >&2
	exit 1
fi

USCITA="$RADICE/dist"
LAVORO="$(mktemp -d)"
trap 'rm -rf "$LAVORO"' EXIT

echo "▸ Albo Pretorio Online — versione $VERSIONE"

# ---------------------------------------------------------------------------
# 1. CONTROLLI PRIMA DI IMPACCHETTARE
#    Un pacchetto che non compila e' peggio di un pacchetto che non esiste: il primo
#    lo installi su un sito vero e lo scopri dalla pagina bianca.
# ---------------------------------------------------------------------------
echo "▸ Controllo sintassi PHP…"
ERRORI=0
while IFS= read -r -d '' f; do
	php -l "$f" > /dev/null 2>&1 || { echo "  ✗ $f"; ERRORI=1; }
done < <(find "$SORGENTE" -name '*.php' -not -path '*/vendor/*' -print0)

[ "$ERRORI" -eq 0 ] || { echo "ERRORE: sintassi PHP non valida. Non impacchetto." >&2; exit 1; }

echo "▸ Controllo XML e JSON…"
while IFS= read -r -d '' x; do
	python3 -c "import xml.dom.minidom,sys; xml.dom.minidom.parse(sys.argv[1])" "$x" \
		|| { echo "ERRORE: XML non valido: $x" >&2; exit 1; }
done < <(find "$SORGENTE" -name '*.xml' -not -path '*/vendor/*' -print0)

python3 -c "import json,sys; json.load(open(sys.argv[1]))" \
	"$SORGENTE/media/com_alboonline/joomla.asset.json" \
	|| { echo "ERRORE: joomla.asset.json non valido" >&2; exit 1; }

# Qui c'era il confronto della guida fra ~/albo-online e ~/albo-j6, dichiarato TEMPORANEO
# quando fu scritto: serviva perche' la guida viveva in due cartelle copiate a mano e poteva
# essere aggiornata in una sola (successo davvero: la nota sul limite PHP finita solo su J5).
# Ora la guida sta in src/media/com_alboonline/guida/ e basta: non c'e' piu' un secondo
# esemplare da cui possa divergere. Un controllo che non ha piu' niente da controllare non
# si tiene "per sicurezza": si toglie, o diventa rumore che si impara a ignorare.

# ---------------------------------------------------------------------------
# 1c. LE DIPENDENZE (mPDF)
#
# vendor/ NON sta nel repository: lo scarica Composer dal lock. Prima erano 631 file
# committati a mano — il 77% del repo — e nessuno garantiva che fossero davvero quelli del
# lock. Non lo erano: mancavano 62 font, tolti da qualcuno per il peso e mai dichiarati.
# Un vendor che diverge dal lock e' una dipendenza non tracciata dentro un prodotto che
# installa una PA.
# ---------------------------------------------------------------------------
COMPOSER="$(command -v composer || command -v composer.phar || true)"
[ -n "$COMPOSER" ] || {
	echo "ERRORE: serve Composer per costruire il pacchetto (scarica mPDF)." >&2
	echo "  Installalo: https://getcomposer.org — chi INSTALLA l'albo non ne ha bisogno," >&2
	echo "  serve solo a chi costruisce il pacchetto." >&2
	exit 1
}

echo "▸ Scarico le dipendenze (mPDF)…"
(cd "$RADICE" && "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --quiet) \
	|| { echo "ERRORE: composer install fallito." >&2; exit 1; }

# I FONT: mPDF ne porta 82, per alfabeti di mezzo mondo — 95 MB. Ne servono 20, i DejaVu,
# che sono quelli con cui il referto e' impaginato. Gli altri (etiopico, birmano, accadico,
# meitei…) pesano 74 MB e un albo pretorio italiano non li usera' mai: spedirli farebbe un
# pacchetto da 80 MB che meta' degli enti non riesce a caricare.
#
# ATTENZIONE, la distinzione conta: qui si tolgono font MAI USATI. NON si toccano i DejaVu,
# nemmeno se il pacchetto sembra pesante — il referto e' un documento probatorio e un font
# mancante lo mutila. Se un ente non riesce a caricare 8,7 MB il problema e' il limite di
# PHP sul suo server (upload_max_filesize/post_max_size), e si alza da li': c'e' scritto
# nella guida. Non si mutila il prodotto per compensare un server mal configurato.
TTFONTS="$SORGENTE/administrator/components/com_alboonline/vendor/mpdf/mpdf/ttfonts"
if [ -d "$TTFONTS" ]; then
	find "$TTFONTS" -type f ! -name 'DejaVu*' -delete
	echo "▸ Font: tengo i $(find "$TTFONTS" -type f | wc -l) DejaVu, tolti gli alfabeti inutilizzati"
fi

# ---------------------------------------------------------------------------
# 2. COMPOSIZIONE DEL PACCHETTO
# ---------------------------------------------------------------------------
echo "▸ Compongo il pacchetto…"

# Il pacchetto E' src/. Una riga, e non c'e' piu' niente da dimenticare.
#
# Qui prima c'erano 61 righe che rimontavano il sorgente nella forma che i manifest
# pretendono: il manifest risaliva in cima, components/ diventava site/, le lingue si
# spostavano da administrator/language/ a administrator/languages/ e poi si ricopiavano
# in administrator/language/, quelle dei plugin si ricostruivano da zero, e le cartelle
# da copiare erano ELENCATE A MANO. Da quell'elenco si e' caduti: layouts/ non c'era, e
# l'esito della verifica firma e' rimasto muto su ogni installazione della 1.0.0.
#
# Non serviva un elenco migliore: serviva non avere un elenco. Ora src/ ha gia' la forma
# del pacchetto, quindi comporre significa copiare.
cp -a "$SORGENTE/." "$LAVORO/"

# Roba che non deve finire in mano a un ente. L'elenco NON sta qui: sta in
# bin/non-spedito.txt, che legge anche il guardiano — cosi' le due cose non possono
# dissentire. Se stessero in due posti, il guardiano segnalerebbe come "dimenticato"
# cio' che il build butta di proposito, e smetteremmo di dargli retta.
POTA=()
while IFS= read -r riga; do
	riga="${riga%%#*}"
	riga="$(echo -n "$riga" | tr -d '[:space:]')"
	[ -z "$riga" ] && continue
	[ ${#POTA[@]} -gt 0 ] && POTA+=(-o)
	POTA+=(-name "$riga")
done < "$RADICE/bin/non-spedito.txt"

[ ${#POTA[@]} -gt 0 ] && find "$LAVORO" \( "${POTA[@]}" \) -exec rm -rf {} + 2>/dev/null || true

# ---------------------------------------------------------------------------
# 3. RETE DI SICUREZZA: il pacchetto contiene davvero i pezzi indispensabili?
#    mPDF assente = il referto non si genera. Lo script di installazione assente =
#    i due lavori pianificati non vengono creati (M37). Meglio fallire qui.
# ---------------------------------------------------------------------------
echo "▸ Verifico il contenuto…"
ADMIN="administrator/components/com_alboonline"
SITO="components/com_alboonline"
INDISPENSABILI=(
	"alboonline.xml"
	"script.php"
	"$ADMIN/vendor/autoload.php"
	"$ADMIN/sql/install.mysql.utf8.sql"
	"media/com_alboonline/joomla.asset.json"
	"plugins/task/alboonline"
	"plugins/content/alboonline"
	"$SITO/tmpl"
	# La GPL non e' facoltativa: chi riceve il software deve ricevere anche le condizioni.
	"$ADMIN/LICENSE.txt"
	"$ADMIN/THIRD-PARTY-NOTICES.md"
	# L'avviso di copyright dei font DejaVu (licenza Bitstream Vera) DEVE accompagnare
	# ogni copia: se sparisce, la ridistribuzione dei font non e' piu' conforme.
	"$ADMIN/vendor/mpdf/mpdf/ttfonts/DejaVuinfo.txt"
	"$ADMIN/vendor/mpdf/mpdf/LICENSE.txt"
	# Il pulsante «Guida» punta qui: se il file non c'e', il pulsante porta a un 404.
	"media/com_alboonline/guida/index.html"
	# La guida linka questo PDF: enumerare i pezzi della guida lo aveva gia' lasciato fuori.
	"media/com_alboonline/guida/esempi/referto-di-esempio.pdf"
	# L'esito della verifica firma. Se manca, Joomla non protesta e il riquadro sparisce in
	# silenzio: nella 1.0.0 e' andata cosi'. Un difetto muto deve almeno fermare il build.
	"$ADMIN/layouts/firma/esito.php"
	# Le lingue: se spariscono, il componente torna a installarsi in inglese su chi aggiunge
	# l'italiano dopo, e il difetto NON si vede finche' non lo segnala un ente.
	"$ADMIN/language/it-IT/com_alboonline.ini"
	"$ADMIN/language/it-IT/com_alboonline.sys.ini"
	"$SITO/language/it-IT/com_alboonline.ini"
	"plugins/task/alboonline/language/it-IT/plg_task_alboonline.ini"
)
for pezzo in "${INDISPENSABILI[@]}"; do
	[ -e "$LAVORO/$pezzo" ] || { echo "ERRORE: manca dal pacchetto: $pezzo" >&2; exit 1; }
done

# La lista qui sopra copre solo cio' che qualcuno si e' ricordato di scriverci. Il guardiano
# invece confronta il pacchetto con TUTTO il sorgente e con cio' che i manifest promettono:
# e' lui che prende i file dimenticati, che e' il modo in cui ci siamo fatti male finora.
php "$RADICE/bin/verifica-pacchetto.php" "$RADICE" "$LAVORO" || exit 1

# ---------------------------------------------------------------------------
# 4. ZIP
# ---------------------------------------------------------------------------
mkdir -p "$USCITA"
PACCHETTO="$USCITA/com_alboonline-$VERSIONE.zip"
rm -f "$PACCHETTO"
(cd "$LAVORO" && zip -qr "$PACCHETTO" .)

echo "▸ Fatto: $PACCHETTO ($(du -h "$PACCHETTO" | cut -f1))"
