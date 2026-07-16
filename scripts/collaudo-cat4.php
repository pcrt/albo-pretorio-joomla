<?php
/**
 * Prepara (e poi ripristina) il test CAT-4 del collaudo: cancellare una categoria
 * che contiene un atto PUBBLICATO e BLINDATO. Dev-only.
 *
 * Uso:
 *   ddev exec php scripts/collaudo-cat4.php prepara     → crea la categoria di prova
 *                                                          e ci sposta il Bando (atto 22)
 *   ddev exec php scripts/collaudo-cat4.php ripristina  → rimette il Bando in «Bandi e gare»
 *
 * La categoria originale dell'atto viene salvata in un file di appoggio, così il
 * ripristino non dipende dalla memoria di nessuno.
 */

const _JEXEC = 1;
require_once \dirname(__DIR__) . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

$container = Factory::getContainer();
$container->alias('session', 'session.cli')
    ->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
$app = $container->get(\Joomla\Console\Application::class);
Factory::$application = $app;
$app->createExtensionNamespaceMap();
$app->loadIdentity(Factory::getUser(690));

$db      = $container->get(DatabaseInterface::class);
$azione  = $argv[1] ?? '';
$attoId  = 22;                                   // Bando di gara 3/2026 — pubblicato, blindato
$memoria = __DIR__ . '/.cat4-catid-originale';

function catid(DatabaseInterface $db, int $attoId): int
{
    return (int) $db->setQuery(
        $db->getQuery(true)
            ->select('catid')
            ->from($db->quoteName('#__alboonline_atti'))
            ->where('id = ' . $attoId)
    )->loadResult();
}

function sposta(DatabaseInterface $db, int $attoId, int $catId): void
{
    $db->setQuery(
        $db->getQuery(true)
            ->update($db->quoteName('#__alboonline_atti'))
            ->set($db->quoteName('catid') . ' = ' . $catId)
            ->where('id = ' . $attoId)
    )->execute();
}

if ($azione === 'prepara') {
    file_put_contents($memoria, (string) catid($db, $attoId));

    $q = $db->getQuery(true)
        ->select('id')
        ->from($db->quoteName('#__categories'))
        ->where($db->quoteName('extension') . ' = ' . $db->quote('com_alboonline'))
        ->where($db->quoteName('title') . ' = ' . $db->quote('Prova cancellazione'));
    $catId = (int) $db->setQuery($q)->loadResult();

    if (!$catId) {
        $model = $app->bootComponent('com_categories')->getMVCFactory()
            ->createModel('Category', 'Administrator', ['ignore_request' => true]);

        if (!$model->save([
            'id'        => 0,
            'title'     => 'Prova cancellazione',
            'parent_id' => 1,
            'extension' => 'com_alboonline',
            'published' => 1,
            'language'  => '*',
            'access'    => 1,
            'params'    => [],
            'metadata'  => [],
            'created_user_id' => 690,
        ])) {
            exit('Errore: ' . $model->getError() . "\n");
        }

        $catId = (int) $model->getState('category.id');
    }

    sposta($db, $attoId, $catId);

    echo "Categoria «Prova cancellazione» (id $catId).\n";
    echo "Atto $attoId (Bando di gara 3/2026, pubblicato e blindato) spostato lì dentro.\n";
    echo "Categoria originale salvata: " . file_get_contents($memoria) . "\n";

    exit(0);
}

if ($azione === 'ripristina') {
    if (!is_file($memoria)) {
        exit("Non trovo la categoria originale: esegui prima 'prepara'.\n");
    }

    $originale = (int) file_get_contents($memoria);

    // Se la categoria originale è stata cancellata nel frattempo, non si inventa nulla.
    $esiste = (int) $db->setQuery(
        $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__categories'))
            ->where('id = ' . $originale)
    )->loadResult();

    if (!$esiste) {
        exit("La categoria originale (id $originale) non esiste più. Ricreala prima.\n");
    }

    sposta($db, $attoId, $originale);
    unlink($memoria);

    echo "Atto $attoId rimesso nella categoria $originale.\n";

    exit(0);
}

echo "Uso: collaudo-cat4.php prepara|ripristina\n";
echo 'Categoria attuale dell\'atto ' . $attoId . ': ' . catid($db, $attoId) . "\n";
