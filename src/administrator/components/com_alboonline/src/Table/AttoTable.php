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

use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper;
use Joomla\CMS\Versioning\VersionableTableInterface;
use Joomla\Database\DatabaseDriver;
use Joomla\Registry\Registry;

class AttoTable extends Table implements VersionableTableInterface
{
	protected $_supportNullValue = true;

	public function __construct(DatabaseDriver $db)
	{
		$this->typeAlias = 'com_alboonline.atto';
		parent::__construct('#__alboonline_atti', 'id', $db);
		$this->setColumnAlias('published', 'state');
	}

	public function getTypeAlias()
	{
		return $this->typeAlias;
	}

	public function bind($array, $ignore = '')
	{
		$date = Factory::getDate();
		$user = Factory::getApplication()->getIdentity();
		$input = Factory::getApplication()->input;
		$task = $input->getString('task', '');

		if ($array['id'] == 0 && empty($array['created_by']))
		{
			$array['created_by'] = $user->id;
		}

		if ($array['id'] == 0 && empty($array['modified_by']))
		{
			$array['modified_by'] = $user->id;
		}

		if ($array['id'] == 0 && empty($array['created']))
		{
			$array['created'] = $date->toSql();
		}

		if ($array['id'] == 0 && empty($array['modified']))
		{
			$array['modified'] = $date->toSql();
		}

		if ($task == 'apply' || $task == 'save' || strpos($task, '.apply') !== false || strpos($task, '.save') !== false)
		{
			$array['modified_by'] = $user->id;
			$array['modified'] = $date->toSql();
		}

		// Generate alias
		if (empty($array['alias']) && !empty($array['titolo']))
		{
			$array['alias'] = OutputFilter::stringURLSafe($array['titolo']);
		}

		// Campi DATE (solo data, senza orario). Il calendario Joomla puo' inviare un
		// valore con componente orario/timezone che causa uno shift di +1h.
		// Estraiamo solo YYYY-MM-DD.
		foreach (['data_atto', 'segnatura_data'] as $dateOnlyField)
		{
			if (!empty($array[$dateOnlyField]))
			{
				$dateOnly = substr(trim($array[$dateOnlyField]), 0, 10);
				if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOnly))
				{
					$array[$dateOnlyField] = $dateOnly;
				}
			}
		}

		// Campi data vuoti -> NULL (una colonna DATE non accetta '' in modalita' strict).
		// segnatura_data (S3-T4) va inclusa, altrimenti salvare un atto senza segnatura
		// genera l'errore "Incorrect date value: '' for column segnatura_data".
		$dateFields = ['data_atto', 'segnatura_data', 'publish_up', 'publish_down', 'buffer_expires_at', 'locked_at'];

		foreach ($dateFields as $field)
		{
			if (isset($array[$field]) && ($array[$field] == '0000-00-00' || $array[$field] == '0000-00-00 00:00:00' || empty($array[$field])))
			{
				$array[$field] = null;
				$this->$field = null;
			}
		}

		if (isset($array['params']) && is_array($array['params']))
		{
			$registry = new Registry;
			$registry->loadArray($array['params']);
			$array['params'] = (string) $registry;
		}

		if (isset($array['metadata']) && is_array($array['metadata']))
		{
			$registry = new Registry;
			$registry->loadArray($array['metadata']);
			$array['metadata'] = (string) $registry;
		}

		if (!$user->authorise('core.admin', 'com_alboonline.atto.' . $array['id']))
		{
			$actions = Access::getActionsFromFile(
				JPATH_ADMINISTRATOR . '/components/com_alboonline/access.xml',
				"/access/section[@name='component']/"
			);
			$defaultActions = Access::getAssetRules('com_alboonline.atto.' . $array['id'])->getData();
			$arrayJaccess = [];

			foreach ($actions as $action)
			{
				if (key_exists($action->name, $defaultActions))
				{
					$arrayJaccess[$action->name] = $defaultActions[$action->name];
				}
			}

			$array['rules'] = $this->JAccessRulestoArray($arrayJaccess);
		}

		if (isset($array['rules']) && is_array($array['rules']))
		{
			$this->setRules($array['rules']);
		}

		return parent::bind($array, $ignore);
	}

	public function store($updateNulls = true)
	{
		return parent::store($updateNulls);
	}

	private function JAccessRulestoArray($jaccessrules)
	{
		$rules = [];

		foreach ($jaccessrules as $action => $jaccess)
		{
			$actions = [];

			if ($jaccess)
			{
				foreach ($jaccess->getData() as $group => $allow)
				{
					$actions[$group] = ((bool) $allow);
				}
			}

			$rules[$action] = $actions;
		}

		return $rules;
	}

	public function check()
	{
		if (property_exists($this, 'ordering') && $this->id == 0)
		{
			$this->ordering = self::getNextOrder();
		}

		return parent::check();
	}

	protected function _getAssetName()
	{
		$k = $this->_tbl_key;
		return $this->typeAlias . '.' . (int) $this->$k;
	}

	protected function _getAssetParentId($table = null, $id = null)
	{
		$assetParent = Table::getInstance('Asset');
		$assetParentId = $assetParent->getRootId();

		$assetParent->loadByName('com_alboonline');

		if ($assetParent->id)
		{
			$assetParentId = $assetParent->id;
		}

		return $assetParentId;
	}

	public function delete($pk = null)
	{
		$this->load($pk);
		$attoId = (int) $this->id;

		// Ultimo cancello prima della distruzione: un atto blindato (pubblicato o
		// archiviato) e' in pubblicita' legale. Non e' cancellabile, e non lo sono i
		// suoi allegati: cancellarlo distruggerebbe l'atto e i PDF dal disco, privando
		// referto e registro del loro riferimento.
		// Vedi M17 in docs/gap-analysis/05-collaudo-manuale.md
		if ($attoId > 0 && AlboonlineHelper::isLocked($this))
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_DELETE_LOCKED'));

			return false;
		}

		// Delete all allegati (files + DB rows)
		if ($attoId > 0)
		{
			$db = $this->getDbo();
			$query = $db->getQuery(true)
				->select(['id', 'filename'])
				->from($db->quoteName('#__alboonline_allegati'))
				->where($db->quoteName('atto_id') . ' = ' . $attoId);
			$db->setQuery($query);
			$allegati = $db->loadObjectList();

			if (!empty($allegati))
			{
				$basePath = JPATH_ROOT . '/media/com_alboonline/allegati/';

				foreach ($allegati as $allegato)
				{
					if (!empty($allegato->filename))
					{
						$filePath = $basePath . $allegato->filename;

						if (file_exists($filePath))
						{
							File::delete($filePath);
						}
					}
				}

				// Delete all allegati rows
				$deleteQuery = $db->getQuery(true)
					->delete($db->quoteName('#__alboonline_allegati'))
					->where($db->quoteName('atto_id') . ' = ' . $attoId);
				$db->setQuery($deleteQuery);
				$db->execute();
			}
		}

		return parent::delete($pk);
	}
}
