<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ItemModel;

class AttoModel extends ItemModel
{
	protected $item = null;

	public function getItem($pk = null)
	{
		$app = Factory::getApplication();
		$pk = $pk ?: $app->input->getInt('id');

		if ($this->item === null)
		{
			$this->item = false;

			if (!empty($pk))
			{
				$db = $this->getDbo();
				$user = $app->getIdentity();

				$query = $db->getQuery(true)
					->select('a.*')
					->from($db->quoteName('#__alboonline_atti', 'a'))
					->where($db->quoteName('a.id') . ' = ' . (int) $pk);

				// Join category
				$query->select($db->quoteName('c.title', 'category_title'))
					->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = a.catid');

				// Join created_by user
				$query->select($db->quoteName('ua.name', 'author_name'))
					->join('LEFT', $db->quoteName('#__users', 'ua') . ' ON ua.id = a.created_by');

				// Access filtering
				$accessLevels = $user->getAuthorisedViewLevels();
				$query->where('a.access IN (' . implode(',', $accessLevels) . ')');

					// M22 — Privacy: la pagina del singolo atto serve SOLO gli atti
					// pubblicamente consultabili. `state`: 1 = pubblicato, 2 = archiviato
					// consultabile; 0 = bozza/in attesa/archiviato-con-oblio (invisibile),
					// -2 = cestinato. Senza questo filtro un atto in OBLIO o una bozza
					// restava raggiungibile per URL diretto conoscendone l'ID.
					$query->where('a.state IN (1, 2)');

				$db->setQuery($query);
				$item = $db->loadObject();

				if ($item)
				{

					// Increment hits
					$db->setQuery(
						$db->getQuery(true)
							->update($db->quoteName('#__alboonline_atti'))
							->set($db->quoteName('hits') . ' = ' . $db->quoteName('hits') . ' + 1')
							->where($db->quoteName('id') . ' = ' . (int) $pk)
					);
					$db->execute();

					// Load allegati
					$allegatiQuery = $db->getQuery(true)
						->select('*')
						->from($db->quoteName('#__alboonline_allegati'))
						->where($db->quoteName('atto_id') . ' = ' . (int) $pk)
						->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');
					$db->setQuery($allegatiQuery);
					$item->allegati = $db->loadObjectList() ?: [];

					// Legami di rettifica. Si mostrano SOLO se l'atto collegato e' davvero
					// consultabile dal visitatore: lo stesso metro applicato sopra all'atto
					// principale (state IN (1,2) + livello di accesso).
					//
					// Perche' (M35): l'originale viene marcato "rettificato" gia' all'INIZIO
					// del buffer, quando la rettifica e' ancora invisibile (state=0). Senza
					// questo filtro, per 60 minuti la pagina pubblica annunciava la rettifica
					// e ne mostrava il protocollo, ma il link portava a un 404. Filtrando qui
					// si copre anche qualunque altro riferimento pendente (rettifica cestinata,
					// posta in oblio, ad accesso riservato).
					$attoCollegato = function (int $id) use ($db, $accessLevels) {
						$query = $db->getQuery(true)
							->select('id, titolo, numero_protocollo')
							->from($db->quoteName('#__alboonline_atti'))
							->where($db->quoteName('id') . ' = ' . $id)
							->where('state IN (1, 2)')
							->where('access IN (' . implode(',', $accessLevels) . ')');

						return $db->setQuery($query)->loadObject();
					};

					if (!empty($item->rettifica_di))
					{
						$item->atto_rettificato = $attoCollegato((int) $item->rettifica_di);
					}

					if (!empty($item->rettificato_da))
					{
						$item->atto_rettifica = $attoCollegato((int) $item->rettificato_da);
					}

					// Load atti correlati (stesso codice_correlazione, escluso l'atto corrente)
					$item->atti_correlati = [];

					if (!empty($item->codice_correlazione))
					{
						$corrQuery = $db->getQuery(true)
							->select(['id', 'titolo', 'numero_protocollo', 'tipo_atto', 'data_atto', 'publish_up', 'catid'])
							->from($db->quoteName('#__alboonline_atti'))
							->where($db->quoteName('codice_correlazione') . ' = ' . $db->quote($item->codice_correlazione))
							->where($db->quoteName('id') . ' != ' . (int) $item->id)
							->where($db->quoteName('stato_atto') . ' IN (2, 3)')
							->where('NOT (' . $db->quoteName('diritto_oblio') . ' = 1 AND ' . $db->quoteName('stato_atto') . ' = 3)')
							->order($db->quoteName('publish_up') . ' DESC');
						$db->setQuery($corrQuery);
						$item->atti_correlati = $db->loadObjectList() ?: [];
					}

					$this->item = $item;
				}
			}
		}

		return $this->item;
	}
}
