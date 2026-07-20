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

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;

class ModifiedbyField extends FormField
{
	protected $type = 'modifiedby';

	protected function getInput()
	{
		$html = [];
		$user = Factory::getApplication()->getIdentity();
		$html[] = '<input type="hidden" name="' . $this->name . '" value="' . $user->id . '" />';

		if (!$this->hidden)
		{
			// Escape: nome e username sono scelti dall'utente e finiscono come HTML.
			$nome = htmlspecialchars((string) $user->name, ENT_QUOTES, 'UTF-8');
			$userName = htmlspecialchars((string) $user->username, ENT_QUOTES, 'UTF-8');
			$html[] = "<div>" . $nome . " (" . $userName . ")</div>";
		}

		return implode($html);
	}
}
