<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\View\Atti;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\Helpers\Sidebar;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;
use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper;

class HtmlView extends BaseHtmlView
{
	protected $items;
	protected $pagination;
	protected $state;

	public function display($tpl = null)
	{
		// Disabilitare la cache: lo stato degli atti puo' cambiare in background
		// tramite il task scheduler (In Attesa -> Pubblicato).
		$app = Factory::getApplication();
		$app->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate', true);
		$app->setHeader('Pragma', 'no-cache', true);
		$app->setHeader('Expires', '0', true);

		$this->state         = $this->get('State');
		$this->items         = $this->get('Items');
		$this->pagination    = $this->get('Pagination');
		$this->filterForm    = $this->get('FilterForm');
		$this->activeFilters = $this->get('ActiveFilters');

		if (count($errors = $this->get('Errors')))
		{
			throw new \Exception(implode("\n", $errors));
		}

		$this->verificaTaskPianificati();
		$this->addToolbar();
		$this->sidebar = Sidebar::render();
		parent::display($tpl);
	}

	/**
	 * I due lavori pianificati esistono e sono attivi? (M37)
	 *
	 * Tutto il ciclo di vita dell'atto dipende da loro, e NESSUNO li crea: vanno aggiunti a
	 * mano in Sistema → Attivita pianificate. Se mancano, il componente non protesta e
	 * l'ente se ne accorge tardi, o mai:
	 *
	 *   - senza `lock`    → gli atti restano «In attesa» per sempre (state = 0): non
	 *                       diventano MAI pubblici. La pubblicita' legale non avviene.
	 *   - senza `archive` → gli atti non vengono mai defissi: restano pubblici oltre i
	 *                       termini e nessun referto viene emesso. E' la fattispecie per cui
	 *                       il Garante ha gia' sanzionato enti reali.
	 *
	 * Un avviso in cima all'elenco non e' un presidio forte, ma e' l'unico posto dove chi
	 * lavora all'albo lo vedra' di sicuro.
	 */
	private function verificaTaskPianificati(): void
	{
		$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

		$query = $db->getQuery(true)
			->select($db->quoteName('type'))
			->from($db->quoteName('#__scheduler_tasks'))
			->whereIn($db->quoteName('type'), [
				'plg_task_alboonline_lock',
				'plg_task_alboonline_archive',
			], ParameterType::STRING)
			->where($db->quoteName('state') . ' = 1');

		$attivi   = $db->setQuery($query)->loadColumn() ?: [];
		$mancanti = array_diff(['plg_task_alboonline_lock', 'plg_task_alboonline_archive'], $attivi);

		if (!$mancanti)
		{
			return;
		}

		$etichette = [
			'plg_task_alboonline_lock'    => Text::_('COM_ALBOONLINE_TASK_LOCK'),
			'plg_task_alboonline_archive' => Text::_('COM_ALBOONLINE_TASK_ARCHIVE'),
		];

		foreach ($mancanti as $tipo)
		{
			Factory::getApplication()->enqueueMessage(
				Text::sprintf('COM_ALBOONLINE_WARNING_TASK_MANCANTE', $etichette[$tipo]),
				'warning'
			);
		}
	}

	protected function addToolbar()
	{
		$canDo = AlboonlineHelper::getActions();

		ToolbarHelper::title(Text::_('COM_ALBOONLINE_TITLE_ATTI'), 'file-alt');

		$toolbar = Toolbar::getInstance('toolbar');

		if ($canDo->get('core.create'))
		{
			$toolbar->addNew('atto.add');
		}

		// Registro cronologico delle pubblicazioni (repertorio d'albo) — stampabile.
		$toolbar->linkButton('registro')
			->text('COM_ALBOONLINE_REGISTRO')
			->icon('fas fa-book')
			->url('index.php?option=com_alboonline&view=registro&tmpl=component');

		// GUIDA PER I FUNZIONARI.
		// Sta QUI e non nella scheda dell'atto (decisione utente 2026-07-14): l'elenco e'
		// la schermata da cui si passa sempre, e una barra affollata smette di comunicare.
		//
		// Punta alla copia che VIAGGIA COL COMPONENTE (media/com_alboonline/guida/), non a un
		// indirizzo su internet. Tre ragioni: (1) molti enti hanno l'albo dietro un firewall e
		// un aiuto che non si apre e' peggio di nessun aiuto; (2) la copia locale e' sempre la
		// guida GIUSTA per la versione installata — una guida online descriverebbe funzioni che
		// quell'ente non ha; (3) nessun indirizzo IP di funzionari spedito a un server terzo.
		// L'ente che preferisce una guida online la imposta in Opzioni -> "Indirizzo della guida".
		$guida = ComponentHelper::getParams('com_alboonline')->get('helpURL');

		$toolbar->linkButton('guida')
			->text('COM_ALBOONLINE_GUIDA')
			->icon('fas fa-life-ring')
			->url($guida ?: Uri::root(true) . '/media/com_alboonline/guida/index.html')
			->attributes(['target' => '_blank', 'rel' => 'noopener']);

		if ($canDo->get('core.edit.state'))
		{
			$dropdown = $toolbar->dropdownButton('status-group')
				->text('JTOOLBAR_CHANGE_STATUS')
				->toggleSplit(false)
				->icon('fas fa-ellipsis-h')
				->buttonClass('btn btn-action')
				->listCheck(true);

			$childBar = $dropdown->getChildToolbar();

			// Niente Pubblica/Sospendi/Archivia: agirebbero sul flag `state` di Joomla
			// scavalcando il workflow legale (pubblicazione senza protocollo ne' data
			// certa; sospensione di un atto blindato durante i termini di legge).
			// La pubblicazione avviene dal badge di stato -> atto.publish; l'archiviazione
			// e' compito dello scheduler. Vedi M1 in docs/gap-analysis/05-collaudo-manuale.md

			// `checked_out` e' NULL quando l'atto non e' in modifica: isset() darebbe
			// false e il pulsante Check-in non comparirebbe mai. Vedi M19.
			if (!empty($this->items) && property_exists($this->items[0], 'checked_out'))
			{
				$childBar->checkin('atti.checkin')->listCheck(true);
			}

			if (!empty($this->items) && property_exists($this->items[0], 'state'))
			{
				$childBar->trash('atti.trash')->listCheck(true);
			}
		}

		// «Svuota cestino» compare quando si sta guardando il cestino. Il filtro nativo su
		// `state` non esiste più (M23): ora il cestino si sceglie dalla tendina dello stato
		// del workflow, che il modello traduce in `state = -2`.
		if ((string) $this->state->get('filter.stato_atto') === '-2' && $canDo->get('core.delete'))
		{
			$toolbar->delete('atti.delete')
				->text('JTOOLBAR_EMPTY_TRASH')
				->message('JGLOBAL_CONFIRM_DELETE')
				->listCheck(true);
		}

		if ($canDo->get('core.admin'))
		{
			$toolbar->preferences('com_alboonline');
		}

		Sidebar::setAction('index.php?option=com_alboonline&view=atti');
	}

	protected function getSortFields()
	{
		return [
			'a.id'                => Text::_('JGRID_HEADING_ID'),
			'a.state'             => Text::_('JSTATUS'),
			'a.ordering'          => Text::_('JGRID_HEADING_ORDERING'),
			'a.titolo'            => Text::_('COM_ALBOONLINE_HEADING_TITOLO'),
			'a.numero_protocollo' => Text::_('COM_ALBOONLINE_HEADING_PROTOCOLLO'),
			'a.tipo_atto'         => Text::_('COM_ALBOONLINE_HEADING_TIPO_ATTO'),
			'a.stato_atto'        => Text::_('COM_ALBOONLINE_HEADING_STATO_ATTO'),
			'category_title'      => Text::_('JCATEGORY'),
		];
	}

	public function getState($state)
	{
		return isset($this->state->{$state}) ? $this->state->{$state} : false;
	}
}
