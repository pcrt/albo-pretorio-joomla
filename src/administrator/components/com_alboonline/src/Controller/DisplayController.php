<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

class DisplayController extends BaseController
{
	protected $default_view = 'atti';

	public function display($cachable = false, $urlparams = [])
	{
		return parent::display();
	}
}
