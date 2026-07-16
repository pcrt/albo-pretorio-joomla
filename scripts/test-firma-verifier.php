<?php
// Collaudo dell'helper FirmaVerifier (S3-T1, fase 1) sui file di test. Dev-only.
// Uso: ddev exec php scripts/test-firma-verifier.php
const _JEXEC = 1;
require_once \dirname(__DIR__) . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Pcrt\Component\Alboonline\Administrator\Helper\FirmaVerifier;

$c = Factory::getContainer();
$c->alias('session', 'session.cli')->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
$app = $c->get(\Joomla\Console\Application::class);
Factory::$application = $app;
$app->createExtensionNamespaceMap();

$dir = __DIR__ . '/firma-test';

// prepara un .p7m manomesso al volo (se manca)
$tamp = $dir . '/atto9-tampered.p7m';
if (is_file($dir . '/atto9.pdf.p7m') && !is_file($tamp)) {
    $b = file_get_contents($dir . '/atto9.pdf.p7m');
    $b[intval(strlen($b) / 2)] = chr(ord($b[intval(strlen($b) / 2)]) ^ 0xFF);
    file_put_contents($tamp, $b);
}

$casi = [
    'CAdES valido (.p7m)'      => $dir . '/atto9.pdf.p7m',
    'CAdES manomesso'          => $tamp,
    'PDF non firmato'          => $dir . '/atto9.pdf',
    'PAdES firmato (.pdf)'     => $dir . '/sample-pades.pdf',
];

$v = new FirmaVerifier();

foreach ($casi as $nome => $path) {
    echo "=== $nome ===\n";
    if (!is_file($path)) { echo "  (file assente, salto)\n\n"; continue; }
    $r = $v->verify($path);
    echo "  formato       : {$r->formato}\n";
    echo "  esito         : {$r->esito}\n";
    echo "  integrità     : " . var_export($r->integrita, true) . "\n";
    if ($r->contenuto_hash) echo "  contenuto hash: " . substr($r->contenuto_hash, 0, 20) . "…\n";
    if ($r->marca_temporale) echo "  marca temp.   : presente=" . var_export($r->marca_temporale->presente, true) . "\n";
    foreach ($r->firmatari as $f) {
        echo "  firmatario    : CN={$f->cn}  CF={$f->codice_fiscale}  O={$f->organizzazione}  nella_finestra=" . var_export($f->nella_finestra, true) . "\n";
    }
    echo "\n";
}

@unlink($tamp);
