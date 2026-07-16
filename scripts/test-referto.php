<?php
// Test S2-T1: render del Referto di pubblicazione. Dev-only.
// Uso: ddev exec php scripts/test-referto.php <attoId>
const _JEXEC = 1;
require_once \dirname(__DIR__) . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;

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
$app->getInput()->set('id', $attoId);

$component = $app->bootComponent('com_alboonline');
$view = $component->getMVCFactory()->createView('Referto', 'Administrator', 'Html', ['base_path' => JPATH_ADMINISTRATOR . '/components/com_alboonline']);

ob_start();
try {
    $view->display();
    $html = ob_get_clean();
} catch (\Throwable $e) {
    ob_end_clean();
    echo "ERRORE render: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Lunghezza HTML: " . strlen($html) . " byte\n";
foreach (['REFERTO DI PUBBLICAZIONE', 'Numero di repertorio', 'Si attesta', 'window.print'] as $needle) {
    echo (strpos($html, $needle) !== false ? "  ✓ trovato: " : "  ✗ MANCANTE: ") . $needle . "\n";
}
// estrai il numero di repertorio mostrato
if (preg_match('#<strong>([0-9]{4}/[0-9]+)</strong>#', $html, $m)) {
    echo "  → repertorio nel documento: {$m[1]}\n";
}
