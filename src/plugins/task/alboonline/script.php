<?php
/**
 * @package    Pcrt.Plugin
 * @subpackage Task.Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2026 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerAdapter;

class PlgTaskAlboonlineInstallerScript
{
	public function postflight($type, InstallerAdapter $parent)
	{
		$this->regenerateNamespaceMap();
		return true;
	}

	private function regenerateNamespaceMap()
	{
		try
		{
			if (class_exists('JNamespacePsr4Map'))
			{
				$map = new \JNamespacePsr4Map();
				$map->create();
				return;
			}

			if (file_exists(JPATH_LIBRARIES . '/namespacemap.php'))
			{
				require_once JPATH_LIBRARIES . '/namespacemap.php';

				if (class_exists('JNamespacePsr4Map'))
				{
					$map = new \JNamespacePsr4Map();
					$map->create();
				}
			}
		}
		catch (\Throwable $e)
		{
			// Namespace map will be regenerated on next page load
		}
	}
}
