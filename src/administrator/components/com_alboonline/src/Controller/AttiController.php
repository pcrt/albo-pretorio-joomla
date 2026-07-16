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

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\Utilities\ArrayHelper;

class AttiController extends AdminController
{
	public function getModel($name = 'Atto', $prefix = 'Administrator', $config = [])
	{
		return parent::getModel($name, $prefix, ['ignore_request' => true]);
	}

	public function saveOrderAjax()
	{
		$pks = $this->input->post->get('cid', [], 'array');
		$order = $this->input->post->get('order', [], 'array');

		ArrayHelper::toInteger($pks);
		ArrayHelper::toInteger($order);

		$model = $this->getModel();
		$return = $model->saveorder($pks, $order);

		if ($return)
		{
			echo "1";
		}

		Factory::getApplication()->close();
	}
}
