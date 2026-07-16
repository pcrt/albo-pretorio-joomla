<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\Field;

defined('JPATH_BASE') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

class ProtocolloField extends FormField
{
	protected $type = 'protocollo';

	protected function getInput()
	{
		if (empty($this->value))
		{
			return '<span class="badge bg-secondary">' . Text::_('COM_ALBOONLINE_NOT_ASSIGNED') . '</span>';
		}

		return '<span class="badge bg-primary fw-bold">' . htmlspecialchars($this->value) . '</span>'
			. '<input type="hidden" name="' . $this->name . '" value="' . htmlspecialchars($this->value) . '" />';
	}
}
