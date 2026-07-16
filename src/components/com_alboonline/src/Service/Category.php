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

use Joomla\CMS\Categories\Categories;

class Category extends Categories
{
	public function __construct($options = [])
	{
		$options['table']     = '#__alboonline_atti';
		$options['extension'] = 'com_alboonline';

		parent::__construct($options);
	}
}
