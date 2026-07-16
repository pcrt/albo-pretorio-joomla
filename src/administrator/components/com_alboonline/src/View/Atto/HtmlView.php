<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\View\Atto;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Component\ComponentHelper;
use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper;
use Pcrt\Component\Alboonline\Administrator\Helper\CategorieParticolari;
use Pcrt\Component\Alboonline\Administrator\Helper\FirmaVerifier;
use Pcrt\Component\Alboonline\Administrator\Helper\FormatoPdf;

class HtmlView extends BaseHtmlView
{
	protected $state;
	protected $item;
	protected $form;
	protected $catPart = [];
	protected $showChecklist = false;

	public function display($tpl = null)
	{
		$this->state = $this->get('State');
		$this->item  = $this->get('Item');
		$this->form  = $this->get('Form');

		if (count($errors = $this->get('Errors')))
		{
			throw new \Exception(implode("\n", $errors));
		}

		$this->verificaFirmeAllegati();
		$this->rilevaCategorieParticolari();

		// Checklist pre-pubblicazione (S4-T3): solo per gli atti in bozza (quelli che
		// stanno per essere pubblicati) e se abilitata da Opzioni. Promemoria, non blocca.
		$this->showChecklist = (int) ComponentHelper::getParams('com_alboonline')->get('checklist_pubblicazione', 1) === 1
			&& (int) ($this->item->stato_atto ?? 0) === AlboonlineHelper::STATO_BOZZA;

		// Atto ORFANO (M30): la categoria a cui punta non esiste più. Succedeva quando si
		// cancellava una categoria che conteneva atti (ora impedito dal plugin content), ma
		// un'installazione aggiornata può averne già. Su un atto blindato il campo Categoria
		// è di sola lettura: senza questo pannello non ci sarebbe modo di rimediare.
		$this->categoriaOrfana = false;

		if (!empty($this->item->id))
		{
			$this->categoriaOrfana = $this->getModel()->isCategoriaOrfana((int) $this->item->catid);

			if ($this->categoriaOrfana)
			{
				$this->categorieDisponibili = $this->getCategorieAlbo();
			}
		}

		$this->addToolbar();
		parent::display($tpl);
	}

	/**
	 * Analisi INTERNA (al volo) di ogni allegato: verifica firma (S3-T1) e formato
	 * PDF/A o scansione (S3-T2). Entrambi consultivi (onesti, non bloccanti);
	 * mostrati in linea nella tabella allegati.
	 */
	/** Categorie dell'albo pubblicate, per il pannello di riparazione di un atto orfano (M30). */
	private function getCategorieAlbo(): array
	{
		$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

		$query = $db->getQuery(true)
			->select(['id', 'title', 'level'])
			->from($db->quoteName('#__categories'))
			->where($db->quoteName('extension') . ' = ' . $db->quote('com_alboonline'))
			->where($db->quoteName('published') . ' = 1')
			->order($db->quoteName('lft') . ' ASC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	private function verificaFirmeAllegati(): void
	{
		if (empty($this->item->allegati)) {
			return;
		}

		$verifier = new FirmaVerifier();
		$formato  = new FormatoPdf();
		$base     = JPATH_ROOT . '/media/com_alboonline/allegati/';

		foreach ($this->item->allegati as $allegato) {
			$allegato->firma   = null;
			$allegato->formato = null;

			if (!empty($allegato->filename) && is_file($base . $allegato->filename)) {
				$allegato->firma   = $verifier->verify($base . $allegato->filename);
				$allegato->formato = $formato->analyze($base . $allegato->filename);
			}
		}
	}

	/**
	 * Rileva possibili categorie particolari di dati nel testo dell'atto (S4-T2).
	 * Solo avviso in redazione; l'obbligo di non diffonderle è di legge (art. 9 GDPR),
	 * questo strumento aiuta a non dimenticarsene. Disattivabile da Opzioni.
	 */
	private function rilevaCategorieParticolari(): void
	{
		if ((int) ComponentHelper::getParams('com_alboonline')->get('categorie_particolari_alert', 1) !== 1)
		{
			return;
		}

		$testo = trim(
			(string) ($this->item->titolo ?? '') . ' '
			. (string) ($this->item->oggetto ?? '') . ' '
			. (string) ($this->item->descrizione ?? '')
		);

		$this->catPart = CategorieParticolari::scan($testo);
	}

	protected function addToolbar()
	{
		Factory::getApplication()->input->set('hidemainmenu', true);

		$user = Factory::getApplication()->getIdentity();
		$isNew = ($this->item->id == 0);
		$statoAtto = (int) ($this->item->stato_atto ?? 0);

		$checkedOut = false;

		if (isset($this->item->checked_out))
		{
			$checkedOut = !($this->item->checked_out == 0 || $this->item->checked_out == $user->get('id'));
		}

		$canDo = AlboonlineHelper::getActions();
		$isLocked = AlboonlineHelper::isLocked($this->item);

		$title = $isNew ? Text::_('COM_ALBOONLINE_TITLE_ATTO_NEW') : Text::_('COM_ALBOONLINE_TITLE_ATTO_EDIT');
		ToolbarHelper::title($title, 'file-alt');

		if ($isLocked)
		{
			// Nessun pulsante "Proroga" in toolbar: un ToolbarHelper::custom() invia
			// `adminForm`, che non contiene il campo `extend_publish_down`. Il controller
			// riceverebbe sempre una data vuota, e l'operazione fallirebbe ogni volta.
			// La proroga si esegue dal riquadro dedicato (tmpl/atto/edit.php), che ha il
			// proprio form con il campo data. Vedi M7.

			// Rectify button
			if ($canDo->get('alboonline.rectify'))
			{
				ToolbarHelper::custom('atto.rectify', 'copy', '', 'COM_ALBOONLINE_TOOLBAR_RECTIFY', false);
			}

			ToolbarHelper::cancel('atto.cancel', 'JTOOLBAR_CLOSE');

			return;
		}

		// UNLOCKED ACT: normal workflow buttons

		// Save buttons for new or draft/pending items
		if (!$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create')))
		{
			ToolbarHelper::apply('atto.apply', 'JTOOLBAR_APPLY');
			ToolbarHelper::save('atto.save', 'JTOOLBAR_SAVE');
		}

		// Publish button (Draft -> Pending)
		if (!$isNew && $statoAtto === AlboonlineHelper::STATO_BOZZA && $canDo->get('alboonline.publish'))
		{
			ToolbarHelper::custom('atto.publish', 'publish', '', 'COM_ALBOONLINE_TOOLBAR_PUBLISH', false);
		}

		// Cancel publish (Pending -> Draft)
		if (!$isNew && $statoAtto === AlboonlineHelper::STATO_IN_ATTESA && $canDo->get('alboonline.publish'))
		{
			ToolbarHelper::custom('atto.cancelPublish', 'unpublish', '', 'COM_ALBOONLINE_TOOLBAR_CANCEL_PUBLISH', false);
		}

		if ($isNew)
		{
			ToolbarHelper::cancel('atto.cancel', 'JTOOLBAR_CANCEL');
		}
		else
		{
			ToolbarHelper::cancel('atto.cancel', 'JTOOLBAR_CLOSE');
		}

		// Pulsante «Aiuto in linea»: mostra/nasconde le spiegazioni dei campi, che di
		// default sono NASCOSTE (forms/atto.xml → <inlinehelp button="show"/>). Chi sa già
		// cosa fare lavora senza rumore; chi ha un dubbio apre. È il meccanismo nativo di
		// Joomla, lo stesso degli articoli: nessuna invenzione nostra da manutenere.
		ToolbarHelper::inlinehelp();
	}
}
