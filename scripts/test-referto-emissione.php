<?php
// Test M18: emissione del referto CONGELATO tramite RefertoGenerator. Dev-only.
// Uso: ddev exec php scripts/test-referto-emissione.php <attoId>
const _JEXEC = 1;
require_once \dirname(__DIR__) . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Pcrt\Component\Alboonline\Administrator\Helper\RefertoGenerator;

$container = Factory::getContainer();
$container->alias('session', 'session.cli')
    ->alias('JSession', 'session.cli')
    ->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
$app = $container->get(\Joomla\Console\Application::class);
Factory::$application = $app;
$app->createExtensionNamespaceMap();

$db = $container->get(\Joomla\Database\DatabaseInterface::class);
$db->setQuery('SELECT id FROM #__users ORDER BY id ASC LIMIT 1');
$app->loadIdentity(new User((int) $db->loadResult()));
$app->getLanguage()->load('com_alboonline', JPATH_ADMINISTRATOR);

$attoId = (int) ($argv[1] ?? 0);

if ($attoId <= 0) {
    echo "Uso: php scripts/test-referto-emissione.php <attoId>\n";
    exit(1);
}

$gen = new RefertoGenerator($db);

echo "=== EMISSIONE referto per atto #$attoId ===\n";

try {
    $id = $gen->emit($attoId, 0, 'System Task', RefertoGenerator::TIPO_DEFISSIONE);
} catch (\Throwable $e) {
    echo "ERRORE: " . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
}

if ($id === 0) {
    echo "emit() ha restituito 0 (referto disattivato o gia' esistente).\n";
} else {
    echo "Referto emesso: id=$id\n";
}

// Rileggi la riga piu' recente per questo atto.
$db->setQuery(
    'SELECT * FROM #__alboonline_referti WHERE atto_id = ' . $attoId . ' ORDER BY id DESC LIMIT 1'
);
$r = $db->loadObject();

if (!$r) {
    echo "Nessuna riga referto trovata.\n";
    exit(1);
}

echo "\n--- Riga referto ---\n";
foreach (['id','tipo_documento','numero_repertorio','tipo_atto','ente_emanante','responsabile',
          'publish_up','publish_down','rettifica_di','rettifica_repertorio','stato',
          'pdf_filename','pdf_hash','pdf_size','emesso_at','emesso_by'] as $c) {
    printf("  %-20s = %s\n", $c, $r->$c ?? 'NULL');
}
if (!empty($r->note_rettifica)) {
    printf("  %-20s = %s\n", 'note_rettifica', $r->note_rettifica);
}

// Verifica del file su disco + integrita' hash.
$path = JPATH_ROOT . '/media/com_alboonline/referti/' . $r->pdf_filename;
echo "\n--- Verifica file ---\n";
echo "  path: $path\n";

if (!is_file($path)) {
    echo "  ESITO: FALLITO — file mancante sul disco\n";
    exit(1);
}

$diskHash = hash_file('sha256', $path);
$diskSize = filesize($path);
$magic    = substr(file_get_contents($path, false, null, 0, 5), 0, 5);

printf("  magic PDF     : %s (%s)\n", $magic, $magic === '%PDF-' ? 'OK' : 'NON-PDF');
printf("  hash su disco : %s\n", $diskHash);
printf("  hash nel DB   : %s (%s)\n", $r->pdf_hash, $diskHash === $r->pdf_hash ? 'COINCIDE' : 'DIVERSO');
printf("  size su disco : %d (%s)\n", $diskSize, (int) $diskSize === (int) $r->pdf_size ? 'COINCIDE' : 'DIVERSO');

// Byte-stability: rileggere il file due volte da' lo stesso hash (documento congelato).
$again = hash_file('sha256', $path);
printf("  ri-lettura    : %s (%s)\n", $again, $again === $diskHash ? 'STABILE' : 'INSTABILE');

echo "\n--- Idempotenza (seconda emit) ---\n";
$id2 = $gen->emit($attoId, 0, 'System Task', RefertoGenerator::TIPO_DEFISSIONE);
echo "  seconda emit() -> " . $id2 . ($id2 === 0 ? "  (OK: nessun doppione)\n" : "  (ATTENZIONE: doppione!)\n");

$db->setQuery('SELECT COUNT(*) FROM #__alboonline_referti WHERE atto_id = ' . $attoId
    . " AND tipo_documento = 'defissione'");
echo "  referti 'defissione' per l'atto: " . (int) $db->loadResult() . "\n";
