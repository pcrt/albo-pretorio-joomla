<?php
/**
 * @package    Com_Alboonline
 * @copyright  2025
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper;

/**
 * Referto di pubblicazione (M18).
 *
 * NON genera nulla al volo: serve i documenti GIA' EMESSI e sigillati. Il PDF
 * scaricato e' byte-identico a quello prodotto alla defissione (stesso hash),
 * cosi' l'ente puo' stamparlo, firmarlo a penna e archiviarlo.
 */
class RefertoController extends BaseController
{
    /**
     * Scarica il PDF congelato di un referto (per id di referto).
     * Accesso riservato: alboonline.download.archived.
     */
    public function download()
    {
        $this->checkToken('get');

        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('alboonline.download.archived', 'com_alboonline')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $id = $this->input->getInt('id', 0);

        if (!$id) {
            throw new \Exception(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 404);
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'atto_id', 'numero_repertorio', 'pdf_filename', 'pdf_hash', 'stato', 'firmato_filename', 'firmato_mime']))
            ->from($db->quoteName('#__alboonline_referti'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);
        $db->setQuery($query);
        $referto = $db->loadObject();

        if (!$referto) {
            throw new \Exception(Text::_('COM_ALBOONLINE_REFERTO_NON_TROVATO'), 404);
        }

        // Se richiesto e disponibile, serve la versione firmata; altrimenti l'emesso.
        $variante = $this->input->getCmd('variante', 'emesso');
        $useFirmato = ($variante === 'firmato' && !empty($referto->firmato_filename));

        $filename = $useFirmato ? $referto->firmato_filename : $referto->pdf_filename;
        $filePath = JPATH_ROOT . '/media/com_alboonline/referti/' . $filename;

        if ($filename === '' || !is_file($filePath)) {
            throw new \Exception(Text::_('COM_ALBOONLINE_REFERTO_FILE_MANCANTE'), 404);
        }

        // Integrità: il PDF emesso deve corrispondere all'hash congelato.
        // (La versione firmata NON viene ricontrollata: il file cambia con la firma.)
        if (!$useFirmato && !empty($referto->pdf_hash)) {
            if (hash_file('sha256', $filePath) !== $referto->pdf_hash) {
                throw new \Exception(Text::_('COM_ALBOONLINE_ERROR_INTEGRITY_FAILED'), 500);
            }
        }

        AlboonlineHelper::logAction((int) $referto->atto_id, 'referto_downloaded');

        $mime = $useFirmato ? ($referto->firmato_mime ?: 'application/octet-stream') : 'application/pdf';
        $downloadName = $this->buildDownloadName($referto, $useFirmato, $filename);

        $app->clearHeaders();
        $app->setHeader('Content-Type', $mime);
        $app->setHeader('Content-Disposition', 'attachment; filename="' . $downloadName . '"');
        $app->setHeader('Content-Length', (string) filesize($filePath));
        $app->setHeader('X-Content-Type-Options', 'nosniff');
        $app->setHeader('Cache-Control', 'private, no-store');
        $app->sendHeaders();

        readfile($filePath);
        $app->close();
    }

    /**
     * Ricarica la versione FIRMATA di un referto (POST). L'emesso resta intatto.
     * Riservato al responsabile della pubblicazione: alboonline.publish.
     */
    public function uploadSigned()
    {
        $this->checkToken();

        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        $refertoId = $this->input->getInt('id', 0);
        $attoId    = $this->input->getInt('atto_id', 0);
        $redirect  = Route::_('index.php?option=com_alboonline&view=referto&id=' . $attoId . '&tmpl=component', false);

        if (!$user->authorise('alboonline.publish', 'com_alboonline')) {
            $this->setRedirect($redirect, Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        if (!$refertoId) {
            $this->setRedirect($redirect, Text::_('COM_ALBOONLINE_REFERTO_NON_TROVATO'), 'error');

            return;
        }

        $file = $this->input->files->get('signed_file', null, 'raw');

        if (!\is_array($file) || empty($file['name'])) {
            $this->setRedirect($redirect, Text::_('COM_ALBOONLINE_REFERTO_FIRMATO_NESSUN_FILE'), 'error');

            return;
        }

        /** @var \Pcrt\Component\Alboonline\Administrator\Model\RefertoModel $model */
        $model = $this->getModel('Referto', 'Administrator');

        if ($model->attachSigned($refertoId, $file, (int) $user->id)) {
            $this->setRedirect($redirect, Text::_('COM_ALBOONLINE_REFERTO_FIRMATO_OK'));
        } else {
            $this->setRedirect($redirect, $model->getError() ?: Text::_('COM_ALBOONLINE_ERROR_FILE_UPLOAD'), 'error');
        }
    }

    /**
     * Nome file leggibile per il download (basato sul repertorio, non sull'hash).
     */
    private function buildDownloadName(object $referto, bool $useFirmato, string $stored): string
    {
        $rep = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $referto->numero_repertorio);
        $ext = pathinfo($stored, PATHINFO_EXTENSION) ?: 'pdf';
        $base = 'referto-' . ($rep !== '' ? $rep : (int) $referto->id);

        if ($useFirmato) {
            $base .= '-firmato';
        }

        return $base . '.' . $ext;
    }
}
