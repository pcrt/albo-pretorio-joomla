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
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class CategoriesModel extends BaseDatabaseModel
{
	/**
	 * Categorie dell'albo, col numero di atti che il visitatore troverà davvero.
	 *
	 * Il conteggio deve corrispondere a CIÒ CHE L'ELENCO MOSTRERÀ (M29). Prima contava
	 * pubblicati + archiviati insieme, ma la vista Albo mostra solo i pubblicati e la vista
	 * Archivio solo gli archiviati: una categoria dichiarava «4» e cliccandoci se ne vedevano
	 * 3. Un numero che non torna è peggio di nessun numero.
	 *
	 * @param  bool  $soloArchiviati  true nella vista Archivio (param `solo_archiviati`).
	 */
	public function getCategories(bool $soloArchiviati = false)
	{
		$db = $this->getDbo();
		$user = Factory::getApplication()->getIdentity();

		// Stessi criteri di AttiModel::getListQuery(): pubblicati (stato 2, state 1) nella
		// vista Albo; archiviati consultabili (stato 3, state 2) nella vista Archivio. Gli
		// atti in oblio hanno state = 0 e restano fuori da entrambi i conteggi: il numero
		// non rivela mai l'esistenza di un atto rimosso.
		$condizioneAtti = $soloArchiviati
			? ' AND a.stato_atto = 3 AND a.state = 2'
			: ' AND a.stato_atto = 2 AND a.state = 1';

		$query = $db->getQuery(true)
			->select([
				'c.id',
				'c.title',
				'c.alias',
				'c.level',
				'c.lft',
				'c.rgt',
				'c.parent_id',
				'COUNT(a.id) AS num_atti',
			])
			->from($db->quoteName('#__categories', 'c'))
			->join('LEFT', $db->quoteName('#__alboonline_atti', 'a')
				. ' ON a.catid = c.id'
				. $condizioneAtti)
			->where($db->quoteName('c.extension') . ' = ' . $db->quote('com_alboonline'))
			->where($db->quoteName('c.published') . ' = 1')
			->where('c.access IN (' . implode(',', $user->getAuthorisedViewLevels()) . ')')
			->group('c.id, c.title, c.alias, c.level, c.lft, c.rgt, c.parent_id')
			->order('c.lft ASC');

		$db->setQuery($query);

		return $db->loadObjectList();
	}

	public function buildTree(array $categories, int $parentId = 1): array
	{
		$tree = [];

		foreach ($categories as $cat)
		{
			if ((int) $cat->parent_id === $parentId)
			{
				$cat->children = $this->buildTree($categories, (int) $cat->id);
				$tree[] = $cat;
			}
		}

		return $tree;
	}
}
