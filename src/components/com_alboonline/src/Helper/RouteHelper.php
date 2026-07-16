<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;

class RouteHelper
{
	/**
	 * @param  int   $catid       id categoria; 0 = nessuna.
	 * @param  bool  $esplicito   se true, `catid=0` viene scritto NELL'URL invece di essere
	 *                            omesso. Serve al link «Tutte le categorie»: senza il
	 *                            parametro, il modello non sa che deve AZZERARE il filtro e
	 *                            si tiene quello rimasto in sessione — il cittadino resta
	 *                            chiuso dentro una categoria.
	 */
	public static function getAttiRoute($catid = 0, $language = '*', bool $esplicito = false)
	{
		$link = 'index.php?option=com_alboonline&view=atti';

		if ((int) $catid > 0)
		{
			$link .= '&catid=' . (int) $catid;
		}
		elseif ($esplicito)
		{
			$link .= '&catid=0';
		}

		if ($language !== '*' && Multilanguage::isEnabled())
		{
			$link .= '&lang=' . $language;
		}

		return $link;
	}

	/**
	 * Trova l'Itemid del menu Albo Online o Archivio Atti in base allo stato dell'atto.
	 * Stato 3 (Archiviato) -> menu con solo_archiviati=1
	 * Stato 2 (Pubblicato) -> menu con solo_archiviati=0
	 *
	 * @return int Itemid trovato o 0 se nessun menu compatibile
	 */
	public static function findAttiMenuItemId(int $statoAtto): int
	{
		static $cache = [];

		if (isset($cache[$statoAtto]))
		{
			return $cache[$statoAtto];
		}

		$soloArchiviatiTarget = ($statoAtto === 3) ? 1 : 0;

		$menu = Factory::getApplication()->getMenu();
		$items = $menu->getItems('component', 'com_alboonline');

		foreach ((array) $items as $item)
		{
			if (($item->query['view'] ?? '') !== 'atti')
			{
				continue;
			}

			$soloArchiviati = (int) $item->getParams()->get('solo_archiviati', 0);

			if ($soloArchiviati === $soloArchiviatiTarget)
			{
				return $cache[$statoAtto] = (int) $item->id;
			}
		}

		return $cache[$statoAtto] = 0;
	}

	public static function getAttoRoute($id, $catid = 0, $language = '*')
	{
		$link = 'index.php?option=com_alboonline&view=atto&id=' . (int) $id;

		if ($language !== '*' && Multilanguage::isEnabled())
		{
			$link .= '&lang=' . $language;
		}

		return $link;
	}

	/**
	 * Get download route for an allegato.
	 *
	 * @param  int  $allegatoId  The allegato ID (from #__alboonline_allegati)
	 */
	public static function getDownloadRoute($allegatoId)
	{
		return 'index.php?option=com_alboonline&task=download.file&id=' . (int) $allegatoId;
	}
}
