<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\CategoryFactory;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Pcrt\Component\Alboonline\Administrator\Extension\AlboonlineComponent;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface
{
	public function register(Container $container)
	{
		$container->registerServiceProvider(new CategoryFactory('\\Pcrt\\Component\\Alboonline'));
		$container->registerServiceProvider(new MVCFactory('\\Pcrt\\Component\\Alboonline'));
		$container->registerServiceProvider(new ComponentDispatcherFactory('\\Pcrt\\Component\\Alboonline'));
		$container->registerServiceProvider(new RouterFactory('\\Pcrt\\Component\\Alboonline'));

		$container->set(
			ComponentInterface::class,
			function (Container $container)
			{
				$component = new AlboonlineComponent($container->get(ComponentDispatcherFactoryInterface::class));
				$component->setRegistry($container->get(Registry::class));
				$component->setMVCFactory($container->get(MVCFactoryInterface::class));
				$component->setCategoryFactory($container->get(CategoryFactoryInterface::class));
				$component->setRouterFactory($container->get(RouterFactoryInterface::class));

				return $component;
			}
		);
	}
};
