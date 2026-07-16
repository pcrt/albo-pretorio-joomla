<?php
/**
 * @package    Com_Alboonline
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\View\Registro;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper;

/**
 * Registro cronologico delle pubblicazioni (repertorio d'albo).
 * Elenca gli atti che hanno ricevuto un numero di repertorio, in ordine cronologico.
 */
class HtmlView extends BaseHtmlView
{
	protected $items;
	protected $anno;
	protected $ente;
	protected $anni;

	public function display($tpl = null)
	{
		$app = Factory::getApplication();
		$this->anno = (int) $app->getInput()->getInt('anno', 0);
		$db = Factory::getContainer()->get(DatabaseInterface::class);

		// Anni disponibili (per il filtro)
		$qAnni = $db->getQuery(true)
			->select('DISTINCT ' . $db->quoteName('anno_protocollo'))
			->from($db->quoteName('#__alboonline_atti'))
			->where($db->quoteName('numero_protocollo') . ' IS NOT NULL')
			->where($db->quoteName('anno_protocollo') . ' IS NOT NULL')
			->order($db->quoteName('anno_protocollo') . ' DESC');
		$db->setQuery($qAnni);
		$this->anni = $db->loadColumn();

		// Atti con repertorio, in ordine cronologico (anno, progressivo).
		// Self-join su rettifica_di per il repertorio dell'atto rettificato (M10);
		// note_rettifica per la motivazione (M11).
		$query = $db->getQuery(true)
			->select($db->quoteName([
				'a.id', 'a.titolo', 'a.oggetto', 'a.tipo_atto', 'a.data_atto',
				'a.numero_protocollo', 'a.anno_protocollo', 'a.progressivo_protocollo',
				'a.stato_atto', 'a.publish_up', 'a.publish_down',
				'a.rettifica_di', 'a.note_rettifica',
			]))
			->select($db->quoteName('r.numero_protocollo', 'rettifica_repertorio'))
			->from($db->quoteName('#__alboonline_atti', 'a'))
			->leftJoin($db->quoteName('#__alboonline_atti', 'r') . ' ON r.id = a.rettifica_di')
			->where($db->quoteName('a.numero_protocollo') . ' IS NOT NULL');

		if ($this->anno > 0) {
			$query->where($db->quoteName('a.anno_protocollo') . ' = :anno')
				->bind(':anno', $this->anno, ParameterType::INTEGER);
		}

		$query->order($db->quoteName('a.anno_protocollo') . ' DESC')
			->order($db->quoteName('a.progressivo_protocollo') . ' DESC');
		$db->setQuery($query);
		$this->items = $db->loadObjectList();

		$this->ente = AlboonlineHelper::getEnteEmanante();

		parent::display($tpl);
	}
}
