<?php
/**
 * @package    Plg_Task_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Pcrt\Plugin\Task\Alboonline\Extension\AlboonlineTask;

return new class () implements ServiceProviderInterface {
	public function register(Container $container)
	{
		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$plugin = new AlboonlineTask(
					$container->get(\Joomla\Event\DispatcherInterface::class),
					(array) PluginHelper::getPlugin('task', 'alboonline')
				);
				$plugin->setDatabase($container->get(DatabaseInterface::class));
				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			}
		);
	}
};
