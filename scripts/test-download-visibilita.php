<?php
/**
 * Test di sicurezza: chi puo' scaricare l'allegato di un atto, per stato. Dev-only.
 *
 * Riproduce e blinda la falla corretta nel DownloadController: un atto "In Attesa"
 * (stato_atto=1, ancora invisibile nel resto del frontend) veniva trattato come
 * "Pubblicato" e i suoi allegati erano scaricabili da un visitatore anonimo che ne
 * indovinasse l'id numerico, aggirando la finestra di pubblicazione.
 *
 * La regola vive in AlboonlineHelper::downloadDenial() — funzione PURA — quindi qui la
 * esercitiamo direttamente su tutte le combinazioni di stato/permessi, SENZA montare
 * un'app HTTP ne toccare il database. Gira ovunque ci sia PHP:
 *   php scripts/test-download-visibilita.php
 */

\define('_JEXEC', 1);

require_once \dirname(__DIR__)
	. '/src/administrator/components/com_alboonline/src/Helper/AlboonlineHelper.php';

use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper as H;

$pass = 0;
$fail = 0;
$ok   = function (string $t) use (&$pass) { echo "  PASS  $t\n"; $pass++; };
$ko   = function (string $t) use (&$fail) { echo "  FAIL  $t\n"; $fail++; };
$vero = fn(bool $cond, string $t) => $cond ? $ok($t) : $ko($t);

// Livelli di accesso: guest ha [1,5] (Public+Guest), l'allegato "riservato" chiede il 6.
$PUBLIC   = [1, 5];
$RISERVATO_LV = 6;

echo "=== SEC · un atto In Attesa NON e' scaricabile dal pubblico\n";
// Guest: niente core.edit, niente permesso archivi.
$vero(
	H::downloadDenial(H::STATO_IN_ATTESA, 1, false, false, false, $PUBLIC) === 403,
	'guest su In Attesa -> 403 (era il buco: prima passava)'
);
// Redattore con core.edit: anteprima backend consentita.
$vero(
	H::downloadDenial(H::STATO_IN_ATTESA, 1, false, true, false, $PUBLIC) === null,
	'redattore (core.edit) su In Attesa -> consentito (anteprima)'
);

echo "\n=== Bozza · stesso confine dell'In Attesa\n";
$vero(H::downloadDenial(H::STATO_BOZZA, 1, false, false, false, $PUBLIC) === 403, 'guest su Bozza -> 403');
$vero(H::downloadDenial(H::STATO_BOZZA, 1, false, true, false, $PUBLIC) === null, 'redattore su Bozza -> consentito');

echo "\n=== Pubblicato · pubblico si', ma rispettando il livello di accesso\n";
$vero(H::downloadDenial(H::STATO_PUBBLICATO, 1, false, false, false, $PUBLIC) === null, 'guest su Pubblicato access=1 -> consentito');
$vero(H::downloadDenial(H::STATO_PUBBLICATO, $RISERVATO_LV, false, false, false, $PUBLIC) === 403, 'guest su Pubblicato riservato -> 403');
$vero(H::downloadDenial(H::STATO_PUBBLICATO, $RISERVATO_LV, false, false, false, [1, 5, $RISERVATO_LV]) === null, 'utente col livello giusto su Pubblicato riservato -> consentito');

echo "\n=== Archiviato · serve il permesso dedicato; l'oblio nasconde tutto\n";
$vero(H::downloadDenial(H::STATO_ARCHIVIATO, 1, false, false, false, $PUBLIC) === 403, 'guest su Archiviato -> 403');
$vero(H::downloadDenial(H::STATO_ARCHIVIATO, 1, false, false, true, $PUBLIC) === null, 'con alboonline.download.archived su Archiviato -> consentito');
$vero(H::downloadDenial(H::STATO_ARCHIVIATO, 1, true, true, true, $PUBLIC) === 404, 'Archiviato in oblio -> 404 anche col permesso archivi');

echo "\n=== Cestinato · non esiste per nessuno\n";
$vero(H::downloadDenial(H::STATO_CESTINATO, 1, false, true, true, $PUBLIC) === 404, 'Cestinato -> 404 anche con tutti i permessi');

echo "\n================================\n";
echo "  PASS: $pass   FAIL: $fail\n";
echo "================================\n";

exit($fail === 0 ? 0 : 1);
