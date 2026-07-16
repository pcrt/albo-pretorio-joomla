<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\Table;

defined('_JEXEC') or die;

use Joomla\Filesystem\File;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class AllegatoTable extends Table
{
	protected $_supportNullValue = true;

	public function __construct(DatabaseDriver $db)
	{
		parent::__construct('#__alboonline_allegati', 'id', $db);
	}

	public function delete($pk = null)
	{
		$this->load($pk);

		// Delete physical file
		if (!empty($this->filename))
		{
			$filePath = JPATH_ROOT . '/media/com_alboonline/allegati/' . $this->filename;

			if (file_exists($filePath))
			{
				File::delete($filePath);
			}
		}

		return parent::delete($pk);
	}
}
