<?php
/**
 * Genera il set di documenti di prova per il collaudo (dev-only).
 *
 * Uso:  ddev exec php scripts/make-documenti-test.php
 * Esito: docs/collaudo/documenti-test/  (rigenerabile: sovrascrive)
 *
 * Ogni file esiste per SOLLECITARE un controllo preciso del componente. Cosa
 * ciascuno deve provocare è scritto nel README.md della stessa cartella.
 *
 * I certificati di firma sono quelli self-signed di scripts/firma-test/ (dev-only,
 * NON sono certificati qualificati: servono a esercitare il verificatore, non a
 * dimostrare la validità legale di una firma).
 */

require_once \dirname(__DIR__) . '/administrator/components/com_alboonline/vendor/autoload.php';

$root    = \dirname(__DIR__);
$outDir  = $root . '/docs/collaudo/documenti-test';
$certDir = $root . '/scripts/firma-test';

if (!is_dir($outDir) && !mkdir($outDir, 0755, true)) {
    exit("Impossibile creare $outDir\n");
}

$fatti = [];

/** PDF con testo reale, via mPDF (stesso motore del referto). */
function pdf(string $file, string $titolo, string $corpo, int $ripeti = 1): void
{
    $mpdf = new \Mpdf\Mpdf(['format' => 'A4', 'tempDir' => sys_get_temp_dir()]);
    $html = '';

    for ($i = 1; $i <= $ripeti; $i++) {
        $sep   = $i > 1 ? '<pagebreak />' : '';
        $html .= $sep . '<h2 style="font-family:serif">' . $titolo
            . ($ripeti > 1 ? " — pagina $i di $ripeti" : '') . '</h2>'
            . '<div style="font-family:serif;font-size:11pt;line-height:1.6">' . $corpo . '</div>';
    }

    $mpdf->WriteHTML($html);
    $mpdf->Output($file, 'F');
}

// ---------------------------------------------------------------- 01 · regolare
// Accenti e € apposta: verificano la resa dei font DejaVu ridotti (scelta di S7).
pdf(
    $outDir . '/01-determina-regolare.pdf',
    'Determinazione dirigenziale n. 145/2026',
    '<p><strong>Oggetto:</strong> affidamento del servizio di manutenzione ordinaria '
    . 'degli impianti — importo € 12.480,50 (IVA inclusa).</p>'
    . '<p>Il Responsabile del Servizio, visti gli artt. 32 della L. 69/2009 e 124 del '
    . 'D.Lgs. 267/2000, <em>determina</em> di affidare il servizio alla ditta '
    . 'aggiudicataria, dando atto che la spesa trova copertura sul capitolo 1120.</p>'
    . '<p>Città di riferimento: Comune di Esempio. Perché il documento sia leggibile, '
    . 'questo testo è selezionabile (non è una scansione).</p>'
);
$fatti[] = '01-determina-regolare.pdf';

// -------------------------------------------------------------- 02 · multipagina
pdf(
    $outDir . '/02-delibera-multipagina.pdf',
    'Deliberazione di Giunta n. 40/2026',
    '<p>Testo di riempimento per ottenere un documento di più pagine, utile a provare '
    . 'la stampa, il download e la resa degli allegati lunghi.</p>'
    . str_repeat('<p>Considerato che il presente capoverso serve unicamente a occupare '
        . 'spazio nel documento di prova, senza alcun valore giuridico.</p>', 12),
    6
);
$fatti[] = '02-delibera-multipagina.pdf';

// ------------------------------------------------ 03 · finta scansione (no testo)
// Immagine raster: nessuno strato di testo → pdftotext non estrae nulla →
// FormatoPdf deve emettere AVVISO_SOLO_IMMAGINE.
$png = sys_get_temp_dir() . '/albo-scan.png';
$img = imagecreatetruecolor(1240, 1754);            // A4 a 150 dpi
$bg  = imagecolorallocate($img, 246, 244, 238);     // bianco sporco, come una copia
$fg  = imagecolorallocate($img, 30, 30, 30);
imagefill($img, 0, 0, $bg);

$righe = [
    'COMUNE DI ESEMPIO',
    '',
    'Determinazione n. 210 del 2026',
    '',
    'Oggetto: documento acquisito da scanner.',
    'Questo testo e\' disegnato come immagine:',
    'non e\' estraibile, non e\' selezionabile.',
    '',
    'Serve a far scattare l\'avviso "solo immagine".',
];
$y = 160;

foreach ($righe as $riga) {
    imagestring($img, 5, 120, $y, $riga, $fg);      // font bitmap = pixel, non testo
    $y += 46;
}

// Qualche imperfezione: una scansione vera non è mai perfettamente pulita.
for ($i = 0; $i < 400; $i++) {
    imagesetpixel($img, random_int(0, 1239), random_int(0, 1753), $fg);
}

imagepng($img, $png);
imagedestroy($img);

$mpdf = new \Mpdf\Mpdf([
    'format'       => 'A4',
    'tempDir'      => sys_get_temp_dir(),
    'margin_left'  => 0,
    'margin_right' => 0,
    'margin_top'   => 0,
    'margin_bottom' => 0,
]);
$mpdf->WriteHTML('<img src="' . $png . '" style="width:210mm">');
$mpdf->Output($outDir . '/03-scansione-immagine.pdf', 'F');
unlink($png);
$fatti[] = '03-scansione-immagine.pdf';

// ------------------------------------------------------ 04/05 · firma CAdES (.p7m)
$src  = $outDir . '/01-determina-regolare.pdf';
$p7m  = $outDir . '/04-firmato-cades.p7m';
$cert = $certDir . '/cert.pem';
$key  = $certDir . '/key.pem';

if (!is_file($cert) || !is_file($key)) {
    exit("Mancano i certificati di test in $certDir\n");
}

// -nodetach: il PDF resta incapsulato nella busta (è il formato .p7m reale).
exec(
    'openssl cms -sign -binary -in ' . escapeshellarg($src)
    . ' -signer ' . escapeshellarg($cert)
    . ' -inkey ' . escapeshellarg($key)
    . ' -outform DER -nodetach -out ' . escapeshellarg($p7m) . ' 2>/dev/null',
    $o,
    $rc
);

if ($rc !== 0 || !is_file($p7m)) {
    exit("Firma CAdES fallita\n");
}
$fatti[] = '04-firmato-cades.p7m';

// Copia manomessa: un byte girato a metà busta → l'integrità deve saltare.
$bytes            = (string) file_get_contents($p7m);
$i                = intdiv(\strlen($bytes), 2);
$bytes[$i]        = \chr(\ord($bytes[$i]) ^ 0xFF);
file_put_contents($outDir . '/05-firmato-cades-manomesso.p7m', $bytes);
$fatti[] = '05-firmato-cades-manomesso.p7m';

// ------------------------------------------------------------- 06/07 · da riusare
// PAdES e PDF/A già prodotti e verificati in S3: li portiamo nel set invece di
// rigenerarli (firmare in PAdES richiede strumenti fuori da questo script).
foreach ([
    'sample-pades.pdf' => '06-firmato-pades.pdf',
    'sample-pdfa.pdf'  => '07-pdfa.pdf',
] as $da => $a) {
    if (is_file($certDir . '/' . $da)) {
        copy($certDir . '/' . $da, $outDir . '/' . $a);
        $fatti[] = $a;
    }
}

// -------------------------------------------------- 08/09 · file che vanno RIFIUTATI
// Estensione .pdf ma contenuto di testo: il controllo MIME deve smascherarlo.
file_put_contents(
    $outDir . '/08-finto-pdf.pdf',
    "Questo non e' un PDF: e' testo semplice con l'estensione .pdf.\n"
    . "L'upload deve rifiutarlo sul MIME, non fidarsi dell'estensione.\n"
);
$fatti[] = '08-finto-pdf.pdf';

// Estensione non ammessa: deve essere rifiutata a monte.
file_put_contents(
    $outDir . '/09-estensione-vietata.docx',
    "Contenuto irrilevante: conta l'estensione, che non e' fra pdf/p7m.\n"
);
$fatti[] = '09-estensione-vietata.docx';

// ------------------------------------------- 10 · dati particolari NEL CONTENUTO
// Serve a dimostrare un LIMITE NOTO, non a far passare un test: l'alert categorie
// particolari analizza i CAMPI dell'atto (titolo/oggetto), non il testo del PDF.
// Caricando questo file l'avviso NON deve comparire: è il comportamento atteso.
pdf(
    $outDir . '/10-dati-particolari-nel-pdf.pdf',
    'Determinazione n. 77/2026',
    '<p><strong>Oggetto:</strong> contributo economico a nucleo familiare.</p>'
    . '<p>Vista la certificazione di invalidità del richiedente e la diagnosi allegata; '
    . 'considerata la situazione di disagio economico attestata dall\'ISEE e la presenza '
    . 'di un minorenne a carico, si dispone l\'erogazione del sussidio.</p>'
    . '<p>Questi termini stanno nel CONTENUTO del PDF, non nei campi dell\'atto: '
    . 'l\'avviso categorie particolari NON deve scattare (limite noto e voluto).</p>'
);
$fatti[] = '10-dati-particolari-nel-pdf.pdf';

echo "Generati in docs/collaudo/documenti-test/:\n";

foreach ($fatti as $f) {
    printf("  %-34s %7d byte\n", $f, filesize($outDir . '/' . $f));
}
