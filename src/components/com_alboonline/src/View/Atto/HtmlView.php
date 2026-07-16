<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Site\View\Atto;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper;

class HtmlView extends BaseHtmlView
{
	protected $item;
	protected $params;

	public function display($tpl = null)
	{
		$app = Factory::getApplication();

		$this->item   = $this->get('Item');
		$this->params = $app->getParams();

		if (!$this->item)
		{
			throw new \RuntimeException('Atto non trovato.', 404);
		}

		// Check for errors
		if (count($errors = $this->get('Errors')))
		{
			throw new \RuntimeException(implode("\n", $errors), 500);
		}

		// Deindicizzazione (S4-T1). Per l'Albo Pretorio l'indicizzazione dai motori è
		// sconsigliata (diritto all'oblio), all'opposto dell'Amministrazione Trasparente.
		// Regola: default `noindex`; sugli atti ARCHIVIATI (post-defissione) è FORZATO e
		// non sovrascrivibile — non devono restare reperibili, nemmeno in cache.
		$componentParams = ComponentHelper::getParams('com_alboonline');

		if ((int) $this->item->stato_atto === AlboonlineHelper::STATO_ARCHIVIATO)
		{
			$robots = 'noindex, noarchive';
		}
		elseif (!empty($this->item->robots))
		{
			// Override per singolo atto (consentito solo su atti ancora in corso).
			$robots = $this->item->robots;
		}
		else
		{
			// Default del componente; se non configurato, noindex prudenziale.
			$robots = $componentParams->get('default_robots', '') ?: 'noindex, nofollow';
		}

		$app->getDocument()->setMetaData('robots', $robots);

		// Enforcement anche a livello di risposta HTTP: l'header X-Robots-Tag vale
		// per i crawler che ignorano il meta ed è l'unico canale per le risorse non
		// HTML. Vedi docs/compliance/05-deindicizzazione.md.
		$app->setHeader('X-Robots-Tag', $robots, true);

		// Page title
		$app->getDocument()->setTitle($this->item->titolo);

		// Add assets
		$wa = $app->getDocument()->getWebAssetManager();

		// Ripiego Bootstrap (opzione, default No): vedi Atti\HtmlView e config.xml.
		if ((int) $this->params->get('carica_bootstrap', 0) === 1)
		{
			$wa->useStyle('com_alboonline.bootstrap-fallback');
		}

		$wa->useStyle('com_alboonline.atto');

		parent::display($tpl);
	}
}
