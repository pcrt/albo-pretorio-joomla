<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;

class AttiModel extends ListModel
{
	public function __construct($config = [])
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = [
				'id', 'a.id',
				'state', 'a.state',
				'stato_atto', 'a.stato_atto',
				'ordering', 'a.ordering',
				'created_by', 'a.created_by',
				'modified_by', 'a.modified_by',
				'titolo', 'a.titolo',
				'numero_protocollo', 'a.numero_protocollo',
				'tipo_atto', 'a.tipo_atto',
				'catid', 'a.catid',
				'data_atto', 'a.data_atto',
				'publish_up', 'a.publish_up',
				'publish_down', 'a.publish_down',
				'anno_protocollo', 'a.anno_protocollo',
				'codice_correlazione', 'a.codice_correlazione',
				'access', 'a.access',
				'language', 'a.language',
				'category_title',
			];
		}

		parent::__construct($config);
	}

	protected function populateState($ordering = null, $direction = null)
	{
		parent::populateState('a.id', 'DESC');

		$search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
		$this->setState('filter.search', $search);
	}

	protected function getStoreId($id = '')
	{
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.stato_atto');
		$id .= ':' . $this->getState('filter.catid');
		$id .= ':' . $this->getState('filter.tipo_atto');
		$id .= ':' . $this->getState('filter.anno_protocollo');
		$id .= ':' . $this->getState('filter.codice_correlazione');

		return parent::getStoreId($id);
	}

	protected function getListQuery()
	{
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		$query->select('DISTINCT a.*')
			->from($db->quoteName('#__alboonline_atti', 'a'));

		// Subselect: allegati count
		$subQuery = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__alboonline_allegati', 'al'))
			->where('al.atto_id = a.id');
		$query->select('(' . $subQuery . ') AS allegati_count');

		// Join category
		$query->select($db->quoteName('c.title', 'category_title'))
			->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = a.catid');

		// Join editor
		$query->select($db->quoteName('uc.name', 'uEditor'))
			->join('LEFT', $db->quoteName('#__users', 'uc') . ' ON uc.id = a.checked_out');

		// Join created_by user
		$query->select($db->quoteName('ua.name', 'author_name'))
			->join('LEFT', $db->quoteName('#__users', 'ua') . ' ON ua.id = a.created_by');

		// Stato dell'atto. Un solo concetto per l'operatore: lo stato del WORKFLOW legale
		// (M23 — prima c'erano due tendine chiamate entrambe «Stato», una sul `state`
		// tecnico di Joomla e una sul workflow, con vocabolari sovrapposti).
		//
		// Il cestino fa eccezione: non è uno stato del workflow, vive su `state = -2`
		// (`stato_atto` non vale mai -2). La voce «Cestinato» della tendina viene quindi
		// tradotta qui.
		$statoAtto = $this->getState('filter.stato_atto');

		if ((string) $statoAtto === '-2')
		{
			$query->where('a.state = -2');
		}
		elseif (is_numeric($statoAtto))
		{
			$query->where('a.stato_atto = ' . (int) $statoAtto)
				->where('a.state <> -2');
		}
		else
		{
			// Vista predefinita: TUTTO tranne il cestino.
			//
			// Prima era `state IN (0, 1)` (M24), che produceva un elenco alla rovescia:
			// nascondeva gli atti ARCHIVIATI e consultabili (state = 2) — cioè quelli da
			// conservare — e mostrava invece quelli in OBLIO (state = 0), cioè quelli
			// rimossi. Un funzionario che cercava un atto archiviato poteva concludere che
			// fosse stato cancellato.
			$query->where('a.state <> -2');
		}

		// Filter: category
		$catid = $this->getState('filter.catid');

		if (is_numeric($catid))
		{
			$query->where('a.catid = ' . (int) $catid);
		}

		// Filter: tipo_atto
		$tipoAtto = $this->getState('filter.tipo_atto');

		if (!empty($tipoAtto))
		{
			$query->where('a.tipo_atto = ' . $db->quote($tipoAtto));
		}

		// Filter: anno_protocollo
		$anno = $this->getState('filter.anno_protocollo');

		if (is_numeric($anno))
		{
			$query->where('a.anno_protocollo = ' . (int) $anno);
		}

		// Filter: codice_correlazione
		$correlazione = $this->getState('filter.codice_correlazione');

		if (!empty($correlazione))
		{
			$query->where('a.codice_correlazione LIKE ' . $db->quote('%' . $db->escape($correlazione, true) . '%'));
		}

		// Filter: search
		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			if (stripos($search, 'id:') === 0)
			{
				$query->where('a.id = ' . (int) substr($search, 3));
			}
			else
			{
				$search = $db->quote('%' . $db->escape($search, true) . '%');
				$query->where('(a.titolo LIKE ' . $search
					. ' OR a.oggetto LIKE ' . $search
					. ' OR a.numero_protocollo LIKE ' . $search . ')');
			}
		}

		// Ordering
		$orderCol = $this->state->get('list.ordering', 'a.id');
		$orderDirn = $this->state->get('list.direction', 'DESC');

		if ($orderCol && $orderDirn)
		{
			$query->order($db->escape($orderCol . ' ' . $orderDirn));
		}

		return $query;
	}

	public function getItems()
	{
		return parent::getItems();
	}
}
