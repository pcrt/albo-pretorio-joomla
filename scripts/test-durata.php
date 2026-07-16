<?php
// Test S2-T2: durata di pubblicazione per tipologia di atto. Dev-only.
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

// Categoria
$db->setQuery("SELECT id FROM #__categories WHERE extension='com_alboonline' AND alias='determine' LIMIT 1");
$catid = (int) $db->loadResult();

$component = $app->bootComponent('com_alboonline');
$mvc = $component->getMVCFactory();
$suffix = date('His');

function pubblica($mvc, $db, $catid, $tipo, $suffix) {
    $model = $mvc->createModel('Atto', 'Administrator', ['ignore_request' => true]);
    $ok = $model->save([
        'id'=>0,'catid'=>$catid,'titolo'=>"Test durata $tipo $suffix",'alias'=>"test-durata-$tipo-$suffix",
        'oggetto'=>'Test durata per tipologia','descrizione'=>'<p>test</p>','tipo_atto'=>$tipo,
        'ente_emanante'=>'Ente','responsabile'=>'Admin','stato_atto'=>0,'state'=>0,
        'data_atto'=>date('Y-m-d'),'robots'=>'','visibilita_home'=>'nessuna','diritto_oblio'=>0,'access'=>1,'language'=>'*',
    ]);
    if (!$ok) { echo "  save FALLITO ($tipo): ".$model->getError()."\n"; return; }
    $id = (int) $model->getState('atto.id');
    $pm = $mvc->createModel('Atto', 'Administrator', ['ignore_request' => true]);
    if (!$pm->publishAtto($id)) { echo "  publish FALLITO ($tipo): ".$pm->getError()."\n"; return; }
    $db->setQuery('SELECT publish_up, publish_down, DATEDIFF(publish_down, publish_up) AS giorni FROM #__alboonline_atti WHERE id='.$id);
    $r = $db->loadObject();
    echo sprintf("  tipo=%-10s  atto #%d  finestra = %d giorni  (%s → %s)\n", $tipo, $id, $r->giorni, $r->publish_up, $r->publish_down);
}

echo "=== Durata per tipologia (atteso: bando=30, determina=15, generico=15 default) ===\n";
pubblica($mvc, $db, $catid, 'bando', $suffix);
pubblica($mvc, $db, $catid, 'determina', $suffix);
pubblica($mvc, $db, $catid, 'generico', $suffix);
