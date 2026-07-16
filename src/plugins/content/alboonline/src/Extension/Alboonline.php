<?php
/**
 * @package    Pcrt.Plugin
 * @subpackage Content.Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2026 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Plugin\Content\Alboonline\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper;

class Alboonline extends CMSPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;

	private const STATO_PUBBLICATO = 2;
	private const STATO_ARCHIVIATO = 3;

	public static function getSubscribedEvents(): array
	{
		return [
			'onContentPrepare'      => 'onContentPrepare',
			'onContentBeforeDelete' => 'onContentBeforeDelete',
			'onContentPrepareForm'  => 'onContentPrepareForm',
			'onBeforeRender'        => 'onBeforeRender',
		];
	}

	/**
	 * Aggiunge il pulsante «Aiuto in linea» alla scheda della CATEGORIA dell'albo.
	 *
	 * La vista delle categorie e' del core (com_categories) e quel pulsante non lo prevede.
	 * Ma le spiegazioni che iniettiamo in onContentPrepareForm nascono NASCOSTE, come nella
	 * scheda dell'atto: senza il pulsante sarebbero irraggiungibili. Lo aggiungiamo qui, alla
	 * toolbar gia' costruita, prima che la pagina venga resa.
	 *
	 * Cosi' le due schermate si comportano allo stesso modo — un'incoerenza fra schermate
	 * dello stesso componente e' un modo sicuro per far smettere di leggere gli aiuti.
	 */
	public function onBeforeRender($event): void
	{
		$app = $this->getApplication();

		if (!$app->isClient('administrator')) {
			return;
		}

		$input = $app->getInput();

		if ($input->getCmd('option') !== 'com_categories'
			|| $input->getCmd('extension') !== 'com_alboonline') {
			return;
		}

		\Joomla\CMS\Toolbar\ToolbarHelper::inlinehelp();
	}

	/**
	 * Spiega, dentro la scheda della CATEGORIA, cosa una categoria dell'albo fa e cosa NON fa.
	 *
	 * Il gestore delle categorie e' quello del CORE (com_categories): non possiamo modificarne
	 * il form, ma possiamo iniettarci le spiegazioni — e solo per le categorie dell'albo.
	 *
	 * Serve soprattutto a smontare un equivoco pericoloso: il livello di ACCESSO di una
	 * categoria NON limita chi vede gli atti che contiene. Un funzionario puo' credere di aver
	 * «riservato» una categoria e non averlo fatto per niente — e, credendolo, pubblicarci
	 * dentro documenti che non dovevano essere pubblici. Il posto giusto per dirlo e' proprio
	 * qui, dove sta per commettere l'errore.
	 *
	 * NB: qui le spiegazioni restano VISIBILI (non nascoste dietro «Aiuto in linea», come nella
	 * scheda dell'atto): la vista delle categorie e' del core e non espone quel pulsante, quindi
	 * nasconderle le renderebbe irraggiungibili.
	 */
	public function onContentPrepareForm($event): void
	{
		$form = $event->getArgument('subject') ?? ($event->getArguments()[0] ?? null);

		if (!$form instanceof \Joomla\CMS\Form\Form) {
			return;
		}

		if ($form->getName() !== 'com_categories.categorycom_alboonline') {
			return;
		}

		$this->getApplication()->getLanguage()->load('plg_content_alboonline', JPATH_ADMINISTRATOR);

		$spiegazioni = [
			'title'     => 'PLG_CONTENT_ALBOONLINE_CAT_TITLE_DESC',
			'access'    => 'PLG_CONTENT_ALBOONLINE_CAT_ACCESS_DESC',
			'published' => 'PLG_CONTENT_ALBOONLINE_CAT_PUBLISHED_DESC',
		];

		foreach ($spiegazioni as $campo => $chiave) {
			if ($form->getField($campo)) {
				$form->setFieldAttribute($campo, 'description', Text::_($chiave));
			}
		}

		// Le spiegazioni nascono NASCOSTE, come nella scheda dell'atto: FormField legge
		// `config->inlinehelp['button']` dall'XML del form per decidere se il blocco della
		// descrizione parte con la classe `d-none`. Il form e' del core e quel nodo non ce
		// l'ha: glielo aggiungiamo qui. Il pulsante che le rivela lo mettiamo in toolbar
		// (onBeforeRender): senza, sarebbero irraggiungibili.
		$xml = $form->getXml();

		if ($xml !== null && !isset($xml->config->inlinehelp)) {
			$config = isset($xml->config) ? $xml->config : $xml->addChild('config');
			$config->addChild('inlinehelp')->addAttribute('button', 'show');
		}
	}

	/**
	 * Impedisce di cancellare una categoria dell'albo che contiene atti (M30).
	 *
	 * Il gestore categorie e' quello del CORE (com_categories): non conosce la tabella
	 * #__alboonline_atti e quindi cancellava senza avvisare, lasciando atti ORFANI — con un
	 * `catid` che punta a una categoria inesistente. Su un atto blindato il danno era
	 * definitivo: il campo Categoria e' di sola lettura, e non c'era modo di rimediare
	 * dall'interfaccia.
	 *
	 * Qui il componente si difende da solo: e' la stessa difesa in profondita' del presidio
	 * in AttoTable::delete() (M17).
	 *
	 * Il core accetta il veto se il listener aggiunge `false` al risultato E l'errore e'
	 * scritto sulla TABELLA: AdminModel::delete() legge il messaggio da $table->getError().
	 */
	public function onContentBeforeDelete($event): void
	{
		$context = $event->getArgument('context');
		$table   = $event->getArgument('subject');

		if ($context !== 'com_categories.category') {
			return;
		}

		if (!$table || ($table->extension ?? '') !== 'com_alboonline') {
			return;
		}

		$atti = $this->countAtti((int) $table->id);

		if ($atti === 0) {
			return;
		}

		$this->getApplication()->getLanguage()->load(
			'plg_content_alboonline',
			JPATH_ADMINISTRATOR
		);

		$table->setError(
			Text::plural('PLG_CONTENT_ALBOONLINE_ERROR_CATEGORIA_CON_ATTI', $atti)
		);

		$event->addResult(false);
	}

	/**
	 * Quanti atti sono in questa categoria — TUTTI, compresi bozze e cestinati: anche un
	 * atto cestinato conserva la sua classificazione, e cancellare la categoria la
	 * perderebbe comunque.
	 */
	private function countAtti(int $catId): int
	{
		$db = $this->getDatabase();

		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__alboonline_atti'))
			->where($db->quoteName('catid') . ' = :catid')
			->bind(':catid', $catId, ParameterType::INTEGER);

		return (int) $db->setQuery($query)->loadResult();
	}

	public function onContentPrepare($event): void
	{
		[$context, $article, $params, $page] = array_values($event->getArguments());

		if ($context === 'com_finder.indexer') {
			return;
		}

		if (!isset($article->text) || strpos($article->text, '{alboonline') === false) {
			return;
		}

		$this->getApplication()->getLanguage()->load('com_alboonline', JPATH_SITE);
		$this->getApplication()->getLanguage()->load('plg_content_alboonline', JPATH_PLUGINS . '/content/alboonline');

		$article->text = preg_replace_callback(
			'/\{alboonline\s+([^}\s]+)\}/',
			function ($matches) {
				$value = trim($matches[1]);

				// Numeric = single atto by ID
				if (ctype_digit($value)) {
					return $this->renderAtto((int) $value);
				}

				// Alphanumeric = correlation code (group of atti)
				return $this->renderCorrelati($value);
			},
			$article->text
		);
	}

	private function renderAtto(int $id): string
	{
		$db = $this->getDatabase();

		// Load atto
		$query = $db->getQuery(true)
			->select([
				'a.id', 'a.titolo', 'a.oggetto', 'a.numero_protocollo',
				'a.tipo_atto', 'a.stato_atto', 'a.state',
				'a.ente_emanante', 'a.data_atto',
				'a.publish_up', 'a.publish_down',
				'a.diritto_oblio',
				'c.title AS category_title',
			])
			->from($db->quoteName('#__alboonline_atti', 'a'))
			->leftJoin($db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
			->where($db->quoteName('a.id') . ' = ' . $id);
		$db->setQuery($query);
		$atto = $db->loadObject();

		if (!$atto) {
			return '<div class="alert alert-warning">'
				. Text::sprintf('PLG_CONTENT_ALBOONLINE_NOT_FOUND', $id)
				. '</div>';
		}

		// Diritto all'oblio: atto scaduto e rimosso dal pubblico
		$isExpired = (int) $atto->stato_atto === self::STATO_ARCHIVIATO;
		if ((int) $atto->diritto_oblio === 1 && $isExpired) {
			return '<div class="alert alert-secondary">'
				. '<span class="icon-lock me-1" aria-hidden="true"></span>'
				. Text::sprintf('PLG_CONTENT_ALBOONLINE_DIRITTO_OBLIO', $id)
				. '</div>';
		}

		// Not published (draft, trashed, etc.)
		if ((int) $atto->state !== 1) {
			return '<div class="alert alert-warning">'
				. Text::sprintf('PLG_CONTENT_ALBOONLINE_NOT_AVAILABLE', $id)
				. '</div>';
		}

		// Load allegati
		$query = $db->getQuery(true)
			->select(['id', 'titolo', 'filename', 'original_name', 'size'])
			->from($db->quoteName('#__alboonline_allegati'))
			->where($db->quoteName('atto_id') . ' = ' . $id)
			->order($db->quoteName('ordering') . ' ASC');
		$db->setQuery($query);
		$allegati = $db->loadObjectList();

		return $this->buildHtml($atto, $allegati);
	}

	private function renderCorrelati(string $codice): string
	{
		$db = $this->getDatabase();

		// Load all atti with this correlation code
		$query = $db->getQuery(true)
			->select([
				'a.id', 'a.titolo', 'a.oggetto', 'a.numero_protocollo',
				'a.tipo_atto', 'a.stato_atto', 'a.state',
				'a.ente_emanante', 'a.data_atto',
				'a.publish_up', 'a.publish_down',
				'a.diritto_oblio',
				'c.title AS category_title',
			])
			->from($db->quoteName('#__alboonline_atti', 'a'))
			->leftJoin($db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
			->where($db->quoteName('a.codice_correlazione') . ' = :codice')
			->where($db->quoteName('a.state') . ' = 1')
			->bind(':codice', $codice)
			->order($db->quoteName('a.publish_up') . ' ASC');
		$db->setQuery($query);
		$atti = $db->loadObjectList();

		if (empty($atti)) {
			return '<div class="alert alert-warning">'
				. Text::sprintf('PLG_CONTENT_ALBOONLINE_CORRELATI_NOT_FOUND', htmlspecialchars($codice))
				. '</div>';
		}

		// Filter out atti with diritto all'oblio that are archived
		$atti = array_filter($atti, function ($atto) {
			if ((int) $atto->diritto_oblio === 1 && (int) $atto->stato_atto === self::STATO_ARCHIVIATO) {
				return false;
			}
			return true;
		});

		if (empty($atti)) {
			return '<div class="alert alert-secondary">'
				. '<span class="icon-lock me-1" aria-hidden="true"></span>'
				. Text::sprintf('PLG_CONTENT_ALBOONLINE_CORRELATI_OBLIO', htmlspecialchars($codice))
				. '</div>';
		}

		// Load allegati for all atti in a single query
		$attoIds = array_map(function ($a) { return (int) $a->id; }, $atti);
		$query = $db->getQuery(true)
			->select(['id', 'atto_id', 'titolo', 'filename', 'original_name', 'size'])
			->from($db->quoteName('#__alboonline_allegati'))
			->whereIn($db->quoteName('atto_id'), $attoIds)
			->order($db->quoteName('atto_id') . ' ASC, ' . $db->quoteName('ordering') . ' ASC');
		$db->setQuery($query);
		$allAllegati = $db->loadObjectList();

		// Group allegati by atto_id
		$allegatiByAtto = [];
		foreach ($allAllegati as $allegato) {
			$allegatiByAtto[(int) $allegato->atto_id][] = $allegato;
		}

		return $this->buildCorrelatiHtml($atti, $allegatiByAtto, $codice);
	}

	private function buildCorrelatiHtml(array $atti, array $allegatiByAtto, string $codice): string
	{
		$tipiAtto = AlboonlineHelper::getTipiAtto();

		$token = Session::getFormToken();

		$html = '<div class="plg-alboonline-embed plg-alboonline-correlati mt-4 mb-4" style="border-left: 4px solid #b71c1c; padding-left: 1.25rem;">';

		// Header — lightweight
		$html .= '<div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">';
		$html .= '<h4 class="mb-0" style="font-size: 1.1rem;">'
			. Text::_('PLG_CONTENT_ALBOONLINE_PROCEDURA')
			. '</h4>';
		$html .= '<small class="text-muted">'
			. Text::sprintf('PLG_CONTENT_ALBOONLINE_ATTI_COUNT', count($atti))
			. '</small>';
		$html .= '</div>';

		// Atti list
		foreach ($atti as $index => $atto) {
			$attoUrl = Route::_('index.php?option=com_alboonline&view=atto&id=' . (int) $atto->id);
			$tipoLabel = $tipiAtto[$atto->tipo_atto] ?? ucfirst($atto->tipo_atto);
			$isLast = ($index === count($atti) - 1);

			$html .= '<div class="' . ($isLast ? 'mb-3' : 'mb-3 pb-3 border-bottom') . '">';

			// Protocol + title
			$html .= '<div class="mb-1">';
			if (!empty($atto->numero_protocollo)) {
				$html .= '<strong class="me-2">' . htmlspecialchars($atto->numero_protocollo) . '</strong>';
			}
			$html .= '<a href="' . $attoUrl . '">' . htmlspecialchars($atto->titolo) . '</a>';
			$html .= '</div>';

			// Oggetto
			if (!empty($atto->oggetto)) {
				$html .= '<p class="text-muted mb-1 small">' . htmlspecialchars($atto->oggetto) . '</p>';
			}

			// Tags — small, discrete
			$html .= '<small class="text-muted">';
			$html .= $tipoLabel;
			if (!empty($atto->category_title)) {
				$html .= ' &middot; ' . htmlspecialchars($atto->category_title);
			}
			if (!empty($atto->publish_up)) {
				$html .= ' &middot; ' . HTMLHelper::_('date', $atto->publish_up, Text::_('DATE_FORMAT_LC4'));
			}
			$html .= '</small>';

			$html .= '</div>';
		}

		// Allegati — flat list, clean boxes
		$hasAllegati = false;
		foreach ($atti as $atto) {
			if (!empty($allegatiByAtto[(int) $atto->id])) {
				$hasAllegati = true;
				break;
			}
		}

		if ($hasAllegati) {
			$html .= '<div class="mt-4 pt-3 border-top">';
			$html .= '<h4 class="mb-3" style="font-size: 1.1rem;">'
				. Text::_('COM_ALBOONLINE_ALLEGATI') . '</h4>';

			foreach ($atti as $atto) {
				$attoAllegati = $allegatiByAtto[(int) $atto->id] ?? [];
				if (empty($attoAllegati)) {
					continue;
				}

				foreach ($attoAllegati as $allegato) {
					$downloadUrl = Route::_('index.php?option=com_alboonline&task=download.file&id=' . (int) $allegato->id . '&' . $token . '=1');
					$nome = htmlspecialchars($allegato->titolo ?: $allegato->original_name ?: $allegato->filename);
					$size = $allegato->size > 0 ? HTMLHelper::_('number.bytes', $allegato->size) : '';

					$html .= '<a href="' . $downloadUrl . '" class="d-flex align-items-center gap-2 text-decoration-none text-dark border rounded p-3 mb-2" style="transition: background .15s;" onmouseover="this.style.background=\'#f8f9fa\'" onmouseout="this.style.background=\'\'">';
					$html .= '<span class="icon-file-alt flex-shrink-0" aria-hidden="true" style="font-size: 1.2rem;"></span>';
					$html .= '<span class="flex-grow-1">' . $nome . '</span>';
					if ($size) {
						$html .= '<small class="text-muted flex-shrink-0">' . $size . '</small>';
					}
					$html .= '</a>';
				}
			}

			$html .= '</div>';
		}

		$html .= '</div>'; // plg-alboonline-correlati

		return $html;
	}

	private function buildHtml(object $atto, array $allegati): string
	{
		$tipoLabel = AlboonlineHelper::getTipoLabel($atto->tipo_atto);

		$attoUrl = Route::_('index.php?option=com_alboonline&view=atto&id=' . (int) $atto->id);

		$html = '<div class="card border-danger mb-4 plg-alboonline-embed">';

		// Header
		$html .= '<div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">';
		$html .= '<span><span class="icon-file-alt me-1" aria-hidden="true"></span>'
			. Text::_('PLG_CONTENT_ALBOONLINE_ATTO_ALBO') . '</span>';
		if (!empty($atto->numero_protocollo)) {
			$html .= '<span class="badge bg-light text-dark">' . htmlspecialchars($atto->numero_protocollo) . '</span>';
		}
		$html .= '</div>';

		// Body
		$html .= '<div class="card-body">';

		// Title + link
		$html .= '<h4 class="card-title">';
		$html .= '<a href="' . $attoUrl . '">' . htmlspecialchars($atto->titolo) . '</a>';
		$html .= '</h4>';

		// Oggetto
		if (!empty($atto->oggetto)) {
			$html .= '<p class="card-text text-muted">' . htmlspecialchars($atto->oggetto) . '</p>';
		}

		// Metadata row
		$html .= '<div class="d-flex flex-wrap gap-2 mb-3">';
		$html .= '<span class="badge bg-secondary">' . $tipoLabel . '</span>';
		if (!empty($atto->category_title)) {
			$html .= '<span class="badge bg-light text-dark border">' . htmlspecialchars($atto->category_title) . '</span>';
		}
		if (!empty($atto->data_atto) && $atto->data_atto !== '0000-00-00') {
			$html .= '<small class="text-muted"><span class="icon-calendar me-1" aria-hidden="true"></span>'
				. HTMLHelper::_('date', $atto->data_atto, Text::_('DATE_FORMAT_LC4')) . '</small>';
		}
		if (!empty($atto->ente_emanante)) {
			$html .= '<small class="text-muted">' . htmlspecialchars($atto->ente_emanante) . '</small>';
		}
		$html .= '</div>';

		// Allegati
		if (!empty($allegati)) {
			$html .= '<div class="mt-3 pt-2 border-top">';
			$html .= '<strong class="d-block mb-2">'
				. Text::_('COM_ALBOONLINE_ALLEGATI')
				. ' <span class="badge bg-secondary">' . count($allegati) . '</span></strong>';
			$html .= '<div class="list-group list-group-flush">';

			$token = Session::getFormToken();

			foreach ($allegati as $allegato) {
				$downloadUrl = Route::_('index.php?option=com_alboonline&task=download.file&id=' . (int) $allegato->id . '&' . $token . '=1');
				$nome = htmlspecialchars($allegato->titolo ?: $allegato->original_name ?: $allegato->filename);
				$size = $allegato->size > 0 ? ' <small class="text-muted">(' . HTMLHelper::_('number.bytes', $allegato->size) . ')</small>' : '';

				$html .= '<a href="' . $downloadUrl . '" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2">';
				$html .= '<span class="icon-file flex-shrink-0" aria-hidden="true"></span>';
				$html .= '<span class="text-truncate">' . $nome . $size . '</span>';
				$html .= '<span class="icon-download ms-auto flex-shrink-0" aria-hidden="true"></span>';
				$html .= '</a>';
			}

			$html .= '</div></div>';
		}

		$html .= '</div>'; // card-body

		// Footer: link to full act
		$html .= '<div class="card-footer text-end">';
		$html .= '<a href="' . $attoUrl . '" class="btn btn-sm btn-outline-danger">';
		$html .= '<span class="icon-external-link me-1" aria-hidden="true"></span>';
		$html .= Text::_('PLG_CONTENT_ALBOONLINE_VIEW_FULL');
		$html .= '</a>';
		$html .= '</div>';

		$html .= '</div>'; // card

		return $html;
	}
}
