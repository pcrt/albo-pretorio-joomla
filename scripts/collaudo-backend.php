<?php
/**
 * Collaudo dei PRESIDI del backend, guidando i modelli reali. Dev-only.
 *
 * Gira su ENTRAMBE le installazioni (un solo codice, J5 e J6):
 *   ddev exec php scripts/collaudo-backend.php
 *
 * Non è un test di unità: esercita il componente come lo esercita un funzionario, e verifica
 * che i divieti siano davvero divieti. Serve soprattutto per il port su Joomla 6, dove le
 * rotture non si vedono col grep (lezione S1: pagina bianca, trait mancante, install fallita).
 */

const _JEXEC = 1;
require_once \dirname(__DIR__) . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

// L'applicazione web le definisce; la CLI no. Senza, getForm() esplode.
\define('JPATH_COMPONENT', JPATH_ADMINISTRATOR . '/components/com_alboonline');
\define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_COMPONENT);

use Joomla\CMS\Factory;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseInterface;

$c = Factory::getContainer();
$c->alias('session', 'session.cli')->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
$app = $c->get(\Joomla\Console\Application::class);
Factory::$application = $app;
$app->createExtensionNamespaceMap();

$db      = $c->get(DatabaseInterface::class);
$adminId = (int) $db->setQuery('SELECT id FROM #__users ORDER BY id ASC LIMIT 1')->loadResult();
$app->loadIdentity(Factory::getUser($adminId));
$app->getLanguage()->load('com_alboonline', JPATH_ADMINISTRATOR);

$atto  = fn() => $app->bootComponent('com_alboonline')->getMVCFactory()
    ->createModel('Atto', 'Administrator', ['ignore_request' => true]);
$atti  = fn() => $app->bootComponent('com_alboonline')->getMVCFactory()
    ->createModel('Atti', 'Administrator', ['ignore_request' => true]);
$cat   = fn() => $app->bootComponent('com_categories')->getMVCFactory()
    ->createModel('Category', 'Administrator', ['ignore_request' => true]);

$pass = 0;
$fail = 0;
$ok   = function (string $t) use (&$pass) { echo "  PASS  $t\n"; $pass++; };
$ko   = function (string $t) use (&$fail) { echo "  FAIL  $t\n"; $fail++; };
$vero = fn(bool $cond, string $t) => $cond ? $ok($t) : $ko($t);

echo 'Joomla ' . (new Version())->getShortVersion() . ' · PHP ' . PHP_VERSION . " · admin #$adminId\n\n";

// ---------------------------------------------------------------- dati di lavoro
$bozza = (int) $db->setQuery('SELECT id FROM #__alboonline_atti WHERE stato_atto = 0 LIMIT 1')->loadResult();
$blind = (int) $db->setQuery('SELECT id FROM #__alboonline_atti WHERE locked_at IS NOT NULL LIMIT 1')->loadResult();

if (!$bozza || !$blind) {
    exit("Servono almeno una bozza e un atto blindato: esegui prima scripts/seed-demo.php\n");
}

echo "=== M31 · un atto senza documento non si pubblica\n";
$db->setQuery("DELETE FROM #__alboonline_allegati WHERE atto_id = $bozza")->execute();
$m = $atto();
$vero(!$m->publishAtto($bozza), 'pubblicazione senza allegati RIFIUTATA');

echo "\n=== M32 · pubblica → annulla → ripubblica (il ciclo deve chiudersi)\n";
// Rimetto un allegato fittizio, altrimenti M31 (giustamente) blocca tutto.
$file = 'collaudo-backend.pdf';
file_put_contents(JPATH_ROOT . '/media/com_alboonline/allegati/' . $file, "%PDF-1.4\n%collaudo\n");
$db->setQuery("INSERT INTO #__alboonline_allegati (atto_id, titolo, filename, original_name, size, ordering, created)
               VALUES ($bozza, 'Collaudo', '$file', 'collaudo.pdf', 20, 0, UTC_TIMESTAMP())")->execute();

$vero($atto()->publishAtto($bozza), 'pubblicazione con documento riuscita');
$vero($atto()->cancelPublish($bozza), 'annullamento riuscito');
$r = $db->setQuery("SELECT publish_up, publish_down, progressivo_protocollo FROM #__alboonline_atti WHERE id = $bozza")->loadObject();
$vero($r->publish_up === null && $r->publish_down === null, 'annullamento: publish_up e publish_down azzerate');
$vero((int) $r->progressivo_protocollo > 0, 'annullamento: protocollo conservato (nessun buco nel repertorio)');
$vero($atto()->publishAtto($bozza), 'RIpubblicazione riuscita');

echo "\n=== M17/M20 · un atto blindato non si cestina, e non mente sul successo\n";
$m = $atto();

try {
    $pks = [$blind];
    $m->publish($pks, -2);
    $ko('cestinare un atto blindato: CONSENTITO');
} catch (\RuntimeException $e) {
    $ok('cestinare un atto blindato: rifiutato con eccezione (niente falso successo)');
}

$statoDopo = (int) $db->setQuery("SELECT state FROM #__alboonline_atti WHERE id = $blind")->loadResult();
$vero($statoDopo !== -2, 'atto blindato: lo stato NON è cambiato');

echo "\n=== M34 · sull'atto blindato la data di fine è di sola lettura\n";
$m = $atto();
$m->setState('atto.id', $blind);
$form = $m->getForm([], true);
$vero($form->getFieldAttribute('publish_down', 'disabled') === 'true', 'publish_down disabilitata sull\'atto blindato');
$m2 = $atto();
$m2->setState('atto.id', $bozza);
$vero($m2->getForm([], true)->getFieldAttribute('publish_down', 'disabled') === null, 'publish_down modificabile in bozza');

echo "\n=== M33 · ente emanante obbligatorio e precompilato\n";
$m = $atto();
$m->setState('atto.id', 0);
$vero($m->getForm([], true)->getFieldAttribute('ente_emanante', 'required') === 'true', 'ente_emanante è required');
$rm = new ReflectionMethod($m, 'loadFormData');
$rm->setAccessible(true);
$d = $rm->invoke($m);
$vero(!empty($d->ente_emanante), 'atto nuovo: ente precompilato dal default (' . ($d->ente_emanante ?? '-') . ')');

echo "\n=== M23/M24 · l'elenco admin mostra gli archiviati e nasconde solo il cestino\n";
$arch = (int) $db->setQuery('SELECT id FROM #__alboonline_atti WHERE stato_atto = 3 AND state = 2 LIMIT 1')->loadResult();
$elenco = function ($filtro) use ($atti) {
    $m = $atti();
    $m->setState('filter.stato_atto', $filtro);
    $m->setState('list.limit', 200);

    return array_map(fn($i) => (int) $i->id, $m->getItems() ?: []);
};
$default = $elenco('');
$vero($arch > 0 && \in_array($arch, $default, true), "archiviato consultabile #$arch presente nella vista predefinita");

$db->setQuery("UPDATE #__alboonline_atti SET state = -2 WHERE id = $bozza")->execute();
$vero(!\in_array($bozza, $elenco(''), true), 'atto cestinato ASSENTE dalla vista predefinita');
$vero(\in_array($bozza, $elenco('-2'), true), 'atto cestinato presente filtrando «Cestinato» (voce che prima non trovava nulla)');
$db->setQuery("UPDATE #__alboonline_atti SET state = 0 WHERE id = $bozza")->execute();

echo "\n=== M30 · non si cancella una categoria che contiene atti, e l'orfano si ripara\n";
$mc = $cat();
$mc->save([
    'id' => 0, 'title' => 'Collaudo backend', 'parent_id' => 1, 'extension' => 'com_alboonline',
    'published' => 1, 'language' => '*', 'access' => 1, 'params' => [], 'metadata' => [],
    'created_user_id' => $adminId,
]);
$catId = (int) $mc->getState('category.id');
$db->setQuery("UPDATE #__categories SET published = -2 WHERE id = $catId")->execute();

$catPrec = (int) $db->setQuery("SELECT catid FROM #__alboonline_atti WHERE id = $blind")->loadResult();
$db->setQuery("UPDATE #__alboonline_atti SET catid = $catId WHERE id = $blind")->execute();

$pks = [$catId];
$esito = $cat()->delete($pks);
$viva  = (int) $db->setQuery("SELECT COUNT(*) FROM #__categories WHERE id = $catId")->loadResult();
$vero($esito === false && $viva === 1, 'categoria CON atti: cancellazione rifiutata dal plugin content');

$db->setQuery("UPDATE #__alboonline_atti SET catid = $catPrec WHERE id = $blind")->execute();
$pks = [$catId];
$vero($cat()->delete($pks) === true, 'categoria VUOTA: si cancella (il presidio non blocca l\'uso normale)');

// Riparazione di un orfano su atto BLINDATO
$db->setQuery("UPDATE #__alboonline_atti SET catid = 99999 WHERE id = $blind")->execute();
$m = $atto();
$vero($m->isCategoriaOrfana(99999), 'orfano rilevato');
$vero($atto()->riassegnaCategoria($blind, $catPrec), 'orfano riparato su atto BLINDATO (unica modifica ammessa)');
$m = $atto();
$vero(!$m->riassegnaCategoria($blind, $catPrec), 'atto sano: la riparazione è rifiutata (non è una scorciatoia)');

echo "\n=== S3 · firma e formato dei documenti\n";
$dir = \dirname(__DIR__) . '/docs/collaudo/documenti-test';

if (is_dir($dir)) {
    $fv = new \Pcrt\Component\Alboonline\Administrator\Helper\FirmaVerifier();
    $fp = new \Pcrt\Component\Alboonline\Administrator\Helper\FormatoPdf();

    $rep = $fv->verify($dir . '/04-firmato-cades.p7m');
    $vero(($rep->esito ?? '') === 'integra', 'CAdES valido → firma integra');
    $rep = $fv->verify($dir . '/05-firmato-cades-manomesso.p7m');
    $vero(($rep->esito ?? '') === 'compromessa', 'CAdES manomesso → firma compromessa');
    $an = $fp->analyze($dir . '/07-pdfa.pdf');
    $vero(($an->pdfa_livello ?? '') === 'PDF/A-1B', 'PDF/A riconosciuto');
} else {
    echo "  (documenti di prova non presenti: copia docs/collaudo/documenti-test/)\n";
}

// Pulizia
$db->setQuery("DELETE FROM #__alboonline_allegati WHERE filename = '$file'")->execute();
@unlink(JPATH_ROOT . '/media/com_alboonline/allegati/' . $file);

echo "\n================================\n";
echo "  PASS: $pass   FAIL: $fail\n";
echo "================================\n";

exit($fail === 0 ? 0 : 1);
