<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\Extension;

defined('_JEXEC') or die;

use Pcrt\Component\Alboonline\Administrator\Service\Html\AlboonlineHtml;
use Joomla\CMS\Categories\CategoryServiceInterface;
use Joomla\CMS\Categories\CategoryServiceTrait;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\CMS\Tag\TagServiceTrait;
use Psr\Container\ContainerInterface;

class AlboonlineComponent extends MVCComponent implements RouterServiceInterface, BootableExtensionInterface, CategoryServiceInterface
{
	use RouterServiceTrait;
	use HTMLRegistryAwareTrait;
	use CategoryServiceTrait, TagServiceTrait {
		CategoryServiceTrait::getTableNameForSection insteadof TagServiceTrait;
		CategoryServiceTrait::getStateColumnForSection insteadof TagServiceTrait;
	}

	public function boot(ContainerInterface $container)
	{
		$db = $container->get(\Joomla\Database\DatabaseInterface::class);
		$this->getRegistry()->register('alboonline', new AlboonlineHtml($db));
	}

	protected function getTableNameForSection(string $section = null)
	{
		return '#__alboonline_atti';
	}

	public function countItems(array $items, string $section)
	{
		$db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

		foreach ($items as $item)
		{
			$item->count_trashed     = 0;
			$item->count_archived    = 0;
			$item->count_unpublished = 0;
			$item->count_published   = 0;

			$query = $db->getQuery(true)
				->select('state, COUNT(*) AS count')
				->from('#__alboonline_atti')
				->where('catid = ' . (int) $item->id)
				->group('state');
			$db->setQuery($query);
			$rows = $db->loadObjectList();

			foreach ($rows as $row)
			{
				switch ((int) $row->state)
				{
					case 1:
						$item->count_published = $row->count;
						break;
					case 0:
						$item->count_unpublished = $row->count;
						break;
					case 2:
						$item->count_archived = $row->count;
						break;
					case -2:
						$item->count_trashed = $row->count;
						break;
				}
			}
		}
	}
}
