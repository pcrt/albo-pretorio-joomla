<?php
/**
 * Dev seed — popola l'ambiente di TEST con dati di prova per com_alboonline.
 * NON fa parte del pacchetto distribuibile (cartella scripts/ esclusa dal build).
 *
 * Uso:  ddev exec php scripts/dev-seed.php
 * Crea (idempotente sulla categoria): 1 categoria "Determine" + alcuni atti in stati diversi.
 */

const _JEXEC = 1;

if (file_exists(\dirname(__DIR__) . '/defines.php')) {
    require_once \dirname(__DIR__) . '/defines.php';
}
if (!\defined('_JDEFINES')) {
    \define('JPATH_BASE', \dirname(__DIR__));
    require_once JPATH_BASE . '/includes/defines.php';
}
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\CMS\Table\Table;

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
$adminId = (int) $db->loadResult();
$app->loadIdentity(new User($adminId));

// 1) Categoria "Determine" (idempotente)
$db->setQuery("SELECT id FROM #__categories WHERE extension='com_alboonline' AND alias='determine' LIMIT 1");
$catid = (int) $db->loadResult();
if (!$catid) {
    $cat = Table::getInstance('Category');
    $cat->extension = 'com_alboonline';
    $cat->title = 'Determine';
    $cat->alias = 'determine';
    $cat->description = 'Categoria di prova';
    $cat->published = 1; $cat->access = 1;
    $cat->params = '{}'; $cat->metadata = '{}'; $cat->language = '*';
    $cat->setLocation(1, 'last-child');
    $cat->check(); $cat->store(); $cat->rebuildPath($cat->id);
    $catid = (int) $cat->id;
    echo "Categoria 'Determine' creata (id={$catid}).\n";
} else {
    echo "Categoria 'Determine' gia' presente (id={$catid}).\n";
}

// 2) Atti di prova — suffisso univoco per non collidere con alias esistenti
$suffix = date('ymd-His');
$component = $app->bootComponent('com_alboonline');
$mvc = $component->getMVCFactory();

function nuovoAtto($mvc, $catid, $titolo, $alias, $oggetto): int
{
    $model = $mvc->createModel('Atto', 'Administrator', ['ignore_request' => true]);
    $ok = $model->save([
        'id' => 0, 'catid' => $catid, 'titolo' => $titolo, 'alias' => $alias,
        'oggetto' => $oggetto, 'descrizione' => '<p>Atto di prova (dev seed).</p>',
        'tipo_atto' => 'generico', 'ente_emanante' => 'Ente di prova',
        'responsabile' => 'Amministratore', 'stato_atto' => 0, 'state' => 0,
        'data_atto' => date('Y-m-d'), 'robots' => '', 'visibilita_home' => 'nessuna',
        'diritto_oblio' => 0, 'access' => 1, 'language' => '*',
    ]);
    if (!$ok) { echo "  ERRORE save: " . $model->getError() . "\n"; return 0; }
    return (int) $model->getState('atto.id');
}

// a) atto in Bozza
$idBozza = nuovoAtto($mvc, $catid, "Bozza di prova {$suffix}", "bozza-prova-{$suffix}", 'Determina lasciata in bozza.');
echo "Atto in Bozza creato (id={$idBozza}).\n";

// b) atto Pubblicato (Bozza -> InAttesa via publishAtto)
$idPub = nuovoAtto($mvc, $catid, "Pubblicato di prova {$suffix}", "pubblicato-prova-{$suffix}", 'Determina pubblicata per test workflow.');
$pubModel = $mvc->createModel('Atto', 'Administrator', ['ignore_request' => true]);
if ($pubModel->publishAtto($idPub)) {
    echo "Atto Pubblicato (InAttesa/buffer) creato (id={$idPub}).\n";
} else {
    echo "  ERRORE publishAtto: " . $pubModel->getError() . "\n";
}

echo "\nSeed completato. Categoria={$catid}, atti creati: {$idBozza}, {$idPub}.\n";
