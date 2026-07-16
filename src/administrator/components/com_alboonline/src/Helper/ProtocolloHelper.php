<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

class ProtocolloHelper
{
	public static function generate(DatabaseInterface $db = null): object
	{
		if ($db === null)
		{
			$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		}

		$anno = (int) date('Y');

		$db->transactionStart();

		try
		{
			// Atomic upsert - INSERT or UPDATE
			$query = $db->getQuery(true);
			$query = "INSERT INTO " . $db->quoteName('#__alboonline_contatori')
				. " (" . $db->quoteName('anno') . ", " . $db->quoteName('ultimo_numero') . ")"
				. " VALUES (" . $anno . ", 1)"
				. " ON DUPLICATE KEY UPDATE "
				. $db->quoteName('ultimo_numero') . " = " . $db->quoteName('ultimo_numero') . " + 1";
			$db->setQuery($query);
			$db->execute();

			// Read back the value with lock
			$query = $db->getQuery(true)
				->select($db->quoteName('ultimo_numero'))
				->from($db->quoteName('#__alboonline_contatori'))
				->where($db->quoteName('anno') . ' = ' . $anno);
			// FOR UPDATE to lock the row
			$db->setQuery($query . ' FOR UPDATE');
			$progressivo = (int) $db->loadResult();

			$db->transactionCommit();
		}
		catch (\Exception $e)
		{
			$db->transactionRollback();
			throw $e;
		}

		return (object) [
			'anno_protocollo'       => $anno,
			'progressivo_protocollo' => $progressivo,
			'numero_protocollo'      => sprintf('%d/%04d', $anno, $progressivo),
		];
	}
}
