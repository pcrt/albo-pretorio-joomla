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
use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper;

class StatoattoField extends FormField
{
	protected $type = 'statoatto';

	protected function getInput()
	{
		$stato = (int) $this->value;
		$label = AlboonlineHelper::getStatoLabel($stato);
		$badgeClass = AlboonlineHelper::getStatoBadgeClass($stato);

		return '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($label) . '</span>'
			. '<input type="hidden" name="' . $this->name . '" value="' . $stato . '" />';
	}
}
