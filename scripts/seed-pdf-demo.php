<?php
// Dev-only: genera PDF realistici (determina/delibera) e li allega ad atti in bozza,
// così si possono guardare nell'admin. Uso: ddev exec php scripts/seed-pdf-demo.php
const _JEXEC = 1;
require_once \dirname(__DIR__) . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

require_once JPATH_ADMINISTRATOR . '/components/com_alboonline/vendor/autoload.php';

$db  = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
$dir = JPATH_ROOT . '/media/com_alboonline/allegati';

// Documenti di esempio: [atto_id (bozza), titolo, HTML del corpo]
$docs = [
    [
        11,
        'Determina dirigenziale n. 145/2026',
        '<h2 style="text-align:center;margin:0">COMUNE DI ESEMPIO</h2>
         <p style="text-align:center;margin:2px 0 16px">Area Tecnica &ndash; Servizio Lavori Pubblici</p>
         <h3 style="text-align:center;text-decoration:underline">DETERMINAZIONE DIRIGENZIALE N. 145 del 10/07/2026</h3>
         <p><strong>OGGETTO:</strong> Affidamento del servizio di manutenzione del verde pubblico
            per l&rsquo;anno 2026 &ndash; Impegno di spesa.</p>
         <p style="text-align:justify">IL RESPONSABILE DEL SERVIZIO</p>
         <p style="text-align:justify"><strong>VISTO</strong> il D.Lgs. 18 agosto 2000 n. 267 (TUEL) e in particolare gli artt. 107 e 183;</p>
         <p style="text-align:justify"><strong>VISTO</strong> il D.Lgs. 31 marzo 2023 n. 36 (Codice dei contratti pubblici);</p>
         <p style="text-align:justify"><strong>RITENUTO</strong> necessario garantire la manutenzione ordinaria delle aree verdi comunali;</p>
         <p style="text-align:justify"><strong>DETERMINA</strong></p>
         <ol>
           <li>di affidare il servizio in oggetto per l&rsquo;importo di &euro; 24.500,00 (IVA esclusa);</li>
           <li>di impegnare la relativa spesa sul capitolo 1420 del bilancio 2026;</li>
           <li>di dare atto che il presente provvedimento è soggetto a pubblicazione all&rsquo;Albo Pretorio Online.</li>
         </ol>
         <p style="margin-top:40px">Il Responsabile del Servizio<br><em>(documento firmato digitalmente)</em></p>',
    ],
    [
        7,
        'Deliberazione di Giunta n. 88/2026',
        '<h2 style="text-align:center;margin:0">COMUNE DI ESEMPIO</h2>
         <p style="text-align:center;margin:2px 0 16px">Giunta Comunale</p>
         <h3 style="text-align:center;text-decoration:underline">DELIBERAZIONE DI GIUNTA N. 88 del 10/07/2026</h3>
         <p><strong>OGGETTO:</strong> Approvazione del programma delle manifestazioni estive 2026.</p>
         <p style="text-align:justify">LA GIUNTA COMUNALE</p>
         <p style="text-align:justify"><strong>PREMESSO</strong> che l&rsquo;Amministrazione intende promuovere iniziative culturali e ricreative;</p>
         <p style="text-align:justify"><strong>VISTO</strong> il parere favorevole di regolarità tecnica e contabile;</p>
         <p style="text-align:justify"><strong>DELIBERA</strong></p>
         <ol>
           <li>di approvare il programma delle manifestazioni estive allegato alla presente;</li>
           <li>di demandare al competente Servizio gli atti gestionali conseguenti;</li>
           <li>di dichiarare la presente immediatamente eseguibile.</li>
         </ol>
         <p style="margin-top:40px">Il Sindaco &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Il Segretario Comunale<br><em>(documento firmato digitalmente)</em></p>',
    ],
];

foreach ($docs as [$attoId, $titolo, $bodyHtml]) {
    $mpdf = new \Mpdf\Mpdf(['format' => 'A4', 'tempDir' => sys_get_temp_dir()]);
    $mpdf->SetTitle($titolo);
    $mpdf->WriteHTML('<div style="font-family:serif;font-size:11pt;line-height:1.5">' . $bodyHtml . '</div>');
    $bytes = $mpdf->Output('', 'S');

    $hash     = hash('sha256', $bytes);
    $filename = $hash . '.pdf';
    file_put_contents($dir . '/' . $filename, $bytes);

    // original_name leggibile
    $original = preg_replace('/[^A-Za-z0-9]+/', '-', $titolo) . '.pdf';

    $record = (object) [
        'atto_id'       => $attoId,
        'titolo'        => $titolo,
        'filename'      => $filename,
        'original_name' => $original,
        'hash'          => null, // sarà calcolato alla blindatura
        'size'          => \strlen($bytes),
        'mime'          => 'application/pdf',
        'ordering'      => 0,
        'created'       => Factory::getDate()->toSql(),
        'created_by'    => 690,
    ];
    $db->insertObject('#__alboonline_allegati', $record);

    echo "Atto #$attoId  <-  $filename  (" . \strlen($bytes) . " byte)  \"$titolo\"\n";
}

echo "Fatto.\n";
