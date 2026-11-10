<?php
/**
 * Mette l'avviso di licenza in testa ai file PHP delle dipendenze che non ce l'hanno.
 *
 * PERCHE' SERVE. La JED (e il suo JED Checker, regola "GPL") vuole un avviso di licenza in
 * OGNI file PHP del pacchetto, dipendenze comprese. I nostri file ce l'hanno; quelli di mPDF,
 * psr/log, psr/http-message, myclabs/deep-copy e i file generati da Composer no: la licenza
 * la dichiarano una volta sola, nel composer.json e nel file LICENSE del pacchetto. La 1.2.2
 * e' stata respinta per questo («GPL license notice missing in PHP headers», 10/11/2026).
 *
 * COSA FA. Per ogni file PHP sotto vendor/ che non nomina gia' una licenza, inserisce subito
 * dopo l'apertura <?php un commento con la licenza DICHIARATA da quel pacchetto in
 * vendor/composer/installed.json e il percorso del suo file di licenza. Solo un commento:
 * il codice delle librerie non cambia. I file sotto vendor/composer/ e vendor/autoload.php
 * li genera Composer, che e' MIT (vendor/composer/LICENSE).
 *
 * Idempotente: un file che ha gia' l'avviso (nostro o originale) non si tocca. Fallisce se un
 * pacchetto dichiara una licenza che qui non conosciamo: meglio fermare il build che scrivere
 * un avviso sbagliato in un prodotto che installa una PA.
 *
 * USO: php bin/avviso-licenze-vendor.php <cartella vendor>
 *
 * @copyright (C) 2026 Protocolli Creativi
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

$vendor = rtrim($argv[1] ?? '', '/');

if ($vendor === '' || !is_file($vendor . '/composer/installed.json')) {
    fwrite(STDERR, "ERRORE: indicare la cartella vendor (manca composer/installed.json)\n");
    exit(1);
}

// Nome esteso di ciascuna licenza: e' quello che la regola GPL di JED Checker riconosce.
$nomi = [
    'GPL-2.0-only'     => 'GNU General Public License version 2 (GPL-2.0-only)',
    'GPL-2.0-or-later' => 'GNU General Public License version 2 or later (GPL-2.0-or-later)',
    'LGPL-2.1-only'    => 'GNU Lesser General Public License version 2.1 (LGPL-2.1-only)',
    'MIT'              => 'MIT License (MIT)',
    'BSD-3-Clause'     => 'BSD 3-Clause License (BSD-3-Clause)',
];

// Un file "ha gia' la licenza" se la nomina per esteso, come la regola GPL di JED Checker
// pretende (GPL, o licenze compatibili come MIT e BSD). La sigla da sola non basta: due file
// di mPDF dicono soltanto «License: LGPL» e JED Checker li segnala lo stesso.
$giaPresente = '/General\s+Public\s+License|MIT\s+License|\blicense\b.{0,80}\bMIT\b|BSD\s+(?:\d-Clause\s+)?License|Apache\s+License/i';

// Quei file dichiarano una licenza loro (LGPL, codice entrato in mPDF da altri progetti):
// l'avviso ripete per esteso quella, non la licenza del pacchetto.
$siglaLgpl = '/(?:@license|License:)\s*LGPL\b/i';

$installati = json_decode((string) file_get_contents($vendor . '/composer/installed.json'), true);
$pacchetti  = $installati['packages'] ?? $installati;
$radici     = [];

foreach ($pacchetti as $p) {
    $dir = realpath($vendor . '/composer/' . ($p['install-path'] ?? ''));

    if ($dir === false) {
        continue;
    }

    $licenze = (array) ($p['license'] ?? []);

    foreach ($licenze as $l) {
        if (!isset($nomi[$l])) {
            fwrite(STDERR, "ERRORE: {$p['name']} dichiara la licenza «{$l}», che questo script non conosce.\n");
            exit(1);
        }
    }

    if (!$licenze) {
        fwrite(STDERR, "ERRORE: {$p['name']} non dichiara una licenza.\n");
        exit(1);
    }

    $file = '';

    foreach (['LICENSE', 'LICENSE.txt', 'LICENSE.md', 'COPYING', 'COPYING.txt'] as $nome) {
        if (is_file($dir . '/' . $nome)) {
            $file = $nome;
            break;
        }
    }

    $radici[$dir] = [
        'nome'    => $p['name'],
        'licenza' => implode(' or ', array_map(static fn ($l) => $nomi[$l], $licenze)),
        'file'    => $file !== '' ? 'vendor/' . $p['name'] . '/' . $file : 'vendor/' . $p['name'] . '/composer.json',
    ];
}

// Composer: vendor/composer/* e vendor/autoload.php
$composer = ['nome' => 'composer/composer (file generati)', 'licenza' => $nomi['MIT'], 'file' => 'vendor/composer/LICENSE'];
$base     = realpath($vendor);
$aggiunti = 0;
$giaOk    = 0;

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));

foreach ($it as $f) {
    $percorso = $f->getPathname();

    if (substr($percorso, -4) !== '.php') {
        continue;
    }

    $testo = (string) file_get_contents($percorso);

    if (preg_match($giaPresente, $testo)) {
        $giaOk++;
        continue;
    }

    $chi = null;

    foreach ($radici as $dir => $info) {
        if (str_starts_with($percorso, $dir . '/')) {
            $chi = $info;
            break;
        }
    }

    if ($chi === null) {
        if (str_starts_with($percorso, $base . '/composer/') || $percorso === $base . '/autoload.php') {
            $chi = $composer;
        } else {
            fwrite(STDERR, "ERRORE: non so a quale pacchetto appartiene " . substr($percorso, strlen($base) + 1) . "\n");
            exit(1);
        }
    }

    if (preg_match($siglaLgpl, $testo)) {
        $chi = ['nome' => $chi['nome'], 'licenza' => 'GNU Lesser General Public License (LGPL), as declared below in this file', 'file' => $chi['file']];
    }

    if (!str_starts_with($testo, '<?php')) {
        fwrite(STDERR, "ERRORE: non comincia con <?php, non so dove mettere l'avviso: " . substr($percorso, strlen($base) + 1) . "\n");
        exit(1);
    }

    $avviso = "\n/*\n"
        . " * This file is part of {$chi['nome']}, distributed with Albo Pretorio Online.\n"
        . " * @license {$chi['licenza']}; see {$chi['file']}\n"
        . " * Avviso aggiunto in fase di build: il codice della libreria non e' modificato.\n"
        . " */";

    file_put_contents($percorso, '<?php' . $avviso . substr($testo, 5));
    $aggiunti++;
}

echo "  avviso di licenza aggiunto a $aggiunti file PHP delle dipendenze ($giaOk lo avevano gia')\n";
