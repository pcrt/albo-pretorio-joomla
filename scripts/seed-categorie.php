<?php
/**
 * Crea le categorie di com_alboonline e assegna gli atti demo. Dev-only.
 *
 * Uso: ddev exec php scripts/seed-categorie.php
 *
 * Perché esiste: seed-demo.php inserisce gli atti direttamente nel DB con catid=0,
 * mentre il form ESIGE la categoria (required). I dati demo erano quindi diversi da
 * quelli che produrrebbe un ente reale, e rendevano impossibile collaudare il filtro
 * categoria (A03) e l'albero ARIA (B02, che ha bisogno di una sotto-categoria).
 *
 * Le categorie si creano con il modello di com_categories (non con INSERT a mano):
 * servono l'asset ACL e il nested set corretti, che una INSERT grezza non produce.
 * Idempotente: se una categoria esiste già, la riusa.
 */

const _JEXEC = 1;
require_once \dirname(__DIR__) . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

$container = Factory::getContainer();
$container->alias('session', 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
$app = $container->get(\Joomla\Console\Application::class);
Factory::$application = $app;
$app->createExtensionNamespaceMap();
$app->getLanguage()->load('com_categories', JPATH_ADMINISTRATOR);

$db = $container->get(DatabaseInterface::class);

// L'id dell'amministratore NON si cabla: è diverso fra J5 e J6. Lo si chiede al database.
$adminId = (int) $db->setQuery('SELECT id FROM #__users ORDER BY id ASC LIMIT 1')->loadResult();
Factory::getApplication()->loadIdentity(Factory::getUser($adminId));

// La factory si prende dal componente avviato: costruirla a mano non le dà il
// database né il logger che i modelli del core si aspettano.
$factory = $app->bootComponent('com_categories')->getMVCFactory();

/** Restituisce l'id della categoria (creandola se manca). */
function categoria($factory, DatabaseInterface $db, string $titolo, int $parent = 1, int $adminId = 0): int
{
    $q = $db->getQuery(true)
        ->select('id')
        ->from($db->quoteName('#__categories'))
        ->where($db->quoteName('extension') . ' = ' . $db->quote('com_alboonline'))
        ->where($db->quoteName('title') . ' = ' . $db->quote($titolo));
    $id = (int) $db->setQuery($q)->loadResult();

    if ($id > 0) {
        echo "  = esiste già: $titolo (id $id)\n";

        return $id;
    }

    /** @var \Joomla\Component\Categories\Administrator\Model\CategoryModel $model */
    $model = $factory->createModel('Category', 'Administrator', ['ignore_request' => true]);

    $ok = $model->save([
        'id'         => 0,
        'title'      => $titolo,
        'parent_id'  => $parent,
        'extension'  => 'com_alboonline',
        'published'  => 1,
        'language'   => '*',
        'access'     => 1,
        'params'     => [],
        'metadata'   => [],
        'created_user_id' => $adminId,
    ]);

    if (!$ok) {
        exit("  ! errore su '$titolo': " . $model->getError() . "\n");
    }

    $id = (int) $model->getState('category.id');
    echo "  + creata: $titolo (id $id)\n";

    return $id;
}

echo "Categorie:\n";
$determine = categoria($factory, $db, 'Determine', 1, $adminId);
$bandi     = categoria($factory, $db, 'Bandi e gare', 1, $adminId);
$delibere  = categoria($factory, $db, 'Delibere', 1, $adminId);
// Sotto-categoria: serve a collaudare i tasti → e ← dell'albero ARIA (B02).
$giunta    = categoria($factory, $db, 'Delibere di Giunta', $delibere, $adminId);

// Assegnazione degli atti PER TIPOLOGIA, non per id: gli id cambiano a ogni seed e sono
// diversi fra l'installazione J5 e quella J6. Un mapping cablato funzionava solo qui.
$perTipo = [
    'determina' => $determine,
    'bando'     => $bandi,
    'delibera'  => $giunta,     // sotto-categoria: serve a collaudare i tasti freccia (B02)
];

echo "\nAtti:\n";

foreach ($perTipo as $tipo => $catId) {
    $q = $db->getQuery(true)
        ->update($db->quoteName('#__alboonline_atti'))
        ->set($db->quoteName('catid') . ' = ' . (int) $catId)
        ->where($db->quoteName('tipo_atto') . ' = ' . $db->quote($tipo));
    $db->setQuery($q)->execute();
    echo "  tipo «$tipo» → categoria $catId (" . $db->getAffectedRows() . " atti)\n";
}

// Tutto il resto (tipi non mappati) finisce comunque in una categoria: un atto senza
// categoria non è ciò che il form produrrebbe (catid è required).
$q = $db->getQuery(true)
    ->update($db->quoteName('#__alboonline_atti'))
    ->set($db->quoteName('catid') . ' = ' . (int) $determine)
    ->where($db->quoteName('catid') . ' = 0');
$db->setQuery($q)->execute();
echo "  altri tipi → categoria $determine (" . $db->getAffectedRows() . " atti)\n";

echo "\nFatto.\n";
