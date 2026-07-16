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

use Joomla\CMS\Form\Field\ListField;
use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper;

/**
 * Dynamic tipo atto list field — reads options from component configuration.
 */
class TipoattolistField extends ListField
{
	protected $type = 'Tipoattolist';

	protected function getOptions()
	{
		$options = parent::getOptions();

		$tipi = AlboonlineHelper::getTipiAtto();

		foreach ($tipi as $valore => $etichetta)
		{
			$options[] = (object) [
				'value' => $valore,
				'text'  => $etichetta,
			];
		}

		return $options;
	}
}
