<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper;

class AttiModel extends ListModel
{
	public function __construct($config = [])
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = [
				'id', 'a.id',
				'titolo', 'a.titolo',
				'numero_protocollo', 'a.numero_protocollo',
				'tipo_atto', 'a.tipo_atto',
				'data_atto', 'a.data_atto',
				'publish_up', 'a.publish_up',
				'catid', 'a.catid',
				'anno_protocollo', 'a.anno_protocollo',
				'ordering', 'a.ordering',
				'fullordering',
			];
		}

		parent::__construct($config);
	}

	protected function populateState($ordering = null, $direction = null)
	{
		$app = Factory::getApplication();

		$params = $app->getParams();
		$this->setState('params', $params);

		// La categoria arriva dall'albero come parametro GET. `null` = non c'e' nel link
		// (es. paginazione): in quel caso NON si tocca il filtro, che resta quello scelto.
		// `0` = c'e' ed e' esplicitamente vuoto: e' il link «Tutte le categorie», e deve
		// AZZERARE il filtro.
		//
		// Prima si guardava solo `catid > 0`: cliccare «Tutte le categorie» non azzerava
		// nulla e il filtro restava incollato nella sessione (bastava aver premuto una volta
		// «Applica» con una categoria scelta). Il cittadino restava chiuso in una categoria
		// senza via d'uscita — e, rimossa la tendina doppione, senza nemmeno un altro modo
		// per liberarsi.
		$catid = $app->input->get('catid', null, 'int');

		if ($catid !== null)
		{
			// Non basta impostare lo stato della RICHIESTA: Joomla conserva i filtri in
			// sessione (array `filter` del contesto) e alla richiesta successiva li ripesca.
			// Se non si aggiorna anche quello, il filtro azzerato ricompare al primo
			// caricamento senza parametri — e il cittadino si ritrova di nuovo chiuso dentro
			// la categoria, senza aver fatto nulla.
			$filtri          = (array) $app->getUserState($this->context . '.filter', []);
			$filtri['catid'] = $catid;
			$app->setUserState($this->context . '.filter', $filtri);

			$this->setState('filter.catid', $catid);
		}

		parent::populateState('a.publish_up', 'DESC');

		// Dopo il genitore (che rilegge lo stato di sessione), l'URL deve avere l'ultima parola.
		if ($catid !== null)
		{
			$this->setState('filter.catid', $catid);
		}
	}

	protected function getStoreId($id = '')
	{
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.catid');
		$id .= ':' . $this->getState('filter.tipo_atto');
		$id .= ':' . $this->getState('filter.anno_protocollo');

		return parent::getStoreId($id);
	}

	protected function getListQuery()
	{
		$db = $this->getDbo();
		$query = $db->getQuery(true);
		$user = Factory::getApplication()->getIdentity();

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

		// Filter by stato_atto: archive-only mode or normal
		$params = $this->getState('params');
		$soloArchiviati = $params ? (int) $params->get('solo_archiviati', 0) : 0;

		if ($soloArchiviati)
		{
			$query->where('a.stato_atto = ' . AlboonlineHelper::STATO_ARCHIVIATO);
		}
		else
		{
			// Elenco "Albo Online" mostra solo atti pubblicati (stato 2).
			// Gli atti archiviati (stato 3) sono visibili solo dal menu "Archivio Atti".
			$query->where('a.stato_atto = ' . AlboonlineHelper::STATO_PUBBLICATO);
		}

		// Joomla state must be published or archived
		$query->where('a.state IN (1, 2)');

		// Escludi atti archiviati con diritto all'oblio
		$query->where(
			'NOT (' . $db->quoteName('a.stato_atto') . ' = 3'
			. ' AND ' . $db->quoteName('a.diritto_oblio') . ' = 1)'
		);

		// Access filtering
		$accessLevels = $user->getAuthorisedViewLevels();
		$query->where('a.access IN (' . implode(',', $accessLevels) . ')');

		// Language filtering
		$query->where('a.language IN (' . $db->quote(Factory::getApplication()->getLanguage()->getTag()) . ',' . $db->quote('*') . ')');

		// Filter: category
		$catid = $this->getState('filter.catid');

		if (is_numeric($catid) && (int) $catid > 0)
		{
			// Include child categories
			$subQuery = $db->getQuery(true)
				->select('sub.id')
				->from($db->quoteName('#__categories', 'sub'))
				->join('INNER', $db->quoteName('#__categories', 'this') . ' ON sub.lft >= this.lft AND sub.rgt <= this.rgt')
				->where('this.id = ' . (int) $catid);

			$query->where('a.catid IN (' . $subQuery . ')');
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

		// Filter: search
		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			$search = $db->quote('%' . $db->escape($search, true) . '%');
			$query->where('(a.titolo LIKE ' . $search
				. ' OR a.oggetto LIKE ' . $search
				. ' OR a.numero_protocollo LIKE ' . $search . ')');
		}

		// Ordering
		$orderCol = $this->state->get('list.ordering', 'a.publish_up');
		$orderDirn = $this->state->get('list.direction', 'DESC');

		$query->order($db->escape($orderCol . ' ' . $orderDirn));

		return $query;
	}

	/**
	 * Returns distinct years from anno_protocollo for the filter dropdown.
	 */
	public function getYears(): array
	{
		$db = $this->getDbo();

		$query = $db->getQuery(true)
			->select('DISTINCT ' . $db->quoteName('anno_protocollo'))
			->from($db->quoteName('#__alboonline_atti'))
			->where($db->quoteName('anno_protocollo') . ' IS NOT NULL')
			->where($db->quoteName('stato_atto') . ' IN (2, 3)')
			->where($db->quoteName('state') . ' IN (1, 2)')
			->order($db->quoteName('anno_protocollo') . ' DESC');

		$db->setQuery($query);

		return $db->loadColumn() ?: [];
	}
}
