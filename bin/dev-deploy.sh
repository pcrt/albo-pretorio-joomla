#!/usr/bin/env bash
#
# Costruisce il pacchetto e lo INSTALLA su un ambiente di sviluppo, come farebbe un ente.
#
# USO:  bash bin/dev-deploy.sh j5      -> installa su ~/albo-j5  (Joomla 5)
#       bash bin/dev-deploy.sh j6      -> installa su ~/albo-j6      (Joomla 6)
#       bash bin/dev-deploy.sh entrambi
#
# PERCHE' ESISTE
# Fino a oggi il codice arrivava negli ambienti di prova COPIANDO I FILE A MANO. Sembra
# comodo e costa carissimo:
#
#   1. Il manifest non viene esercitato da nessuno. Un file che il manifest non dichiara
#      resta comunque al suo posto nell'albero copiato, quindi funziona in prova e sparisce
#      in produzione. E' successo davvero: fino alla 1.0.0 l'esito della verifica firma non
#      compariva su NESSUNA installazione vera, e le 32 prove erano verdi lo stesso.
#   2. Le copie divergono. La guida allineata a mano fra J5 e J6, J6 rimasto a una versione
#      indietro: ogni volta lo stesso film.
#
# Con questo script gli ambienti smettono di essere SORGENTI e diventano DESTINAZIONI,
# usa-e-getta. La fonte e' una sola: questo repository.
#
# PERCHE' NON I SYMLINK
# Sarebbe piu' rapido e sarebbe l'errore di prima con un altro nome: un ambiente
# symlinkato non esercita l'installer — cioe' l'unico pezzo che si e' rotto, due volte —
# e per giunta puo' scrivere all'indietro nel repo (e' cosi' che un artefatto di build
# e' finito committato).
#
set -euo pipefail

RADICE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
QUALE="${1:-}"

case "$QUALE" in
	j5)       BERSAGLI=("$HOME/albo-j5") ;;
	j6)       BERSAGLI=("$HOME/albo-j6") ;;
	entrambi) BERSAGLI=("$HOME/albo-j5" "$HOME/albo-j6") ;;
	*)
		echo "Uso: bash bin/dev-deploy.sh j5|j6|entrambi" >&2
		exit 2
		;;
esac

# Il pacchetto si costruisce UNA volta: lo stesso identico zip va su entrambi gli ambienti.
# Costruirne uno per ambiente rimetterebbe in piedi il problema che stiamo togliendo.
echo "▸ Costruisco il pacchetto…"
bash "$RADICE/bin/build.sh"

VERSIONE="$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' \
	"$RADICE/src/alboonline.xml" | head -1)"
PACCHETTO="$RADICE/dist/com_alboonline-$VERSIONE.zip"

[ -f "$PACCHETTO" ] || { echo "ERRORE: pacchetto non trovato: $PACCHETTO" >&2; exit 1; }

for BERSAGLIO in "${BERSAGLI[@]}"; do
	NOME="$(basename "$BERSAGLIO")"
	echo ""
	echo "▸ Installo su $NOME…"

	[ -d "$BERSAGLIO" ] || { echo "ERRORE: ambiente inesistente: $BERSAGLIO" >&2; exit 1; }

	# ddev non vede fuori dal progetto, e risolve il progetto dalla CARTELLA CORRENTE:
	# lanciare i comandi da un'altra cartella li manda sul Joomla sbagliato (e' costato
	# 10 FAIL falsi in un collaudo). Quindi: il pacchetto entra nel progetto, e si esegue
	# stando dentro.
	#
	# Se il bersaglio E' l'albero da cui costruiamo (oggi J5 fa da sorgente E da ambiente),
	# il pacchetto e' gia' al suo posto: copiarlo su se stesso e' un errore. E' una stortura
	# del doppio ambiente, non del deploy — sparisce quando il repo sara' l'unica fonte.
	PROPRIO="$BERSAGLIO/dist/com_alboonline-$VERSIONE.zip"

	if [ "$PACCHETTO" != "$PROPRIO" ]; then
		mkdir -p "$BERSAGLIO/dist"
		cp "$PACCHETTO" "$PROPRIO"
		TEMPORANEO=1
	else
		TEMPORANEO=0
	fi

	(
		cd "$BERSAGLIO"
		ddev exec php cli/joomla.php extension:install \
			--path="dist/com_alboonline-$VERSIONE.zip" 2>&1 | grep -iE "success|error|warning" || true
	)

	[ "$TEMPORANEO" -eq 1 ] && rm -rf "$BERSAGLIO/dist"

	INSTALLATA="$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' \
		"$BERSAGLIO/administrator/components/com_alboonline/alboonline.xml" | head -1)"

	if [ "$INSTALLATA" != "$VERSIONE" ]; then
		echo "  ✗ $NOME dice di avere la $INSTALLATA, non la $VERSIONE: l'installazione non ha preso." >&2
		exit 1
	fi

	echo "  ✓ $NOME: versione $INSTALLATA"
done

echo ""
echo "▸ Fatto. Il collaudo va lanciato DENTRO l'ambiente, o ddev prende il progetto sbagliato:"
echo "    cd ~/albo-j5 && bash scripts/collaudo-auto.sh"
echo "    cd ~/albo-j6     && ALBO_BASE=https://albo-j6.ddev.site bash scripts/collaudo-auto.sh"
