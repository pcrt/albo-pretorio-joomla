<?php
// Dev-only: azzera i dati Albo e ricrea un set curato di atti in tutti gli stati
// utili, GIA' blindati/archiviati (niente attesa del buffer), con PDF realistici,
// referti (date sensate) e un referto firmato. Uso: ddev exec php scripts/seed-demo.php
const _JEXEC = 1;
require_once \dirname(__DIR__) . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Pcrt\Component\Alboonline\Administrator\Helper\RefertoGenerator;

require_once JPATH_ADMINISTRATOR . '/components/com_alboonline/vendor/autoload.php';

$c = Factory::getContainer();
$c->alias('session', 'session.cli')->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
$app = $c->get(\Joomla\Console\Application::class);
Factory::$application = $app;
$app->createExtensionNamespaceMap();
$db = $c->get(\Joomla\Database\DatabaseInterface::class);
// L'id dell'amministratore NON si cabla: è diverso fra l'installazione J5 e quella J6
// (690 vs 295). Lo si chiede al database, così lo stesso script gira su entrambe.
$adminId = (int) $db->setQuery('SELECT id FROM #__users ORDER BY id ASC LIMIT 1')->loadResult();

if ($adminId > 0) {
    $app->loadIdentity(new User($adminId));
}
$app->getLanguage()->load('com_alboonline', JPATH_ADMINISTRATOR);

$ALL = JPATH_ROOT . '/media/com_alboonline/allegati';
$REF = JPATH_ROOT . '/media/com_alboonline/referti';

// --- 1) PULIZIA -----------------------------------------------------------
foreach (['alboonline_referti', 'alboonline_allegati', 'alboonline_atti', 'alboonline_log', 'alboonline_contatori'] as $t) {
    $db->setQuery('DELETE FROM ' . $db->quoteName('#__' . $t))->execute();
}
foreach ([$ALL, $REF] as $d) {
    foreach (glob($d . '/*') as $f) {
        if (!in_array(basename($f), ['.htaccess', 'index.html'], true)) {
            @unlink($f);
        }
    }
}
echo "Pulizia completata.\n";

// --- Helper: genera un PDF realistico e ritorna [filename, hash, size] -----
$makePdf = function (string $titolo, string $bodyHtml) use ($ALL): array {
    $mpdf = new \Mpdf\Mpdf(['format' => 'A4', 'tempDir' => sys_get_temp_dir()]);
    $mpdf->SetTitle($titolo);
    $mpdf->WriteHTML('<div style="font-family:serif;font-size:11pt;line-height:1.5">'
        . '<h2 style="text-align:center;margin:0">COMUNE DI ESEMPIO</h2>'
        . '<h3 style="text-align:center;text-decoration:underline">' . $titolo . '</h3>'
        . $bodyHtml
        . '<p style="margin-top:40px">Il Responsabile del Servizio<br><em>(documento firmato digitalmente)</em></p></div>');
    $bytes = $mpdf->Output('', 'S');
    $hash  = hash('sha256', $bytes);
    file_put_contents($ALL . '/' . $hash . '.pdf', $bytes);

    return [$hash . '.pdf', $hash, \strlen($bytes)];
};

// --- Helper: inserisce un atto e (se serve) il suo allegato sigillato ------
$mkAtto = function (array $f) use ($db, $makePdf): int {
    $o = (object) array_merge([
        'catid' => 0, 'alias' => '', 'oggetto' => '', 'ente_emanante' => '',
        'numero_protocollo' => null, 'anno_protocollo' => null, 'progressivo_protocollo' => null,
        'data_atto' => null, 'stato_atto' => 0, 'state' => 0,
        'publish_up' => null, 'publish_down' => null, 'buffer_expires_at' => null, 'locked_at' => null,
        'rettifica_di' => null, 'rettificato_da' => null, 'diritto_oblio' => 0, 'note_rettifica' => null,
        'created' => '2026-06-01 08:00:00', 'created_by' => $adminId, 'access' => 1, 'language' => '*',
    ], $f);
    unset($o->_pdf, $o->_log_responsabile);
    $o->alias = $o->alias ?: strtolower(preg_replace('/[^a-z0-9]+/i', '-', $o->titolo));
    $db->insertObject('#__alboonline_atti', $o, 'id');
    $id = (int) $o->id;

    // Allegato PDF (sigillato con hash se l'atto è blindato/archiviato)
    if (!empty($f['_pdf'])) {
        [$titolo, $body] = $f['_pdf'];
        [$fn, $hash, $size] = $makePdf($titolo, $body);
        $sealed = ((int) $o->stato_atto >= 2); // pubblicato o archiviato = sigillato
        $al = (object) [
            'atto_id' => $id, 'titolo' => $titolo, 'filename' => $fn,
            'original_name' => preg_replace('/[^A-Za-z0-9]+/', '-', $titolo) . '.pdf',
            'hash' => $sealed ? $hash : null, 'size' => $size, 'mime' => 'application/pdf',
            'ordering' => 0, 'created' => $o->created, 'created_by' => $adminId,
        ];
        $db->insertObject('#__alboonline_allegati', $al);
    }

    // Log 'published' col nome del responsabile (per il referto)
    if (!empty($f['_log_responsabile']) && $o->publish_up) {
        $lg = (object) [
            'atto_id' => $id, 'azione' => 'published', 'stato_prima' => 0, 'stato_dopo' => 1,
            'user_id' => $adminId, 'user_name' => $f['_log_responsabile'], 'note' => 'Pubblicazione',
            'created' => $o->publish_up,
        ];
        $db->insertObject('#__alboonline_log', $lg);
    }

    return $id;
};

// Date (UTC nel DB): periodo ATTIVO (in corso) e periodo CHIUSO (archiviato)
$UP_ATT = '2026-07-06 08:00:00'; $DOWN_ATT_15 = '2026-07-21 08:00:00'; $DOWN_ATT_30 = '2026-08-05 08:00:00';
$LOCK_ATT = '2026-07-06 09:00:00';
$UP_ARC = '2026-06-12 08:00:00'; $DOWN_ARC = '2026-06-27 08:00:00'; $LOCK_ARC = '2026-06-12 09:00:00';

// --- 2) GLI ATTI ----------------------------------------------------------
$resp = 'Ing. Mario Rossi';

// A) BOZZA (in lavorazione) — solo admin
$mkAtto([
    'titolo' => 'Determina n. 200/2026 (in bozza)', 'tipo_atto' => 'determina', 'stato_atto' => 0, 'state' => 0,
    'data_atto' => '2026-07-09', 'oggetto' => 'Affidamento fornitura cancelleria uffici comunali',
    '_pdf' => ['Determina n. 200/2026', '<p><strong>OGGETTO:</strong> Affidamento fornitura cancelleria. <em>Atto in bozza, non ancora pubblicato.</em></p>'],
]);

// B) PUBBLICATO + blindato (determina 15gg) — visibile nel frontend
$mkAtto([
    'titolo' => 'Determina n. 145/2026', 'tipo_atto' => 'determina', 'stato_atto' => 2, 'state' => 1,
    'numero_protocollo' => '2026/0001', 'anno_protocollo' => 2026, 'progressivo_protocollo' => 1,
    'data_atto' => '2026-07-03', 'oggetto' => 'Manutenzione del verde pubblico 2026 - impegno di spesa',
    'publish_up' => $UP_ATT, 'publish_down' => $DOWN_ATT_15, 'buffer_expires_at' => $LOCK_ATT, 'locked_at' => $LOCK_ATT,
    'segnatura_protocollo' => 'AOO-COM-0012345/2026', 'segnatura_data' => '2026-07-03',
    '_pdf' => ['Determina n. 145/2026', '<p><strong>OGGETTO:</strong> Manutenzione del verde pubblico. Impegno di spesa di &euro; 24.500,00.</p>'],
]);

// C) PUBBLICATO + blindato (bando 30gg) — durata piu' lunga
$mkAtto([
    'titolo' => 'Bando di gara n. 3/2026', 'tipo_atto' => 'bando', 'stato_atto' => 2, 'state' => 1,
    'numero_protocollo' => '2026/0002', 'anno_protocollo' => 2026, 'progressivo_protocollo' => 2,
    'data_atto' => '2026-07-04', 'oggetto' => 'Procedura aperta per servizi di refezione scolastica',
    'publish_up' => $UP_ATT, 'publish_down' => $DOWN_ATT_30, 'buffer_expires_at' => $LOCK_ATT, 'locked_at' => $LOCK_ATT,
    '_pdf' => ['Bando di gara n. 3/2026', '<p><strong>OGGETTO:</strong> Procedura aperta per il servizio di refezione scolastica. Termine 30 giorni.</p>'],
]);

// D) ORIGINALE poi rettificato — pubblicato + blindato
$idOrig = $mkAtto([
    'titolo' => 'Determina n. 88/2026', 'tipo_atto' => 'determina', 'stato_atto' => 2, 'state' => 1,
    'numero_protocollo' => '2026/0003', 'anno_protocollo' => 2026, 'progressivo_protocollo' => 3,
    'data_atto' => '2026-07-02', 'oggetto' => 'Aggiudicazione servizio di pulizia - importo iniziale',
    'publish_up' => $UP_ATT, 'publish_down' => $DOWN_ATT_15, 'buffer_expires_at' => $LOCK_ATT, 'locked_at' => $LOCK_ATT,
    '_pdf' => ['Determina n. 88/2026', '<p><strong>OGGETTO:</strong> Aggiudicazione del servizio di pulizia.</p>'],
]);

// E) RETTIFICA dell'originale — pubblicato + blindato, con motivazione (M10/M11)
$idRett = $mkAtto([
    'titolo' => 'Determina n. 92/2026 (rettifica della 88/2026)', 'tipo_atto' => 'determina', 'stato_atto' => 2, 'state' => 1,
    'numero_protocollo' => '2026/0004', 'anno_protocollo' => 2026, 'progressivo_protocollo' => 4,
    'data_atto' => '2026-07-05', 'oggetto' => 'Rettifica della determina 88/2026 - correzione importo',
    'publish_up' => $UP_ATT, 'publish_down' => $DOWN_ATT_15, 'buffer_expires_at' => $LOCK_ATT, 'locked_at' => $LOCK_ATT,
    'rettifica_di' => $idOrig, 'note_rettifica' => 'Correzione dell\'importo di aggiudicazione, indicato per mero errore materiale nella determina originaria.',
    '_pdf' => ['Determina n. 92/2026', '<p><strong>OGGETTO:</strong> Rettifica della determina 88/2026 per correzione dell\'importo.</p>'],
]);
$db->setQuery('UPDATE ' . $db->quoteName('#__alboonline_atti') . ' SET ' . $db->quoteName('rettificato_da') . ' = ' . (int) $idRett . ' WHERE id = ' . (int) $idOrig)->execute();

// F) ARCHIVIATO senza oblio (con referto) — visibile in Archivio Atti
$idArc = $mkAtto([
    'titolo' => 'Delibera di Giunta n. 40/2026', 'tipo_atto' => 'delibera', 'stato_atto' => 3, 'state' => 2,
    'numero_protocollo' => '2026/0005', 'anno_protocollo' => 2026, 'progressivo_protocollo' => 5,
    'data_atto' => '2026-06-10', 'oggetto' => 'Approvazione programma manifestazioni estive',
    'publish_up' => $UP_ARC, 'publish_down' => $DOWN_ARC, 'buffer_expires_at' => $LOCK_ARC, 'locked_at' => $LOCK_ARC,
    'segnatura_protocollo' => 'AOO-COM-0009876/2026', 'segnatura_data' => '2026-06-10',
    '_log_responsabile' => $resp,
    '_pdf' => ['Delibera di Giunta n. 40/2026', '<p><strong>OGGETTO:</strong> Approvazione del programma delle manifestazioni estive 2026.</p>'],
]);

// G) ARCHIVIATO con DIRITTO ALL'OBLIO — invisibile al pubblico
$idObl = $mkAtto([
    'titolo' => 'Determina n. 12/2026', 'tipo_atto' => 'determina', 'stato_atto' => 3, 'state' => 0, 'diritto_oblio' => 1,
    'numero_protocollo' => '2026/0006', 'anno_protocollo' => 2026, 'progressivo_protocollo' => 6,
    'data_atto' => '2026-06-08', 'oggetto' => 'Concessione contributo economico (dati personali)',
    'publish_up' => $UP_ARC, 'publish_down' => $DOWN_ARC, 'buffer_expires_at' => $LOCK_ARC, 'locked_at' => $LOCK_ARC,
    '_log_responsabile' => $resp,
    '_pdf' => ['Determina n. 12/2026', '<p><strong>OGGETTO:</strong> Concessione di contributo economico a persona fisica.</p>'],
]);

// H) ARCHIVIATO con referto (verra' FIRMATO dallo script bash) — per S3-T1
$idSig = $mkAtto([
    'titolo' => 'Determina n. 30/2026', 'tipo_atto' => 'determina', 'stato_atto' => 3, 'state' => 2,
    'numero_protocollo' => '2026/0007', 'anno_protocollo' => 2026, 'progressivo_protocollo' => 7,
    'data_atto' => '2026-06-11', 'oggetto' => 'Liquidazione fattura fornitore - referto firmato',
    'publish_up' => $UP_ARC, 'publish_down' => $DOWN_ARC, 'buffer_expires_at' => $LOCK_ARC, 'locked_at' => $LOCK_ARC,
    '_log_responsabile' => $resp,
    '_pdf' => ['Determina n. 30/2026', '<p><strong>OGGETTO:</strong> Liquidazione fattura al fornitore.</p>'],
]);

// contatore protocollo
$cont = (object) ['anno' => 2026, 'ultimo_numero' => 7];
$db->insertObject('#__alboonline_contatori', $cont);

// --- 3) REFERTI (per gli archiviati) — date sensate dal generatore --------
$gen = new RefertoGenerator($db);
foreach ([$idArc, $idObl, $idSig] as $aid) {
    $rid = $gen->emit($aid, $adminId, $resp, RefertoGenerator::TIPO_DEFISSIONE);
    echo "Referto emesso per atto #$aid -> referto #$rid\n";
}

// espone l'id del referto da firmare per lo step bash
$refToSign = (int) $db->setQuery('SELECT id FROM #__alboonline_referti WHERE atto_id = ' . (int) $idSig)->loadResult();
$pdfToSign = (string) $db->setQuery('SELECT pdf_filename FROM #__alboonline_referti WHERE id = ' . $refToSign)->loadResult();
echo "SIGN_REFERTO_ID=$refToSign\n";
echo "SIGN_REFERTO_PDF=$pdfToSign\n";

echo "\nSeed completato: 8 atti (1 bozza, 4 pubblicati, 3 archiviati), referti con date sensate.\n";
