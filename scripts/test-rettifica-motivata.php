<?php
// Test M11: note_rettifica obbligatoria SOLO quando rettifica_di e' valorizzato. Dev-only.
// Uso: ddev exec php scripts/test-rettifica-motivata.php
const _JEXEC = 1;
require_once \dirname(__DIR__) . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
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

Form::addFormPath(JPATH_ADMINISTRATOR . '/components/com_alboonline/forms');

function valida(array $data): array
{
    // Nuova istanza a ogni giro: validate() accumula gli errori.
    $form = new Form('atto');
    $form->loadFile('atto');
    $ok = $form->validate($data);
    $errs = array_map(static fn($e) => $e->getMessage(), $form->getErrors());

    return [$ok, $errs];
}

$scenari = [
    'A) rettifica (di=8) SENZA motivazione -> deve FALLIRE' => ['rettifica_di' => 8, 'note_rettifica' => ''],
    'B) rettifica (di=8) CON motivazione   -> deve PASSARE' => ['rettifica_di' => 8, 'note_rettifica' => 'Correzione importo per errore materiale'],
    'C) atto ordinario (di=0) senza note   -> deve PASSARE' => ['rettifica_di' => 0, 'note_rettifica' => ''],
    'D) rettifica con note di soli spazi    -> deve FALLIRE' => ['rettifica_di' => 8, 'note_rettifica' => '    '],
];

// Diagnostica: la classe regola e' risolvibile?
echo 'Classe regola esiste: '
    . (class_exists('Pcrt\\Component\\Alboonline\\Administrator\\Rule\\RettificamotivataRule') ? 'SI' : 'NO') . "\n";
echo 'FormHelper::loadRuleType: '
    . (\Joomla\CMS\Form\FormHelper::loadRuleType('rettificamotivata') ? 'TROVATA' : 'NON trovata') . "\n\n";

foreach ($scenari as $titolo => $data) {
    // Dati minimi validi per gli altri campi obbligatori del form.
    $base = [
        'id' => 0, 'titolo' => 'Atto di test', 'catid' => 0,
        'tipo_atto' => 'generico', 'stato_atto' => 0,
    ];
    [$ok, $errs] = valida(array_merge($base, $data));

    // Isola la SOLA regola della motivazione (il messaggio cita "art. 3" in IT ed EN).
    $motivErr = array_filter($errs, static fn($m) => str_contains($m, 'art. 3'));
    echo $titolo . "\n    -> regola motivazione: " . (empty($motivErr) ? 'PASSA' : 'FALLISCE') . "\n";
}
