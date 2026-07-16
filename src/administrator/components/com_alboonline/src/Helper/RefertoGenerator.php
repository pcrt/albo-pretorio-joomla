<?php
/**
 * @package    Com_Alboonline
 * @copyright  2025
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\Filesystem\Folder;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Genera e SIGILLA il referto di pubblicazione (M18).
 *
 * A differenza della vecchia vista — che rigenerava l'attestazione a ogni
 * apertura leggendo dati che nel frattempo potevano cambiare — questo generatore
 * produce il documento UNA SOLA VOLTA, alla defissione, quando il periodo di
 * pubblicazione e' concluso e le date sono definitive:
 *
 *  - i campi vengono CONGELATI in una riga di #__alboonline_referti (snapshot);
 *  - il PDF viene scritto su disco e non piu' rigenerato (byte identici a ogni
 *    download: l'ente puo' stamparlo e firmarlo a penna);
 *  - viene calcolato l'hash SHA-256 del file (integrita' TECNICA — la
 *    immodificabilita' probatoria richiede firma o conservazione, vedi
 *    docs/ricerca-normativa/07-referto-pubblicazione.md);
 *  - l'emissione e' tracciata nell'audit log.
 *
 * Idempotente: un solo referto per (atto, tipo documento, periodo). Un atto
 * riattivato apre un nuovo publish_up => un nuovo referto (non riscrive il primo).
 */
class RefertoGenerator
{
    public const TIPO_DEFISSIONE = 'defissione';
    public const TIPO_AVVIO      = 'avvio';

    private const REFERTI_DIR = '/media/com_alboonline/referti';

    private DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Emette il referto per un atto, se il componente lo prevede e non esiste gia'.
     *
     * @param   int     $attoId         ID dell'atto
     * @param   int     $actorId        ID dell'attore (0 = automazione)
     * @param   string  $actorName      Nome dell'attore (es. 'System Task')
     * @param   string  $tipoDocumento  self::TIPO_DEFISSIONE | self::TIPO_AVVIO
     *
     * @return  int  ID del referto emesso; 0 se disattivato o gia' esistente.
     *
     * @throws  \RuntimeException  su errore di generazione/scrittura.
     */
    public function emit(int $attoId, int $actorId = 0, string $actorName = 'System Task', string $tipoDocumento = self::TIPO_DEFISSIONE): int
    {
        $params = ComponentHelper::getParams('com_alboonline');

        if ((int) $params->get('referto_attivo', 1) !== 1) {
            return 0;
        }

        $atto = $this->loadAtto($attoId);

        if (!$atto) {
            throw new \RuntimeException('Referto: atto #' . $attoId . ' non trovato.');
        }

        // Lingua DETERMINISTICA del documento: quella dell'atto se specifica,
        // altrimenti la lingua predefinita del sito. NON la lingua ambientale
        // (in contesto scheduler/CLI potrebbe essere en-GB su un sito italiano).
        $lang = $this->resolveLanguage($atto->language ?? null);

        // Idempotenza: stesso atto + stesso tipo + stesso inizio periodo => gia' emesso.
        if ($this->exists($attoId, $tipoDocumento, $atto->publish_up)) {
            return 0;
        }

        $responsabile = $this->resolveResponsabile($attoId, (int) $atto->created_by);
        $ente         = AlboonlineHelper::getEnteEmanante($atto->ente_emanante);
        $rettifica    = $this->resolveRettifica($atto->rettifica_di !== null ? (int) $atto->rettifica_di : null);

        $now = Factory::getDate()->toSql();

        // Snapshot congelato: da qui in poi non si rilegge piu' l'atto.
        $snapshot = (object) [
            'atto_id'              => $attoId,
            'tipo_documento'       => $tipoDocumento,
            'numero_repertorio'    => (string) $atto->numero_protocollo,
            'tipo_atto'            => (string) $atto->tipo_atto,
            'oggetto'              => (string) ($atto->oggetto ?: $atto->titolo),
            'ente_emanante'        => $ente,
            'responsabile'         => $responsabile,
            'data_atto'            => $atto->data_atto,
            'publish_up'           => $atto->publish_up,
            'publish_down'         => $atto->publish_down,
            'rettifica_di'         => $rettifica->id,
            'rettifica_repertorio' => $rettifica->repertorio,
            // M11: la motivazione sta sull'atto rettificante (quello corrente), non
            // sull'atto rettificato. La congeliamo solo se c'è un legame di rettifica.
            'note_rettifica'       => $rettifica->id ? $atto->note_rettifica : null,
            // S3-T4: segnatura di protocollo dell'ente, congelata.
            'segnatura_protocollo' => $atto->segnatura_protocollo,
            'segnatura_data'       => $atto->segnatura_data,
            'emesso_at'            => $now,
        ];

        $html  = $this->renderHtml($snapshot, $params, $tipoDocumento, $lang);
        $bytes = $this->generatePdf($html);
        $hash  = hash('sha256', $bytes);

        $filename = $this->writeFile($bytes, $hash);

        // Riga di referto: congela lo snapshot + il sigillo del PDF.
        $row = (object) [
            'atto_id'              => $snapshot->atto_id,
            'tipo_documento'       => $snapshot->tipo_documento,
            'numero_repertorio'    => $snapshot->numero_repertorio,
            'tipo_atto'            => $snapshot->tipo_atto,
            'oggetto'              => $snapshot->oggetto,
            'ente_emanante'        => $snapshot->ente_emanante,
            'responsabile'         => $snapshot->responsabile,
            'data_atto'            => $snapshot->data_atto,
            'publish_up'           => $snapshot->publish_up,
            'publish_down'         => $snapshot->publish_down,
            'rettifica_di'         => $snapshot->rettifica_di,
            'rettifica_repertorio' => $snapshot->rettifica_repertorio,
            'note_rettifica'       => $snapshot->note_rettifica,
            'segnatura_protocollo' => $snapshot->segnatura_protocollo,
            'segnatura_data'       => $snapshot->segnatura_data,
            'pdf_filename'         => $filename,
            'pdf_hash'             => $hash,
            'pdf_size'             => \strlen($bytes),
            'stato'                => 'emesso',
            'emesso_at'            => $now,
            'emesso_by'            => $actorId,
            'created'              => $now,
        ];

        $this->db->insertObject('#__alboonline_referti', $row, 'id');

        $this->logEmissione($attoId, $actorId, $actorName, $now, $snapshot->numero_repertorio);

        return (int) $row->id;
    }

    /**
     * Esiste gia' un referto per questo atto/tipo/inizio-periodo?
     */
    public function exists(int $attoId, string $tipoDocumento, ?string $publishUp): bool
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__alboonline_referti'))
            ->where($this->db->quoteName('atto_id') . ' = :atto')
            ->where($this->db->quoteName('tipo_documento') . ' = :tipo')
            ->bind(':atto', $attoId, ParameterType::INTEGER)
            ->bind(':tipo', $tipoDocumento, ParameterType::STRING);

        if ($publishUp === null) {
            $query->where($this->db->quoteName('publish_up') . ' IS NULL');
        } else {
            $query->where($this->db->quoteName('publish_up') . ' = :pu')
                ->bind(':pu', $publishUp, ParameterType::STRING);
        }

        $this->db->setQuery($query);

        return (int) $this->db->loadResult() > 0;
    }

    /**
     * Lingua in cui produrre il documento (deterministica, non ambientale).
     * Ordine: lingua propria dell'atto (se non '*') -> default del sito -> en-GB.
     */
    private function resolveLanguage(?string $attoLang): Language
    {
        $tag = trim((string) $attoLang);

        if ($tag === '' || $tag === '*') {
            $tag = (string) ComponentHelper::getParams('com_languages')->get('site', 'en-GB');
        }

        $lang = Factory::getContainer()->get(LanguageFactoryInterface::class)->createLanguage($tag);
        $lang->load('com_alboonline', JPATH_ADMINISTRATOR);

        return $lang;
    }

    private function loadAtto(int $attoId): ?object
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName([
                'a.id', 'a.titolo', 'a.oggetto', 'a.tipo_atto', 'a.data_atto',
                'a.numero_protocollo', 'a.anno_protocollo', 'a.stato_atto', 'a.ente_emanante',
                'a.publish_up', 'a.publish_down', 'a.created_by', 'a.rettifica_di', 'a.note_rettifica',
                'a.language', 'a.segnatura_protocollo', 'a.segnatura_data',
            ]))
            ->from($this->db->quoteName('#__alboonline_atti', 'a'))
            ->where($this->db->quoteName('a.id') . ' = :id')
            ->bind(':id', $attoId, ParameterType::INTEGER);
        $this->db->setQuery($query);

        return $this->db->loadObject() ?: null;
    }

    /**
     * Responsabile della pubblicazione: autore dell'azione 'published' nel log,
     * con fallback all'autore del record.
     */
    private function resolveResponsabile(int $attoId, int $createdBy): string
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('user_name'))
            ->from($this->db->quoteName('#__alboonline_log'))
            ->where($this->db->quoteName('atto_id') . ' = :id')
            ->where($this->db->quoteName('azione') . ' = ' . $this->db->quote('published'))
            ->order($this->db->quoteName('id') . ' DESC')
            ->bind(':id', $attoId, ParameterType::INTEGER);
        $this->db->setQuery($query, 0, 1);
        $nome = (string) $this->db->loadResult();

        if ($nome !== '') {
            return $nome;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('name'))
            ->from($this->db->quoteName('#__users'))
            ->where($this->db->quoteName('id') . ' = :uid')
            ->bind(':uid', $createdBy, ParameterType::INTEGER);
        $this->db->setQuery($query);

        return (string) $this->db->loadResult();
    }

    /**
     * Legame di rettifica congelato (M10/M11): repertorio dell'atto rettificato +
     * motivazione. Ritorna un oggetto con id/repertorio/note (tutti eventualmente null).
     */
    private function resolveRettifica(?int $rettificaDi): object
    {
        $out = (object) ['id' => null, 'repertorio' => null];

        if (!$rettificaDi) {
            return $out;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('numero_protocollo'))
            ->from($this->db->quoteName('#__alboonline_atti'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $rettificaDi, ParameterType::INTEGER);
        $this->db->setQuery($query);

        $out->id         = $rettificaDi;
        $out->repertorio = $this->db->loadResult() ?: null;

        return $out;
    }

    /**
     * Costruisce l'HTML del documento. Formula al PASSATO con date EFFETTIVE:
     * legittima perche' emesso alla defissione, a periodo concluso (corregge il
     * difetto (a) di M18, che attestava al passato un periodo ancora in corso).
     */
    private function renderHtml(object $s, $params, string $tipoDocumento, Language $lang): string
    {
        $tz  = new \DateTimeZone((string) Factory::getApplication()->get('offset', 'UTC'));
        $fmt = static function ($sqlUtc) use ($tz) {
            if (empty($sqlUtc) || $sqlUtc === '0000-00-00 00:00:00' || $sqlUtc === '0000-00-00') {
                return '—';
            }

            return Factory::getDate($sqlUtc)->setTimezone($tz)->format('d/m/Y');
        };

        $titolo = trim((string) $params->get('referto_titolo', ''));
        $titolo = $titolo !== '' ? $titolo : $lang->_('COM_ALBOONLINE_REFERTO_TITOLO');

        $sottotitolo = trim((string) $params->get('referto_sottotitolo', ''));

        $tipoLabel = AlboonlineHelper::getTipoLabel($s->tipo_atto);
        $e         = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $righe = [
            [$lang->_('COM_ALBOONLINE_REFERTO_REPERTORIO'), '<strong>' . $e($s->numero_repertorio) . '</strong>'],
            [$lang->_('COM_ALBOONLINE_REFERTO_OGGETTO'),    $e($s->oggetto)],
            [$lang->_('COM_ALBOONLINE_REFERTO_TIPO'),       $e($tipoLabel)],
            [$lang->_('COM_ALBOONLINE_REFERTO_DATA_ATTO'),  $fmt($s->data_atto)],
        ];

        // S3-T4: segnatura di protocollo dell'ente (se presente).
        if (!empty($s->segnatura_protocollo)) {
            $seg = $e($s->segnatura_protocollo);

            if (!empty($s->segnatura_data) && $s->segnatura_data !== '0000-00-00') {
                $seg .= ' &middot; ' . $fmt($s->segnatura_data);
            }

            $righe[] = [$lang->_('COM_ALBOONLINE_REFERTO_SEGNATURA'), $seg];
        }

        $righe[] = [$lang->_('COM_ALBOONLINE_REFERTO_DAL'), '<strong>' . $fmt($s->publish_up) . '</strong>'];
        $righe[] = [$lang->_('COM_ALBOONLINE_REFERTO_AL'),  '<strong>' . $fmt($s->publish_down) . '</strong>'];
        // Il responsabile e' un DATO del referto: sta nella tabella, vicino alle date.
        // Il piede resta uno spazio libero per la firma (a penna o digitale), senza
        // sovrapporre il nome alla riga di firma.
        $righe[] = [$lang->_('COM_ALBOONLINE_REFERTO_RESPONSABILE_LABEL'), $e($s->responsabile ?: '—')];

        // M10: legame di rettifica nel documento probatorio.
        if ($s->rettifica_repertorio) {
            $righe[] = [
                $lang->_('COM_ALBOONLINE_REFERTO_RETTIFICA_DI'),
                $e($s->rettifica_repertorio),
            ];
        }

        $tabella = '';

        foreach ($righe as $r) {
            $tabella .= '<tr><th>' . $e($r[0]) . '</th><td>' . $r[1] . '</td></tr>';
        }

        $attestazione = sprintf(
            $lang->_('COM_ALBOONLINE_REFERTO_ATTESTAZIONE'),
            '<strong>' . $e($s->numero_repertorio) . '</strong>',
            '<strong>' . $fmt($s->publish_up) . '</strong>',
            '<strong>' . $fmt($s->publish_down) . '</strong>'
        );

        // M11: motivazione della rettifica, stampata nel documento.
        $motivazione = '';

        if ($s->rettifica_repertorio && trim((string) $s->note_rettifica) !== '') {
            $motivazione = '<p class="motivazione"><strong>'
                . $e($lang->_('COM_ALBOONLINE_REFERTO_NOTE_RETTIFICA')) . '</strong> '
                . $e($s->note_rettifica) . '</p>';
        }

        $sottotitoloHtml = $sottotitolo !== ''
            ? '<p class="sub">' . $e($sottotitolo) . '</p>'
            : '';

        // Valori precompilati per il heredoc (niente chiamate a funzione in interpolazione).
        $langAttr        = $e($lang->getTag() ?: 'it-IT');
        $enteHtml        = $e($s->ente_emanante ?: '—');
        $titoloHtml      = $e($titolo);
        $luogoDataHtml   = $e(sprintf($lang->_('COM_ALBOONLINE_REFERTO_LUOGO_DATA'), $fmt($s->emesso_at)));
        $respLabelHtml   = $e($lang->_('COM_ALBOONLINE_REFERTO_RESPONSABILE'));

        return <<<HTML
<html lang="{$langAttr}">
<head><meta charset="utf-8"><style>
body { font-family: serif; color: #1a1a1a; font-size: 12pt; }
.doc { padding: 8px; }
.head { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 18px; }
.ente { font-size: 12pt; font-weight: bold; margin: 0 0 8px; text-transform: uppercase; }
.title { font-size: 18pt; margin: 4px 0; letter-spacing: 1px; }
.sub { font-size: 10pt; color: #444; margin: 4px 0 0; }
table.tab { width: 100%; border-collapse: collapse; margin: 16px 0; }
table.tab th, table.tab td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #ccc; vertical-align: top; }
table.tab th { width: 34%; font-weight: bold; color: #333; }
.attesta { line-height: 1.7; margin: 18px 0; text-align: justify; }
.motivazione { line-height: 1.6; margin: 10px 0; }
.foot { margin-top: 48px; width: 100%; }
.foot td { vertical-align: bottom; }
.firma { text-align: center; }
.firma-label { font-size: 10pt; color: #444; }
/* Spazio libero per la firma (a penna o digitale) e riga su cui firmare.
   Niente nome stampato: il responsabile e' gia' un dato in tabella.
   L'<hr> (non un border su div) perche' mPDF lo rende a tutta larghezza;
   il margine sopra crea lo spazio per firmare (un div vuoto in mPDF collassa). */
.firma-hr { width: 85%; border: none; border-top: 1px solid #333; margin: 48px auto 0; }
</style></head>
<body>
<div class="doc">
    <div class="head">
        <p class="ente">{$enteHtml}</p>
        <h1 class="title">{$titoloHtml}</h1>
        {$sottotitoloHtml}
    </div>
    <table class="tab"><tbody>{$tabella}</tbody></table>
    <p class="attesta">{$attestazione}</p>
    {$motivazione}
    <table class="foot"><tr>
        <td>{$luogoDataHtml}</td>
        <td class="firma" style="width:45%;">
            <div class="firma-label">{$respLabelHtml}</div>
            <hr class="firma-hr">
        </td>
    </tr></table>
</div>
</body>
</html>
HTML;
    }

    private function generatePdf(string $html): string
    {
        require_once JPATH_ADMINISTRATOR . '/components/com_alboonline/vendor/autoload.php';

        $tmp = Factory::getApplication()->get('tmp_path', sys_get_temp_dir());

        $mpdf = new \Mpdf\Mpdf([
            'mode'        => 'utf-8',
            'format'      => 'A4',
            'tempDir'     => $tmp,
            'margin_top'  => 18,
            'margin_bottom' => 18,
            'margin_left' => 20,
            'margin_right' => 20,
        ]);
        $mpdf->SetTitle('Referto di pubblicazione');
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    private function writeFile(string $bytes, string $hash): string
    {
        $dir = JPATH_ROOT . self::REFERTI_DIR;

        if (!is_dir($dir)) {
            Folder::create($dir);
            file_put_contents($dir . '/.htaccess', "Require all denied\n");
            file_put_contents($dir . '/index.html', '<html><body></body></html>');
        }

        $filename = $hash . '.pdf';

        if (file_put_contents($dir . '/' . $filename, $bytes) === false) {
            throw new \RuntimeException('Referto: impossibile scrivere il PDF in ' . $dir);
        }

        return $filename;
    }

    private function logEmissione(int $attoId, int $actorId, string $actorName, string $now, string $repertorio): void
    {
        $record = (object) [
            'atto_id'     => $attoId,
            'azione'      => 'referto_emesso',
            'stato_prima' => null,
            'stato_dopo'  => null,
            'user_id'     => $actorId,
            'user_name'   => $actorName,
            'ip_address'  => '',
            'note'        => 'Referto di pubblicazione emesso (rep. ' . $repertorio . ')',
            'created'     => $now,
        ];

        $this->db->insertObject('#__alboonline_log', $record);
    }
}
