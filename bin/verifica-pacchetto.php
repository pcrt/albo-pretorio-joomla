<?php
/**
 * Il guardiano del pacchetto. Lo chiama bin/build.sh dopo aver composto il pacchetto e
 * PRIMA di chiudere lo zip: se qualcosa non torna, il pacchetto non si fa.
 *
 * Uso: php bin/verifica-pacchetto.php <radice-sorgente> <cartella-pacchetto>
 *
 * PERCHE' ESISTE
 * Fino alla 1.0.0 il pacchetto si componeva con una lista di cartelle scritta a mano, e
 * dalla lista si e' caduti due volte:
 *   - layouts/firma/esito.php non era dichiarato nel manifest -> l'esito della verifica
 *     firma non compariva su NESSUNA installazione vera, in silenzio;
 *   - i manifest dei plugin pretendevano una cartella language/ che nel repo non esiste.
 * Nessuno dei due si vedeva in sviluppo: gli ambienti J5/J6 sono alberi sorgente, dove i
 * file mancanti ci sono per forza. Li ha scoperti un'ispezione, non una prova.
 *
 * I TRE CONTROLLI SONO DIVERSI E SERVONO TUTTI
 *   A. c'e' nel sorgente ma non nel pacchetto      -> qualcuno se n'e' dimenticato
 *   B. il manifest lo promette ma non c'e'         -> il build e' sbagliato   (= lingue plugin)
 *   C. c'e' nel pacchetto ma nessun manifest lo installa -> Joomla lo ignora  (= layouts)
 *
 * Il C sembra il piu' strano ed e' quello che serviva davvero: il difetto della 1.0.0 era
 * proprio quello. Il file finiva nello zip; era il manifest a non dichiararlo, e Joomla
 * installa solo cio' che il manifest dichiara. Con i soli controlli A e B la build sarebbe
 * rimasta verde e l'esito della verifica firma muto.
 *
 * NOTA SUL METODO DEL CONTROLLO A
 * Si confronta per CONTENUTO (impronta), non per percorso: il build rimonta i file in
 * posizioni diverse da quelle del repo (site/, administrator/languages/, media/…), quindi
 * un confronto sui percorsi direbbe solo che la forma del repo non e' quella del pacchetto
 * — cosa che sappiamo. Quello che vogliamo sapere e' un'altra: quel file, da qualche parte,
 * e' finito nel pacchetto?
 */

$radice  = rtrim($argv[1] ?? '', '/');
$lavoro  = rtrim($argv[2] ?? '', '/');

if (!is_dir($radice) || !is_dir($lavoro)) {
    fwrite(STDERR, "Uso: php bin/verifica-pacchetto.php <radice-sorgente> <cartella-pacchetto>\n");
    exit(2);
}

/**
 * Il sorgente spedibile e' src/, tutto qui: e' il senso di src/. Prima questo elenco doveva
 * enumerare sei cartelle sparse piu' sette schemi per i .ini, che stavano nelle cartelle di
 * lingua del CORE mescolati a quelli di Joomla. Ogni voce era un posto da cui dimenticarsi.
 */
$fonti = ['src'];

/** @var string[] Nessuno: dentro src/ non c'e' niente da pescare con un glob. */
$fontiGlob = [];

/**
 * File che il sorgente ha e il pacchetto NON deve avere, indicati per PERCORSO.
 * Non vengono nemmeno copiati dal build: qui servono solo a non farli scambiare per
 * dimenticanze. Ogni voce e' una scelta e ha il suo motivo scritto accanto.
 */
$nonSpedito = [
    // Provenienza di vendor/ (mPDF): dicono QUALE versione ridistribuiamo. Servono a noi e a
    // chi verifica la licenza, non all'ente che installa.
    'src/administrator/components/com_alboonline/composer.json',
    'src/administrator/components/com_alboonline/composer.lock',
];

/**
 * File che il build POTA dal pacchetto, indicati per NOME (glob, a qualsiasi profondita').
 * L'elenco sta in bin/non-spedito.txt e lo legge anche build.sh: una regola sola, un posto
 * solo. Se le due cose divergessero, il guardiano segnalerebbe come dimenticato cio' che il
 * build butta apposta — e a quel punto smetteremmo di dargli retta, che e' il modo in cui
 * un controllo muore.
 */
$nonSpeditoNomi = [];

foreach (file(__DIR__ . '/non-spedito.txt', FILE_IGNORE_NEW_LINES) as $riga) {
    $riga = trim(explode('#', $riga, 2)[0]);

    if ($riga !== '') {
        $nonSpeditoNomi[] = $riga;
    }
}

/** Dati prodotti a runtime dall'albo: non sono sorgente, non si spediscono. */
$ignora = [
    'src/media/com_alboonline/allegati',
    'src/media/com_alboonline/referti',
];

$errori = [];
$contati = 0;

// ─────────────────────────────────────────────────────────────────────────────
// Le impronte di TUTTO cio' che e' finito nel pacchetto.
// ─────────────────────────────────────────────────────────────────────────────
$impronteLavoro = [];
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($lavoro, FilesystemIterator::SKIP_DOTS)
);

foreach ($it as $f) {
    if ($f->isFile()) {
        $impronteLavoro[md5_file($f->getPathname())] = true;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// CONTROLLO A — c'e' nel sorgente ma non nel pacchetto?
// ─────────────────────────────────────────────────────────────────────────────
$daControllare = [];

foreach ($fonti as $cartella) {
    $piena = $radice . '/' . $cartella;

    if (!is_dir($piena)) {
        continue;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($piena, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($it as $f) {
        if ($f->isFile()) {
            $daControllare[] = substr($f->getPathname(), \strlen($radice) + 1);
        }
    }
}

foreach ($fontiGlob as $schema) {
    foreach (glob($radice . '/' . $schema) as $f) {
        $daControllare[] = substr($f, \strlen($radice) + 1);
    }
}

foreach ($daControllare as $rel) {
    foreach ($ignora as $i) {
        if (str_starts_with($rel, $i . '/')) {
            continue 2;
        }
    }

    if (\in_array($rel, $nonSpedito, true)) {
        continue;
    }

    // Potato dal build per nome: basta che UN pezzo del percorso combaci (il glob .git*
    // deve scartare sia .gitignore sia tutto quello che sta dentro .github/).
    foreach ($nonSpeditoNomi as $glob) {
        foreach (explode('/', $rel) as $pezzo) {
            if (fnmatch($glob, $pezzo)) {
                continue 3;
            }
        }
    }

    $contati++;

    if (!isset($impronteLavoro[md5_file($radice . '/' . $rel)])) {
        $errori[] = "[A] nel sorgente ma NON nel pacchetto: $rel";
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// CONTROLLO B — il manifest lo promette ma nel pacchetto non c'e'?
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Verifica una sezione del manifest: ogni <folder>/<filename>/<language> dichiarato
 * deve esistere davvero, sotto la base che l'attributo folder= indica.
 */
function controllaSezione(?SimpleXMLElement $sezione, string $lavoro, string $etichetta, array &$errori): void
{
    if ($sezione === null) {
        return;
    }

    $base = trim((string) ($sezione['folder'] ?? ''));
    $base = $base === '' ? $lavoro : $lavoro . '/' . $base;

    foreach ($sezione->children() as $voce) {
        $nome = trim((string) $voce);

        if ($nome === '') {
            continue;
        }

        if (!file_exists($base . '/' . $nome)) {
            // Le graffe non sono un vezzo: PHP ammette i byte \x80-\xff nei nomi di
            // variabile, quindi «$nome» verrebbe letto come la variabile «$nome»» —
            // che non esiste, e il messaggio uscirebbe senza il nome del file, cioe'
            // senza l'unica informazione per cui esiste.
            $errori[] = "[B] {$etichetta} dichiara «{$nome}» ma nel pacchetto non c'e'";
        }
    }
}

/**
 * Registra, per ogni sezione, la BASE (l'attributo folder=) e i NOMI che dichiara.
 * Serve al controllo C: senza sapere cosa il manifest dichiara, non si puo' sapere
 * cosa il manifest IGNORA.
 *
 * @param array $basi          base (relativa al pacchetto) => nomi dichiarati
 * @param array $percorsiBase  le basi stesse: sono legittime anche se nessuno le dichiara
 *                             (es. site/languages non e' dentro <files folder="site">,
 *                             ma e' la base di <languages>)
 */
function raccogli(?SimpleXMLElement $sezione, string $prefisso, array &$basi, array &$percorsiBase): void
{
    if ($sezione === null) {
        return;
    }

    $folder = trim((string) ($sezione['folder'] ?? ''));
    $base   = trim($prefisso . ($folder !== '' ? $folder : ''), '/');

    if ($base !== '') {
        $percorsiBase[] = $base;
    }

    foreach ($sezione->children() as $voce) {
        $nome = trim((string) $voce);

        if ($nome === '') {
            continue;
        }

        // <language tag="it-IT">it-IT/com_alboonline.ini</language> -> il nome di primo
        // livello sotto la base e' "it-IT".
        $basi[$base][] = explode('/', $nome)[0];
    }
}

/**
 * CONTROLLO C — c'e' nel pacchetto ma nessun manifest lo installa.
 * Guarda solo il PRIMO livello sotto ogni base: e' li' che si dichiara, ed e' li' che si
 * dimentica. Se una cartella e' dichiarata, Joomla se la porta dietro tutta.
 */
function controllaIgnorati(string $lavoro, array $basi, array $percorsiBase, array $ammessiInCima, array &$errori): void
{
    foreach ($basi as $base => $dichiarati) {
        $piena = $base === '' ? $lavoro : $lavoro . '/' . $base;

        if (!is_dir($piena)) {
            continue;
        }

        foreach (scandir($piena) as $voce) {
            if ($voce === '.' || $voce === '..') {
                continue;
            }

            $rel = trim($base . '/' . $voce, '/');

            if (\in_array($voce, $dichiarati, true)
                || \in_array($rel, $ammessiInCima, true)) {
                continue;
            }

            // Legittimo anche se e' una base, o il CONTENITORE di una base: «administrator»
            // in cima al pacchetto non lo dichiara nessuno, ma dentro c'e'
            // administrator/components/com_alboonline, che e' la base di <administration>.
            $contenitore = false;

            foreach ($percorsiBase as $b) {
                if ($b === $rel || str_starts_with($b, $rel . '/')) {
                    $contenitore = true;
                    break;
                }
            }

            if ($contenitore) {
                continue;
            }

            $errori[] = "[C] nel pacchetto ma NESSUN manifest lo installa: {$rel}";
        }
    }
}

$basi         = [];
$percorsiBase = [];

$manifest = @simplexml_load_file($lavoro . '/alboonline.xml');

if ($manifest === false) {
    $errori[] = '[B] il manifest del componente non e\' leggibile nel pacchetto';
} else {
    controllaSezione($manifest->files ?? null, $lavoro, 'il manifest (sito)', $errori);
    controllaSezione($manifest->media ?? null, $lavoro, 'il manifest (media)', $errori);
    controllaSezione($manifest->languages ?? null, $lavoro, 'il manifest (lingue sito)', $errori);

    raccogli($manifest->files ?? null, '', $basi, $percorsiBase);
    raccogli($manifest->media ?? null, '', $basi, $percorsiBase);
    raccogli($manifest->languages ?? null, '', $basi, $percorsiBase);

    if (isset($manifest->administration)) {
        controllaSezione($manifest->administration->files ?? null, $lavoro, 'il manifest (amministrazione)', $errori);
        controllaSezione($manifest->administration->languages ?? null, $lavoro, 'il manifest (lingue amministrazione)', $errori);

        raccogli($manifest->administration->files ?? null, '', $basi, $percorsiBase);
        raccogli($manifest->administration->languages ?? null, '', $basi, $percorsiBase);
    }
}

foreach (['task', 'content'] as $gruppo) {
    $rel  = 'plugins/' . $gruppo . '/alboonline';
    $base = $lavoro . '/' . $rel;
    $mp   = @simplexml_load_file($base . '/alboonline.xml');

    if ($mp === false) {
        $errori[] = "[B] il manifest di plg_{$gruppo}_alboonline non e' leggibile nel pacchetto";
        continue;
    }

    controllaSezione($mp->files ?? null, $base, "plg_{$gruppo}_alboonline", $errori);
    controllaSezione($mp->languages ?? null, $base, "plg_{$gruppo}_alboonline (lingue)", $errori);

    raccogli($mp->files ?? null, $rel . '/', $basi, $percorsiBase);
    raccogli($mp->languages ?? null, $rel . '/', $basi, $percorsiBase);

    // Il manifest del plugin e' il plugin stesso: Joomla lo installa comunque.
    $basi[$rel][] = 'alboonline.xml';
}

// In cima al pacchetto stanno cose che nessuna sezione dichiara ma che Joomla usa lo stesso.
$basi[''][] = 'alboonline.xml';                                   // il manifest
$basi[''][] = (string) ($manifest->scriptfile ?? 'script.php');   // <scriptfile>
$basi[''][] = 'plugins';                                          // <plugins folder="plugins">

controllaIgnorati($lavoro, $basi, $percorsiBase, [], $errori);

// ─────────────────────────────────────────────────────────────────────────────
if ($errori) {
    fwrite(STDERR, "\nIl pacchetto NON e' completo:\n\n");

    foreach ($errori as $e) {
        fwrite(STDERR, "  ✗ $e\n");
    }

    fwrite(STDERR, "\n  [A] Esiste nel sorgente ma non e' finito nel pacchetto: o il build deve\n"
        . "      copiarlo, o va aggiunto a bin/non-spedito.txt (per nome) o all'elenco\n"
        . "      \$nonSpedito di questo script (per percorso) — CON IL MOTIVO.\n"
        . "  [B] Il manifest promette un file che il build non ha messo: l'installazione\n"
        . "      fallirebbe, o installerebbe un componente monco.\n"
        . "  [C] E' nel pacchetto ma nessun manifest lo dichiara, quindi Joomla NON lo\n"
        . "      installa: il file viaggia nello zip e non arriva mai a destinazione, senza\n"
        . "      che nessuno protesti. E' cosi' che nella 1.0.0 l'esito della verifica firma\n"
        . "      era muto su ogni installazione vera. Va dichiarato nel manifest.\n\n");
    exit(1);
}

echo "  $contati file del sorgente, tutti spediti; i manifest li installano tutti\n";
exit(0);
