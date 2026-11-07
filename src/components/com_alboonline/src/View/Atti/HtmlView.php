<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Site\View\Atti;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Pcrt\Component\Alboonline\Site\Model\CategoriesModel;

class HtmlView extends BaseHtmlView
{
	protected $items;
	protected $pagination;
	protected $state;
	public $filterForm;
	public $activeFilters;
	protected $categories;
	protected $categoryTree;
	protected $years;
	protected $params;

	public function display($tpl = null)
	{
		$app = Factory::getApplication();

		$this->state         = $this->get('State');
		$this->items         = $this->get('Items');
		$this->pagination    = $this->get('Pagination');
		$this->filterForm    = $this->get('FilterForm');
		$this->activeFilters = $this->get('ActiveFilters');
		$this->years         = $this->get('Years');
		$this->params        = $app->getParams();

		// Meta robots dell'elenco: dalla voce di menu, altrimenti dalla Configurazione globale.
		// Prima non veniva impostato e l'elenco ignorava anche il noindex di tutto il sito
		// (es. su un sito dimostrativo). La vista del singolo atto ha una regola propria.
		$robots = $this->params->get('robots') ?: $app->get('robots');

		if ($robots)
		{
			$app->getDocument()->setMetaData('robots', $robots);
		}

		// Get categories for sidebar tree
		try
		{
			$mvcFactory = Factory::getApplication()->bootComponent('com_alboonline')->getMVCFactory();
			$catModel = $mvcFactory->createModel('Categories', 'Site');

			// Il conteggio accanto a ogni categoria deve corrispondere a ciò che si vedrà
			// cliccandoci: nella vista Archivio si contano gli archiviati, nell'Albo i
			// pubblicati (M29).
			$soloArchiviati = (int) $this->params->get('solo_archiviati', 0) === 1;

			$this->categories   = $catModel->getCategories($soloArchiviati);
			$this->categoryTree = $catModel->buildTree($this->categories);
		}
		catch (\Exception $e)
		{
			$this->categories   = [];
			$this->categoryTree = [];
		}

		// Check for errors
		if (count($errors = $this->get('Errors')))
		{
			throw new \RuntimeException(implode("\n", $errors), 500);
		}

		// Add assets
		$wa = Factory::getApplication()->getDocument()->getWebAssetManager();

		// Ripiego Bootstrap: solo se l'ente usa un tema che non lo include (opzione,
		// default No). I nostri layout usano le classi Bootstrap standard, che di norma
		// le disegna il tema (Cassiopeia, Bootstrap Italia, qualunque tema Bootstrap).
		if ((int) $this->params->get('carica_bootstrap', 0) === 1)
		{
			$wa->useStyle('com_alboonline.bootstrap-fallback');
		}

		$wa->useStyle('com_alboonline.explorer')
			->useScript('com_alboonline.explorer');

		parent::display($tpl);
	}
}
