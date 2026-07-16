<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper;

class AttoController extends FormController
{
	protected $view_list = 'atti';

	public function publish()
	{
		$this->checkToken('request');

		$id = $this->input->getInt('id');
		$return = $this->input->getCmd('return', 'edit');
		$model = $this->getModel();

		if ($model->publishAtto($id))
		{
			$this->setMessage(Text::_('COM_ALBOONLINE_ATTO_PUBLISHED'));
		}
		else
		{
			$this->setMessage($model->getError(), 'error');
		}

		if ($return === 'list')
		{
			$this->setRedirect(Route::_('index.php?option=com_alboonline&view=atti', false));
		}
		else
		{
			$this->setRedirect(Route::_('index.php?option=com_alboonline&view=atto&layout=edit&id=' . $id, false));
		}
	}

	/**
	 * Carica gli allegati scelti e torna alla scheda, senza salvare il resto dell'atto.
	 *
	 * E' il pulsante «Carica» della scheda allegati. Fa SOLO questo: non salva gli altri
	 * campi e non cancella niente, altrimenti sarebbe un doppione del Salva della barra.
	 */
	public function uploadAllegati()
	{
		$this->checkToken();

		$id       = $this->input->getInt('id');
		$user     = Factory::getApplication()->getIdentity();
		$redirect = Route::_('index.php?option=com_alboonline&view=atto&layout=edit&id=' . $id, false);

		if (!$user->authorise('core.edit', 'com_alboonline'))
		{
			$this->setMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			$this->setRedirect($redirect);

			return;
		}

		$model = $this->getModel();
		$n     = $model->uploadAllegati($id);

		// Zero file caricati non e' per forza un errore: i motivi dello scarto li ha gia'
		// messi a video uploadAllegati(), file per file. Qui non si aggiunge rumore.
		if ($n > 0)
		{
			$this->setMessage(Text::plural('COM_ALBOONLINE_ALLEGATI_CARICATI_N', $n));
		}

		$this->setRedirect($redirect);
	}

	/**
	 * Elimina un solo allegato e torna alla scheda.
	 *
	 * E' il pulsante «Elimina» della riga. Non passa dal form: cosi' cancella quell'unico
	 * allegato senza caricare i file eventualmente scelti nel selettore qui sotto.
	 */
	public function deleteAllegato()
	{
		$this->checkToken('request');

		$id         = $this->input->getInt('id');
		$allegatoId = $this->input->getInt('allegato_id');
		$user       = Factory::getApplication()->getIdentity();
		$redirect   = Route::_('index.php?option=com_alboonline&view=atto&layout=edit&id=' . $id, false);

		if (!$user->authorise('core.edit', 'com_alboonline'))
		{
			$this->setMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			$this->setRedirect($redirect);

			return;
		}

		$model = $this->getModel();

		if ($model->deleteAllegato($allegatoId, $id))
		{
			$this->setMessage(Text::_('COM_ALBOONLINE_ALLEGATO_ELIMINATO'));
		}
		else
		{
			$this->setMessage($model->getError() ?: Text::_('COM_ALBOONLINE_ERROR_FILE_UPLOAD'), 'error');
		}

		$this->setRedirect($redirect);
	}

	public function cancelPublish()
	{
		$this->checkToken();

		$id = $this->input->getInt('id');
		$model = $this->getModel();

		if ($model->cancelPublish($id))
		{
			$this->setMessage(Text::_('COM_ALBOONLINE_ATTO_PUBLISH_CANCELLED'));
		}
		else
		{
			$this->setMessage($model->getError(), 'error');
		}

		$this->setRedirect(Route::_('index.php?option=com_alboonline&view=atto&layout=edit&id=' . $id, false));
	}

	/**
	 * Revoca del diritto all'oblio, con motivazione obbligatoria e tracciata.
	 * E' il presupposto per riportare in pubblicazione un atto rimosso su richiesta
	 * dell'interessato. Vedi M16 in docs/gap-analysis/05-collaudo-manuale.md
	 */
	public function revokeOblio()
	{
		$this->checkToken();

		$id     = $this->input->getInt('id');
		$motivo = $this->input->getString('motivo_revoca_oblio', '');
		$model  = $this->getModel();

		if ($model->revokeOblio($id, $motivo))
		{
			$this->setMessage(Text::_('COM_ALBOONLINE_OBLIO_REVOKED'));
		}
		else
		{
			$this->setMessage($model->getError(), 'error');
		}

		$this->setRedirect(Route::_('index.php?option=com_alboonline&view=atto&layout=edit&id=' . $id, false));
	}

	/**
	 * Ripara un atto orfano assegnandogli una categoria (M30): l'unica modifica di categoria
	 * ammessa su un atto blindato, e solo quando quella attuale non esiste più.
	 */
	public function riassegnaCategoria()
	{
		$this->checkToken();

		$id    = $this->input->getInt('id');
		$catId = $this->input->getInt('nuova_categoria');
		$model = $this->getModel();

		if ($model->riassegnaCategoria($id, $catId))
		{
			$this->setMessage(Text::_('COM_ALBOONLINE_CATEGORIA_RIASSEGNATA'));
		}
		else
		{
			$this->setMessage($model->getError(), 'error');
		}

		$this->setRedirect(Route::_('index.php?option=com_alboonline&view=atto&layout=edit&id=' . $id, false));
	}

	/**
	 * Attivazione del diritto all'oblio su richiesta dell'interessato (art. 17 GDPR),
	 * con motivazione obbligatoria e tracciata. Funziona anche su atti già pubblicati
	 * o archiviati, dove il campo del form è disabilitato. Vedi S4-T4.
	 */
	public function attivaOblio()
	{
		$this->checkToken();

		$id     = $this->input->getInt('id');
		$motivo = $this->input->getString('motivo_attiva_oblio', '');
		$model  = $this->getModel();

		if ($model->attivaOblio($id, $motivo))
		{
			$this->setMessage(Text::_('COM_ALBOONLINE_OBLIO_ATTIVATO'));
		}
		else
		{
			$this->setMessage($model->getError(), 'error');
		}

		$this->setRedirect(Route::_('index.php?option=com_alboonline&view=atto&layout=edit&id=' . $id, false));
	}

	public function extendPublishDown()
	{
		$this->checkToken();

		$id = $this->input->getInt('id');
		$newDate = $this->input->getString('extend_publish_down', '');
		$model = $this->getModel();

		if (empty($newDate))
		{
			// Campo vuoto e data nel passato sono errori diversi: dire "deve essere nel
			// futuro" a chi non ha scritto nulla e' fuorviante. Vedi M8.
			$this->setMessage(Text::_('COM_ALBOONLINE_ERROR_EXTEND_DATE_REQUIRED'), 'error');
		}
		else
		{
			// Check if archived before extend (for correct success message)
			$table = $model->getTable();
			$table->load($id);
			$wasArchived = ((int) $table->stato_atto === AlboonlineHelper::STATO_ARCHIVIATO);

			if ($model->extendPublishDown($id, $newDate))
			{
				$msgKey = $wasArchived
					? 'COM_ALBOONLINE_PUBLISH_DOWN_REACTIVATED'
					: 'COM_ALBOONLINE_PUBLISH_DOWN_EXTENDED';
				$this->setMessage(Text::_($msgKey));
			}
			else
			{
				$this->setMessage($model->getError(), 'error');
			}
		}

		$this->setRedirect(Route::_('index.php?option=com_alboonline&view=atto&layout=edit&id=' . $id, false));
	}

	public function saveVisibilita()
	{
		$this->checkToken();

		$id = $this->input->getInt('id');
		$value = $this->input->getString('visibilita_home', 'nessuna');
		$model = $this->getModel();

		if ($model->saveVisibilita($id, $value))
		{
			$this->setMessage(Text::_('JLIB_APPLICATION_SAVE_SUCCESS'));
		}
		else
		{
			$this->setMessage($model->getError(), 'error');
		}

		$this->setRedirect(Route::_('index.php?option=com_alboonline&view=atto&layout=edit&id=' . $id, false));
	}

	public function rectify()
	{
		$this->checkToken();

		$id = $this->input->getInt('id');
		$model = $this->getModel();

		$newId = $model->rectify($id);

		if ($newId !== null)
		{
			$this->setMessage(Text::sprintf('COM_ALBOONLINE_ATTO_RECTIFIED', $newId));
			$this->setRedirect(Route::_('index.php?option=com_alboonline&view=atto&layout=edit&id=' . $newId, false));
		}
		else
		{
			$this->setMessage($model->getError(), 'error');
			$this->setRedirect(Route::_('index.php?option=com_alboonline&view=atto&layout=edit&id=' . $id, false));
		}
	}
}
