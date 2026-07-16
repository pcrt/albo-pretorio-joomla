<?php
/**
 * @package    Com_Alboonline
 * @copyright  2025
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\Helper;

defined('_JEXEC') or die;

/**
 * Analisi del FORMATO di un PDF allegato (S3-T2). Solo AVVISI, mai blocco.
 *
 * Le Linee Guida AgID (All. 2) raccomandano il profilo PDF/A per la conservazione
 * a lungo termine (non lo impongono), e l'accessibilità richiede PDF con testo
 * selezionabile (non scansioni-immagine). Qui rileviamo:
 *   - PDF/A: presenza del marcatore XMP pdfaid:part (rilevabile OFFLINE, puro PHP);
 *   - testo vs scansione: best-effort via pdftotext, con fallback onesto se assente.
 *
 * L'esito è consultivo: un ente può pubblicare un PDF ordinario o una scansione;
 * il sistema avvisa soltanto.
 */
class FormatoPdf
{
    public const AVVISO_NON_PDFA     = 'non_pdfa';
    public const AVVISO_SOLO_IMMAGINE = 'solo_immagine';

    public function analyze(string $filePath): object
    {
        $r = (object) [
            'is_pdf'       => false,
            'pdfa'         => false,
            'pdfa_livello' => null,   // es. "PDF/A-1B"
            'ha_testo'     => null,   // true | false | null (non determinabile)
            'avvisi'       => [],
        ];

        if (!is_file($filePath) || !is_readable($filePath)) {
            return $r;
        }

        $bytes = (string) file_get_contents($filePath);

        if (!str_starts_with($bytes, '%PDF-')) {
            return $r;
        }

        $r->is_pdf = true;

        // PDF/A: marcatore XMP pdfaid:part (elemento <pdfaid:part>N</> o attributo
        // pdfaid:part="N"). Lo stream XMP /Metadata è per specifica non compresso.
        if (preg_match('/pdfaid:part\s*[>="\']*\s*([0-9])/i', $bytes, $mp)) {
            $r->pdfa = true;
            $part = $mp[1];
            $conf = '';

            if (preg_match('/pdfaid:conformance\s*[>="\']*\s*([A-Za-z])/i', $bytes, $mc)) {
                $conf = strtoupper($mc[1]);
            }

            $r->pdfa_livello = 'PDF/A-' . $part . $conf;
        } else {
            $r->avvisi[] = self::AVVISO_NON_PDFA;
        }

        // Testo vs scansione: best-effort. Solo se possiamo determinarlo con certezza
        // segnaliamo la scansione; nell'incertezza restiamo in silenzio.
        $r->ha_testo = $this->hasText($filePath);

        if ($r->ha_testo === false) {
            $r->avvisi[] = self::AVVISO_SOLO_IMMAGINE;
        }

        return $r;
    }

    /**
     * Il PDF contiene testo estraibile? true/false via pdftotext; null se lo
     * strumento non è disponibile (non si può determinare → nessun avviso).
     */
    private function hasText(string $filePath): ?bool
    {
        if (!$this->pdftotextAvailable()) {
            return null;
        }

        $out = [];
        $rc  = 1;
        // -l 3: bastano le prime pagine per capire se c'è uno strato di testo.
        exec('pdftotext -l 3 ' . escapeshellarg($filePath) . ' - 2>/dev/null', $out, $rc);

        if ($rc !== 0) {
            return null;
        }

        return trim(implode('', $out)) !== '';
    }

    private function pdftotextAvailable(): bool
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
        @exec('pdftotext -v 2>/dev/null', $out, $rc);

        return $available = ($rc === 0);
    }
}
