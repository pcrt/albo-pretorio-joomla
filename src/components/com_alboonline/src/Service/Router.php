<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Categories\CategoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\Database\DatabaseInterface;

class Router extends RouterView
{
	private $categoryFactory;
	private $categoryCache = [];
	private $noIDs;

	public function __construct(SiteApplication $app, AbstractMenu $menu, CategoryFactoryInterface $categoryFactory, DatabaseInterface $db)
	{
		$params = ComponentHelper::getParams('com_alboonline');
		$this->noIDs = (bool) $params->get('sef_ids');
		$this->categoryFactory = $categoryFactory;

		$atti = new RouterViewConfiguration('atti');
		$this->registerView($atti);

		$atto = new RouterViewConfiguration('atto');
		$atto->setKey('id')->setParent($atti);
		$this->registerView($atto);

		parent::__construct($app, $menu);

		$this->attachRule(new MenuRules($this));
		$this->attachRule(new StandardRules($this));
		$this->attachRule(new NomenuRules($this));
	}

	public function getAttoSegment($id, $query)
	{
		if (!strpos($id, ':'))
		{
			$db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
			$dbquery = $db->getQuery(true)
				->select($db->quoteName('alias'))
				->from($db->quoteName('#__alboonline_atti'))
				->where($db->quoteName('id') . ' = ' . (int) $id);
			$db->setQuery($dbquery);
			$alias = $db->loadResult();

			$id = $id . ':' . $alias;
		}

		list($intId, $alias) = explode(':', $id, 2);

		// Use id-alias format to guarantee uniqueness (like com_content)
		return [(int) $intId => (int) $intId . '-' . $alias];
	}

	public function getAttoId($segment, $query)
	{
		// Extract ID from id-alias format (e.g. "19-correlati-primo-documento")
		$id = (int) $segment;

		if ($id > 0)
		{
			return $id;
		}

		// Fallback: segment is a plain alias, look up the ID
		$db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$dbquery = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__alboonline_atti'))
			->where($db->quoteName('alias') . ' = :alias')
			->bind(':alias', $segment);
		$db->setQuery($dbquery);

		return (int) $db->loadResult();
	}

	private function getCategories(array $options = []): CategoryInterface
	{
		$key = serialize($options);

		if (!isset($this->categoryCache[$key]))
		{
			$this->categoryCache[$key] = $this->categoryFactory->createCategory($options);
		}

		return $this->categoryCache[$key];
	}
}
