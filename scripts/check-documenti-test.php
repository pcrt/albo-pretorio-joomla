<?php
/**
 * Passa il set di documenti di prova agli helper reali del componente e stampa
 * cosa ciascuno provoca. Dev-only.
 *
 * Uso: ddev exec php scripts/check-documenti-test.php
 *
 * Serve a due cose: (a) confermare che i file di prova sollecitino davvero il
 * controllo per cui esistono; (b) riverificarli dopo ogni modifica agli helper.
 */

const _JEXEC = 1;
require_once \dirname(__DIR__) . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Pcrt\Component\Alboonline\Administrator\Helper\FirmaVerifier;
use Pcrt\Component\Alboonline\Administrator\Helper\FormatoPdf;

$c = Factory::getContainer();
$c->alias('session', 'session.cli')->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
$app = $c->get(\Joomla\Console\Application::class);
Factory::$application = $app;
$app->createExtensionNamespaceMap();

$dir = \dirname(__DIR__) . '/docs/collaudo/documenti-test';

$firma   = new FirmaVerifier();
$formato = new FormatoPdf();

foreach (glob($dir . '/*') as $file) {
    $nome = basename($file);

    if ($nome === 'README.md') {
        continue;
    }

    echo "\n=== $nome\n";

    $f = $firma->verify($file);
    echo '  firma   : ' . ($f->ha_firma ? ($f->formato . ' · esito=' . $f->esito) : 'nessuna') . "\n";

    if (!empty($f->firmatari)) {
        foreach ($f->firmatari as $s) {
            echo '            firmatario=' . ($s->cn ?: '—')
                . ' cert_nella_finestra=' . var_export($s->nella_finestra ?? null, true) . "\n";
        }
    }

    if (!empty($f->marca_temporale)) {
        echo '            marca temporale: presente=' . var_export($f->marca_temporale->presente, true)
            . ' verificata=' . var_export($f->marca_temporale->verificata, true) . "\n";
    }

    if (str_ends_with(strtolower($nome), '.pdf')) {
        $r = $formato->analyze($file);
        echo '  formato : pdfa=' . ($r->pdfa_livello ?: 'no')
            . ' · ha_testo=' . var_export($r->ha_testo, true)
            . ' · avvisi=' . ($r->avvisi ? implode(',', $r->avvisi) : '—') . "\n";
    }
}

echo "\n";
