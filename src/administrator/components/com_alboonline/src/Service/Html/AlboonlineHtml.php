<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\Service\Html;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Utilities\ArrayHelper;

class AlboonlineHtml
{
	use DatabaseAwareTrait;

	public function __construct(DatabaseDriver $db)
	{
		// Joomla 6: il trait CMS DatabaseAwareTrait (setDbo) è rimosso →
		// Joomla\Database\DatabaseAwareTrait (setDatabase)
		$this->setDatabase($db);
	}

	public function toggle($value = 0, $view = '', $field = '', $i = '')
	{
		$states = [
			0 => ['icon-unpublish', Text::_('Toggle'), ''],
			1 => ['icon-publish', Text::_('Toggle'), ''],
		];

		$state = ArrayHelper::getValue($states, (int) $value, $states[0]);
		$text = '<span aria-hidden="true" class="' . $state[0] . '"></span>';
		$html = '<a href="javascript:void(0);" class="tbody-icon ' . $state[2] . '"';
		$html .= 'onclick="return Joomla.toggleField(\'cb' . $i . '\',\'' . $view . '.toggle\',\'' . $field . '\')" title="' . Text::_($state[1]) . '">' . $text . '</a>';

		return $html;
	}
}
