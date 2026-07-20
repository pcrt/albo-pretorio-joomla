<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper;

class DownloadController extends BaseController
{
	public function file()
	{
		$this->checkToken('get');

		$app = Factory::getApplication();
		$id = $this->input->getInt('id', 0);
		$user = $app->getIdentity();

		if (!$id)
		{
			throw new \Exception(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 404);
		}

		$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

		// Try loading as allegato ID (new behavior)
		$query = $db->getQuery(true)
			->select([
				'al.id AS allegato_id', 'al.filename', 'al.original_name', 'al.hash',
				'al.size', 'al.mime', 'al.atto_id',
				'a.stato_atto', 'a.access', 'a.id AS atto_id_check',
			])
			->from($db->quoteName('#__alboonline_allegati', 'al'))
			->join('INNER', $db->quoteName('#__alboonline_atti', 'a') . ' ON a.id = al.atto_id')
			->where($db->quoteName('al.id') . ' = ' . $id);
		$db->setQuery($query);
		$allegato = $db->loadObject();

		// Fallback: if not found in allegati, try as atto_id and serve first allegato
		if (!$allegato)
		{
			$fallbackQuery = $db->getQuery(true)
				->select([
					'al.id AS allegato_id', 'al.filename', 'al.original_name', 'al.hash',
					'al.size', 'al.mime', 'al.atto_id',
					'a.stato_atto', 'a.access', 'a.id AS atto_id_check',
				])
				->from($db->quoteName('#__alboonline_allegati', 'al'))
				->join('INNER', $db->quoteName('#__alboonline_atti', 'a') . ' ON a.id = al.atto_id')
				->where($db->quoteName('al.atto_id') . ' = ' . $id)
				->order($db->quoteName('al.ordering') . ' ASC')
				->setLimit(1);
			$db->setQuery($fallbackQuery);
			$allegato = $db->loadObject();
		}

		if (!$allegato)
		{
			throw new \Exception(Text::_('COM_ALBOONLINE_ERROR_NO_ATTACHMENT'), 404);
		}

		// Controllo di accesso in base allo stato dell'atto.
		$statoAtto = (int) $allegato->stato_atto;

		// Il diritto all'oblio si consulta solo per gli atti archiviati (unico stato che lo
		// prevede) e richiede una lettura sull'atto: la facciamo qui, poi la DECISIONE di
		// visibilita' e' centralizzata in AlboonlineHelper::downloadDenial(), cosi' e'
		// collaudabile senza montare un'app HTTP (vedi scripts/test-download-visibilita.php).
		$dirittoOblio = false;

		if ($statoAtto === AlboonlineHelper::STATO_ARCHIVIATO)
		{
			$oblioQuery = $db->getQuery(true)
				->select($db->quoteName('diritto_oblio'))
				->from($db->quoteName('#__alboonline_atti'))
				->where($db->quoteName('id') . ' = ' . (int) $allegato->atto_id);
			$db->setQuery($oblioQuery);
			$dirittoOblio = ((int) $db->loadResult() === 1);
		}

		$denial = AlboonlineHelper::downloadDenial(
			$statoAtto,
			(int) $allegato->access,
			$dirittoOblio,
			$user->authorise('core.edit', 'com_alboonline'),
			$user->authorise('alboonline.download.archived', 'com_alboonline'),
			$user->getAuthorisedViewLevels()
		);

		if ($denial !== null)
		{
			// La decisione (consentito/negato + codice HTTP) sta nell'helper; qui scegliamo
			// solo il messaggio piu' adatto allo stato, preservando quelli storici.
			$msgKey = match ($statoAtto) {
				AlboonlineHelper::STATO_ARCHIVIATO => $dirittoOblio
					? 'JGLOBAL_RESOURCE_NOT_FOUND'
					: 'COM_ALBOONLINE_ERROR_ARCHIVED_NO_DOWNLOAD',
				AlboonlineHelper::STATO_CESTINATO => 'JERROR_AN_ERROR_HAS_OCCURRED',
				default                           => 'JERROR_ALERTNOAUTHOR',
			};

			throw new \Exception(Text::_($msgKey), $denial);
		}

		// Check file exists
		if (empty($allegato->filename))
		{
			throw new \Exception(Text::_('COM_ALBOONLINE_ERROR_NO_ATTACHMENT'), 404);
		}

		$filePath = JPATH_ROOT . '/media/com_alboonline/allegati/' . $allegato->filename;

		if (!file_exists($filePath))
		{
			throw new \Exception(Text::_('COM_ALBOONLINE_ERROR_FILE_NOT_FOUND'), 404);
		}

		// Verify integrity for locked acts
		if ($statoAtto >= AlboonlineHelper::STATO_PUBBLICATO && !empty($allegato->hash))
		{
			$fileHash = hash_file('sha256', $filePath);

			if ($fileHash !== $allegato->hash)
			{
				throw new \Exception(Text::_('COM_ALBOONLINE_ERROR_INTEGRITY_FAILED'), 500);
			}
		}

		// Log download
		AlboonlineHelper::logAction((int) $allegato->atto_id, 'downloaded');

		// Serve file
		$originalName = $allegato->original_name ?: $allegato->filename;
		$mime = $allegato->mime ?: 'application/pdf';

		// Il nome file originale e' scelto dall'uploader (backend fidato) e finisce QUOTATO
		// nell'header: una virgoletta o un a-capo lo romperebbe. Stessa sanificazione gia'
		// usata per i referti (RefertoController::buildDownloadName). PHP blocca l'iniezione
		// CRLF negli header, ma un valore pulito e' comunque la cosa giusta.
		$safeName = preg_replace('/[^A-Za-z0-9._-]/', '-', $originalName);
		$safeName = trim($safeName, '-') ?: 'documento.pdf';

		$app->clearHeaders();
		$app->setHeader('Content-Type', $mime);
		$app->setHeader('Content-Disposition', 'attachment; filename="' . $safeName . '"');
		$app->setHeader('Content-Length', filesize($filePath));
		$app->setHeader('X-Content-Type-Options', 'nosniff');
		$app->setHeader('Cache-Control', 'private, no-store');
		// Deindicizzazione (S4-T1): un PDF non ha meta tag, quindi il noindex per i
		// motori passa solo dall'header. Gli allegati d'albo non vanno indicizzati.
		$app->setHeader('X-Robots-Tag', 'noindex, noarchive');
		$app->sendHeaders();

		readfile($filePath);
		$app->close();
	}
}
