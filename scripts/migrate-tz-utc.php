<?php
/**
 * Migrazione M9 — uniformare i timestamp del componente a UTC.
 *
 * Contesto: il componente scriveva le date con DUE basi di tempo diverse:
 *   - UTC          -> Factory::getDate()->toSql()   (logAction, AttoTable, extendPublishDown)
 *   - Ora locale   -> SELECT NOW() di MySQL, date() di PHP  (publishAtto, task lock/archive)
 *
 * Questo script converte in UTC SOLO i valori scritti in ora locale, lasciando
 * intatti quelli gia' in UTC. La conversione e' consapevole dell'ora legale:
 * il valore "naive" viene interpretato nel fuso del server e convertito in UTC.
 *
 * Uso:
 *   ddev exec php scripts/migrate-tz-utc.php              # dry-run (default, non scrive)
 *   ddev exec php scripts/migrate-tz-utc.php --apply      # esegue la migrazione
 *   ddev exec php scripts/migrate-tz-utc.php --apply --tz=Europe/Rome
 *
 * Idempotente: al termine scrive il marcatore `tz_migrated_utc_v1` nei params
 * del componente e rifiuta di rieseguirsi.
 *
 * !! VINCOLO D'ORDINE !!
 * Questo script va eseguito INSIEME alla correzione del codice (che porta tutti
 * gli scrittori a UTC), senza pubblicazioni in mezzo:
 *   - migrare prima della correzione  -> il codice riscrive in ora locale;
 *   - correggere e pubblicare un atto prima di migrare -> quell'atto avrebbe
 *     publish_down gia' in UTC ma nessun log 'extended'/'reactivated', e la
 *     migrazione lo sposterebbe erroneamente di -N ore.
 * Su installazioni reali va agganciato al postflight dello script di
 * aggiornamento del componente, cosi' la finestra non esiste.
 *
 * @package    Com_Alboonline
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 */

$opts    = getopt('', ['apply', 'tz::', 'force']);
$apply   = isset($opts['apply']);
$force   = isset($opts['force']);
$serverTz = $opts['tz'] ?? date_default_timezone_get();

$root = dirname(__DIR__);
require_once $root . '/configuration.php';
$cfg = new JConfig();

$db = new mysqli($cfg->host, $cfg->user, $cfg->password, $cfg->db);

if ($db->connect_errno) {
    fwrite(STDERR, "Connessione DB fallita: {$db->connect_error}\n");
    exit(1);
}

$p = $cfg->dbprefix;
$MARKER = 'tz_migrated_utc_v1';

/* ------------------------------------------------------------------ */
/* Utilita'                                                            */
/* ------------------------------------------------------------------ */

$utc = new DateTimeZone('UTC');
$loc = new DateTimeZone($serverTz);

/** Interpreta il valore naive nel fuso del server e lo converte in UTC (DST-aware). */
$toUtc = static function (?string $v) use ($loc, $utc): ?string {
    if ($v === null || $v === '' || str_starts_with($v, '0000-00-00')) {
        return null;
    }
    $d = new DateTime($v, $loc);
    $d->setTimezone($utc);
    return $d->format('Y-m-d H:i:s');
};

$changes = [];
$note = static function (string $table, $id, string $col, string $from, string $to) use (&$changes) {
    $delta = (strtotime($to) - strtotime($from)) / 3600;
    $changes[] = compact('table', 'id', 'col', 'from', 'to') + ['delta' => $delta];
};

/* ------------------------------------------------------------------ */
/* Guardia di idempotenza                                              */
/* ------------------------------------------------------------------ */

$r = $db->query("SELECT params FROM {$p}extensions WHERE element='com_alboonline' AND type='component'");
$paramsJson = $r->fetch_row()[0] ?? '{}';
$params = json_decode($paramsJson, true) ?: [];

if (!empty($params[$MARKER]) && !$force) {
    echo "Migrazione gia' eseguita (marcatore '{$MARKER}' presente). Usa --force per rieseguirla.\n";
    exit(0);
}

/* ------------------------------------------------------------------ */
/* 1) #__alboonline_log.created  —  locale SOLO per le righe del Task  */
/* ------------------------------------------------------------------ */

$res = $db->query("SELECT id, azione, user_name, created FROM {$p}alboonline_log
                   WHERE user_name = 'System Task' ORDER BY id");
while ($row = $res->fetch_assoc()) {
    if ($new = $toUtc($row['created'])) {
        $note('alboonline_log', $row['id'] . ' (' . $row['azione'] . ')', 'created', $row['created'], $new);
    }
}

/* ------------------------------------------------------------------ */
/* 2) atti: publish_up / buffer_expires_at / locked_at  — sempre locale */
/* ------------------------------------------------------------------ */

foreach (['publish_up', 'buffer_expires_at', 'locked_at'] as $col) {
    $res = $db->query("SELECT id, `$col` AS v FROM {$p}alboonline_atti
                       WHERE `$col` IS NOT NULL AND `$col` NOT LIKE '0000-%' ORDER BY id");
    while ($row = $res->fetch_assoc()) {
        if ($new = $toUtc($row['v'])) {
            $note('alboonline_atti', $row['id'], $col, $row['v'], $new);
        }
    }
}

/* ------------------------------------------------------------------ */
/* 3) atti.publish_down — UTC se l'atto e' stato extended/reactivated  */
/*    (quelle sono le uniche vie che lo riscrivono via Factory::getDate) */
/* ------------------------------------------------------------------ */

$utcAtti = [];
$res = $db->query("SELECT DISTINCT atto_id FROM {$p}alboonline_log
                   WHERE azione IN ('extended','reactivated')");
while ($row = $res->fetch_row()) {
    $utcAtti[(int) $row[0]] = true;
}

$res = $db->query("SELECT id, publish_down FROM {$p}alboonline_atti
                   WHERE publish_down IS NOT NULL AND publish_down NOT LIKE '0000-%' ORDER BY id");
while ($row = $res->fetch_assoc()) {
    if (isset($utcAtti[(int) $row['id']])) {
        continue; // gia' UTC: scritto da extendPublishDown()
    }
    if ($new = $toUtc($row['publish_down'])) {
        $note('alboonline_atti', $row['id'], 'publish_down', $row['publish_down'], $new);
    }
}

/* ------------------------------------------------------------------ */
/* Report                                                              */
/* ------------------------------------------------------------------ */

echo "\n";
echo "Migrazione timestamp -> UTC" . ($apply ? "  [APPLICA]" : "  [DRY-RUN: nessuna scrittura]") . "\n";
echo str_repeat('=', 96) . "\n";
echo "Fuso del server assunto per i valori 'locali': {$serverTz}\n";
echo "Atti con publish_down GIA' in UTC (esclusi): " . ($utcAtti ? implode(', ', array_keys($utcAtti)) : '(nessuno)') . "\n\n";

if (!$changes) {
    echo "Nessuna modifica necessaria.\n";
    exit(0);
}

printf("%-18s %-22s %-18s %-21s %-21s %s\n", 'TABELLA', 'ID', 'COLONNA', 'DA (locale)', 'A (UTC)', 'DELTA');
echo str_repeat('-', 116) . "\n";
foreach ($changes as $c) {
    printf("%-18s %-22s %-18s %-21s %-21s %+.0f h\n",
        $c['table'], $c['id'], $c['col'], $c['from'], $c['to'], $c['delta']);
}
echo str_repeat('-', 116) . "\n";
printf("Totale: %d valori da convertire.\n\n", count($changes));

if (!$apply) {
    echo "Nessuna scrittura effettuata. Rilancia con --apply per applicare.\n";
    exit(0);
}

/* ------------------------------------------------------------------ */
/* Applicazione (in transazione)                                       */
/* ------------------------------------------------------------------ */

$db->begin_transaction();

try {
    foreach ($changes as $c) {
        $id = (int) preg_replace('/\D.*$/', '', (string) $c['id']);
        $sql = sprintf("UPDATE %s%s SET `%s` = ? WHERE id = ?", $p, $c['table'], $c['col']);
        $stmt = $db->prepare($sql);
        $stmt->bind_param('si', $c['to'], $id);

        if (!$stmt->execute()) {
            throw new RuntimeException("UPDATE fallito su {$c['table']}#{$id}.{$c['col']}: {$stmt->error}");
        }
        $stmt->close();
    }

    $params[$MARKER] = date('c');
    $newJson = json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $stmt = $db->prepare("UPDATE {$p}extensions SET params = ? WHERE element='com_alboonline' AND type='component'");
    $stmt->bind_param('s', $newJson);
    $stmt->execute();
    $stmt->close();

    $db->commit();
    printf("OK — %d valori convertiti. Marcatore '%s' scritto.\n", count($changes), $MARKER);
} catch (Throwable $e) {
    $db->rollback();
    fwrite(STDERR, "ERRORE, rollback eseguito: " . $e->getMessage() . "\n");
    exit(1);
}
