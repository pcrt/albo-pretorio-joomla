<?php
/**
 * @package    Com_Alboonline
 * @copyright  2025
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Verifica INTERNA (offline) della firma digitale di un file (S3-T1).
 *
 * Accerta ciò che è affidabilmente verificabile senza rete — e SOLO quello:
 *   - integrità crittografica della firma (il documento non è stato alterato);
 *   - dati del firmatario (nome/CN e codice fiscale dal certificato);
 *   - finestra di validità del certificato;
 *   - presenza (euristica) di una marca temporale.
 *
 * NON accerta — perché richiede le Trusted List europee e il controllo di revoca
 * online (tier ESTERNO, gancio opzionale a un validatore DSS): la natura
 * QUALIFICATA del certificato, la catena verso una CA fidata, lo stato di revoca,
 * e la VALIDITÀ della marca temporale (ne rileviamo solo la presenza: convalidarla
 * esige il certificato della TSA e la sua revoca — resta in capo all'ente).
 * Di conseguenza l'esito interno NON è mai «firma qualificata valida a norma», ma
 * al massimo «firma tecnicamente integra». Vedi docs/ricerca-normativa/08-verifica-firma-digitale.md.
 *
 * La verifica è una FUNZIONE DI SUPPORTO (la legge non la impone alla
 * pubblicazione): non deve bloccare alcun flusso.
 */
class FirmaVerifier
{
    // Esiti possibili (chiavi di stato, tradotte a UI).
    public const ESITO_NESSUNA     = 'nessuna_firma';
    public const ESITO_INTEGRA     = 'integra';
    public const ESITO_COMPROMESSA = 'compromessa';
    public const ESITO_PADES_RILEVATA = 'pades_rilevata';
    public const ESITO_ERRORE      = 'errore';

    // OID id-aa-signatureTimeStampToken (RFC 3161 timestamp come attributo non firmato).
    private const OID_TIMESTAMP_TOKEN = '1.2.840.113549.1.9.16.2.14';

    /**
     * Verifica un file firmato. Non solleva eccezioni: incapsula l'esito nel report.
     *
     * @param   string  $filePath  Percorso del file da verificare
     *
     * @return  object  Report strutturato (vedi buildReport()).
     */
    public function verify(string $filePath): object
    {
        $report = $this->buildReport();

        if (!is_file($filePath) || !is_readable($filePath)) {
            $report->esito  = self::ESITO_ERRORE;
            $report->errore = 'file_non_leggibile';

            return $report;
        }

        $head = (string) file_get_contents($filePath, false, null, 0, 8);

        // PAdES: la firma è dentro un PDF (ByteRange + /Contents).
        if (str_starts_with($head, '%PDF-')) {
            return $this->verifyPades($filePath, $report);
        }

        // Altrimenti trattiamo il file come busta CAdES (.p7m).
        return $this->verifyCades($filePath, $report);
    }

    /**
     * Verifica una busta CAdES (.p7m). Prova le codifiche DER (tipica dei .p7m
     * italiani) e, in fallback, S/MIME e PEM.
     */
    private function verifyCades(string $filePath, object $report): object
    {
        $tmp   = rtrim((string) Factory::getApplication()->get('tmp_path', sys_get_temp_dir()), '/');
        $uniq  = bin2hex(random_bytes(8));
        $certs = $tmp . '/albo-firma-' . $uniq . '-certs.pem';
        $cont  = $tmp . '/albo-firma-' . $uniq . '-content.bin';

        // Fase 1: è una busta CMS/PKCS#7? Controllo STRUTTURALE (PKCS7_NOSIGS: non
        // verifica le firme, solo il formato). Distingue "non è una firma" da "firma
        // rotta". Prova le codifiche: DER (tipica dei .p7m italiani), S/MIME, PEM.
        $encoding = null;

        foreach ([OPENSSL_ENCODING_DER, OPENSSL_ENCODING_SMIME, OPENSSL_ENCODING_PEM] as $enc) {
            if (@openssl_cms_verify($filePath, PKCS7_NOVERIFY | PKCS7_NOSIGS, null, [], null, null, null, null, $enc) === true) {
                $encoding = $enc;
                break;
            }
        }

        if ($encoding === null) {
            // Nessuna busta CMS riconosciuta: il file non è firmato (CAdES).
            $report->esito = self::ESITO_NESSUNA;

            return $report;
        }

        $report->formato  = 'cades';
        $report->ha_firma = true;

        // Fase 2: verifica delle FIRME (integrità), senza la catena (PKCS7_NOVERIFY =
        // tier interno). Estrae certificati firmatari e contenuto incapsulato.
        $ok = @openssl_cms_verify($filePath, PKCS7_NOVERIFY, $certs, [], null, $cont, null, null, $encoding);

        if ($ok !== true) {
            $report->integrita = false;
            $report->esito     = self::ESITO_COMPROMESSA;
            $this->cleanup([$certs, $cont]);

            return $report;
        }

        $report->integrita = true;
        $report->esito     = self::ESITO_INTEGRA;

        // Hash del contenuto incapsulato (eContent): serve a M18 per confermare che il
        // .p7m firma PROPRIO il referto emesso (confronto con il suo pdf_hash).
        if (is_file($cont)) {
            $report->contenuto_hash = hash_file('sha256', $cont);
        }

        // Firmatari dai certificati estratti.
        if (is_file($certs)) {
            $report->firmatari = $this->parseSigners((string) file_get_contents($certs));
        }

        // Presenza (euristica) di una marca temporale nella busta.
        $report->marca_temporale = (object) [
            'presente' => $this->hasTimestampToken((string) file_get_contents($filePath)),
            'verificata' => false, // per scelta: convalidare il token è del tier esterno
        ];

        $this->cleanup([$certs, $cont]);

        return $report;
    }

    /**
     * Verifica una firma PAdES dentro un PDF: per ogni firma legge il /ByteRange
     * (byte firmati = tutto il file tranne il valore /Contents), ricostruisce quei
     * byte e verifica la firma detached del PKCS#7 in /Contents.
     *
     * La verifica detached su contenuto esterno non è esprimibile con la sola
     * `openssl_cms_verify()` di PHP: usiamo il binario `openssl` (con `-binary`,
     * indispensabile su contenuto binario). Se `exec`/openssl non sono disponibili,
     * degradiamo onestamente a «firma rilevata» senza asserire l'integrità.
     */
    private function verifyPades(string $filePath, object $report): object
    {
        $pdf = (string) file_get_contents($filePath);

        if (!preg_match_all('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $pdf, $brs, PREG_SET_ORDER)) {
            $report->esito = self::ESITO_NESSUNA;

            return $report;
        }

        $report->formato  = 'pades';
        $report->ha_firma = true;

        if (!$this->opensslCliAvailable()) {
            // Non possiamo verificare l'integrità: dichiariamo solo la presenza.
            $report->integrita = null;
            $report->esito     = self::ESITO_PADES_RILEVATA;

            return $report;
        }

        $tmp       = rtrim((string) Factory::getApplication()->get('tmp_path', sys_get_temp_dir()), '/');
        $allValid  = true;
        $firmatari = [];

        foreach ($brs as $m) {
            [$a, $b, $c, $d] = [(int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4]];

            // Byte firmati = i due intervalli del ByteRange concatenati.
            $signed = substr($pdf, $a, $b) . substr($pdf, $c, $d);

            // /Contents (PKCS#7 DER) è l'esadecimale nel buco tra i due intervalli.
            $gap = substr($pdf, $a + $b, $c - ($a + $b));

            if (!preg_match('/<([0-9A-Fa-f]+)>/', $gap, $cm)) {
                continue;
            }

            $hex = strlen($cm[1]) % 2 ? $cm[1] . '0' : $cm[1];
            $der = (string) hex2bin($hex);

            $uniq        = bin2hex(random_bytes(8));
            $contentFile = $tmp . '/albo-pades-' . $uniq . '-c.bin';
            $derFile     = $tmp . '/albo-pades-' . $uniq . '-s.der';
            $certsFile   = $tmp . '/albo-pades-' . $uniq . '-certs.pem';

            file_put_contents($contentFile, $signed);
            file_put_contents($derFile, $der);

            // Verifica detached: -content = byte firmati, -binary = niente
            // canonicalizzazione, -noverify = niente catena (tier interno).
            $out = [];
            $rc  = 1;
            exec(
                'openssl cms -verify -inform DER -in ' . escapeshellarg($derFile)
                . ' -content ' . escapeshellarg($contentFile)
                . ' -binary -noverify -out /dev/null 2>/dev/null',
                $out,
                $rc
            );

            if ($rc !== 0) {
                $allValid = false;
            }

            // Certificato firmatario dal PKCS#7.
            exec('openssl pkcs7 -inform DER -in ' . escapeshellarg($derFile)
                . ' -print_certs -out ' . escapeshellarg($certsFile) . ' 2>/dev/null');

            if (is_file($certsFile)) {
                foreach ($this->parseSigners((string) file_get_contents($certsFile)) as $f) {
                    $firmatari[] = $f;
                }
            }

            $this->cleanup([$contentFile, $derFile, $certsFile]);
        }

        $report->integrita = $allValid;
        $report->esito     = $allValid ? self::ESITO_INTEGRA : self::ESITO_COMPROMESSA;
        $report->firmatari = $firmatari;
        $report->marca_temporale = (object) [
            'presente'   => $this->hasTimestampToken($pdf),
            'verificata' => false,
        ];

        return $report;
    }

    /**
     * Il binario openssl è invocabile? (exec abilitato + comando presente)
     */
    private function opensslCliAvailable(): bool
    {
        static $available = null;

        if ($available !== null) {
            return $available;
        }

        if (!\function_exists('exec')) {
            return $available = false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        if (\in_array('exec', $disabled, true)) {
            return $available = false;
        }

        $out = [];
        $rc  = 1;
        @exec('openssl version 2>/dev/null', $out, $rc);

        return $available = ($rc === 0);
    }

    /**
     * Estrae i dati anagrafici dei firmatari dai certificati PEM.
     *
     * @return  array<int,object>
     */
    private function parseSigners(string $certsPem): array
    {
        $firmatari = [];

        if (!preg_match_all('/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s', $certsPem, $m)) {
            return $firmatari;
        }

        foreach ($m[0] as $certPem) {
            $cert = openssl_x509_parse($certPem);

            if (!$cert) {
                continue;
            }

            $subject = $cert['subject'] ?? [];
            $sn      = (string) ($subject['serialNumber'] ?? '');
            // Codice fiscale: nel serialNumber con prefisso di dominio (TINIT- per l'Italia).
            $cf = preg_replace('/^TINIT-/', '', $sn);

            $from = (int) ($cert['validFrom_time_t'] ?? 0);
            $to   = (int) ($cert['validTo_time_t'] ?? 0);
            $now  = time();

            $firmatari[] = (object) [
                'cn'             => (string) ($subject['CN'] ?? ''),
                'codice_fiscale' => $sn !== '' ? $cf : '',
                'organizzazione' => (string) ($subject['O'] ?? ''),
                'valido_dal'     => $from ? gmdate('Y-m-d H:i:s', $from) : null,
                'valido_al'      => $to ? gmdate('Y-m-d H:i:s', $to) : null,
                'nella_finestra' => ($from && $to) ? ($now >= $from && $now <= $to) : null,
            ];
        }

        return $firmatari;
    }

    /**
     * Presenza euristica di un token di marca temporale RFC 3161, cercando l'OID
     * id-aa-signatureTimeStampToken nella codifica DER della busta.
     */
    private function hasTimestampToken(string $der): bool
    {
        // Codifica DER dell'OID 1.2.840.113549.1.9.16.2.14.
        $oidDer = "\x06\x0b\x2a\x86\x48\x86\xf7\x0d\x01\x09\x10\x02\x0e";

        return str_contains($der, $oidDer);
    }

    private function buildReport(): object
    {
        return (object) [
            'formato'              => 'nessuna',   // cades | pades | nessuna
            'ha_firma'             => false,
            'integrita'            => null,        // true | false | null (non verificata)
            'firmatari'            => [],
            'contenuto_hash'       => null,        // sha256 dell'eContent (aggancio M18)
            'marca_temporale'      => null,        // {presente, verificata}
            'avanzata_disponibile' => false,       // tier esterno (DSS) non ancora agganciato
            'esito'                => self::ESITO_NESSUNA,
            'errore'               => null,
        ];
    }

    private function cleanup(array $paths): void
    {
        foreach ($paths as $p) {
            if (is_file($p)) {
                @unlink($p);
            }
        }
    }
}
