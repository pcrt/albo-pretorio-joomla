<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper;

$showSidebar = (int) $this->params->get('show_sidebar', 1);
$showFilter  = (int) $this->params->get('show_filter', 1);
$action      = Route::_('index.php?option=com_alboonline&view=atti');
$resetLink   = Route::_('index.php?option=com_alboonline&view=atti&filter_reset=1');

// Current filter state
$filterSearch = $this->state->get('filter.search', '');
$filterCatid  = (int) $this->state->get('filter.catid', 0);
$filterTipo   = $this->state->get('filter.tipo_atto', '');
$filterAnno   = $this->state->get('filter.anno_protocollo', '');
$currentOrder = $this->state->get('list.fullordering', 'a.publish_up DESC');
$totalItems   = $this->pagination->total ?? 0;

// Tipo atto options (from component configuration)
$tipiAtto = AlboonlineHelper::getTipiAtto();

// Ordering options
$orderOptions = [
	'a.publish_up DESC'        => 'COM_ALBOONLINE_SORT_PUBLISH_UP_DESC',
	'a.publish_up ASC'         => 'COM_ALBOONLINE_SORT_PUBLISH_UP_ASC',
	'a.titolo ASC'             => 'COM_ALBOONLINE_SORT_TITOLO_ASC',
	'a.titolo DESC'            => 'COM_ALBOONLINE_SORT_TITOLO_DESC',
	'a.numero_protocollo ASC'  => 'COM_ALBOONLINE_SORT_PROTOCOLLO_ASC',
	'a.numero_protocollo DESC' => 'COM_ALBOONLINE_SORT_PROTOCOLLO_DESC',
	'a.data_atto DESC'         => 'COM_ALBOONLINE_SORT_DATA_ATTO_DESC',
	'a.data_atto ASC'          => 'COM_ALBOONLINE_SORT_DATA_ATTO_ASC',
];
?>
<div class="com-alboonline-atti" id="alboonline-explorer">
	<?php // Link di salto (WCAG 2.4.1, Salto dei blocchi). Invisibili finché non li si raggiunge
		  // con Tab, poi ben visibili. Servono a chi naviga da tastiera: senza, per uscire
		  // dall'elenco bisogna attraversarlo tutto — con 20 atti e due link per riga (l'atto e
		  // il suo PDF) sono 40 pressioni di Tab, e nessuna via di fuga. ?>
	<a href="#albo-elenco" class="visually-hidden-focusable com-alboonline-skip">
		<?php echo Text::_('COM_ALBOONLINE_SKIP_TO_LIST'); ?>
	</a>
	<a href="#albo-fine-elenco" class="visually-hidden-focusable com-alboonline-skip">
		<?php echo Text::_('COM_ALBOONLINE_SKIP_LIST'); ?>
	</a>

	<h1><?php
		$pageHeading = $this->params->get('page_heading');
		echo htmlspecialchars($pageHeading ?: $this->document->getTitle(), ENT_QUOTES, 'UTF-8');
	?></h1>

	<form action="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>"
		  method="post" name="adminForm" id="adminForm">

		<?php if ($showFilter) : ?>
		<div class="com-alboonline-filterbar p-4 mb-4 rounded-3" id="albo-filtri" tabindex="-1">

			<!-- Riga 1: Ricerca -->
			<div class="row">
				<div class="col-12">
					<label for="filter_search" class="form-label fw-semibold">
						<?php echo Text::_('COM_ALBOONLINE_FILTER_SEARCH_LABEL'); ?>
					</label>
					<?php // UN SOLO pulsante di invio per tutta la barra (M27). Prima ce n'erano
						  // tre — «Cerca», «Applica Filtri» e «Ordina» — tutti submit dello
						  // stesso form: premerne uno qualsiasi applicava comunque ricerca,
						  // filtri e ordinamento insieme. Tre comandi, un solo effetto.
						  // NB: l'assenza di auto-submit è voluta (WCAG 3.2.2: nessun cambio
						  // di contesto non richiesto), quindi il pulsante deve restare. ?>
					<div class="input-group">
						<input type="text"
							   name="filter[search]"
							   id="filter_search"
							   class="form-control form-control-lg"
							   value="<?php echo htmlspecialchars($filterSearch, ENT_QUOTES, 'UTF-8'); ?>"
							   placeholder="<?php echo Text::_('COM_ALBOONLINE_FILTER_SEARCH_PLACEHOLDER'); ?>">
						<button type="submit" class="btn btn-primary btn-lg">
							<span class="icon-search" aria-hidden="true"></span>
							<?php echo Text::_('COM_ALBOONLINE_FILTER_APPLY'); ?>
						</button>
					</div>
					<div class="form-text"><?php echo Text::_('COM_ALBOONLINE_FILTER_APPLY_HINT'); ?></div>
				</div>
			</div>

			<!-- Riga 2: Filtri -->
			<div class="row mt-3">
				<?php // La categoria si sceglie dall'ALBERO nella colonna di sinistra, che è il
					  // controllo più adatto a una gerarchia e il più accessibile (widget ARIA
					  // con navigazione a frecce). Questa tendina ne era il doppione — stessa
					  // funzione, stessa voce «Tutte le categorie», due stati da tenere allineati
					  // (M27). Resta solo quando l'albero è disattivato, dove serve davvero. ?>
				<?php if (!$showSidebar || empty($this->categoryTree)) : ?>
				<div class="col-md-4 mb-3 mb-md-0">
					<label for="filter_catid" class="form-label fw-semibold">
						<?php echo Text::_('COM_ALBOONLINE_FILTER_CATEGORY'); ?>
					</label>
					<select name="filter[catid]"
							id="filter_catid"
							class="form-select">
						<option value=""><?php echo Text::_('COM_ALBOONLINE_FILTER_ALL_CATEGORIES'); ?></option>
						<?php foreach ($this->categories as $cat) : ?>
						<option value="<?php echo (int) $cat->id; ?>"
							<?php echo (int) $cat->id === $filterCatid ? 'selected' : ''; ?>>
							<?php echo str_repeat('— ', max(0, (int) $cat->level - 1))
								. htmlspecialchars($cat->title, ENT_QUOTES, 'UTF-8'); ?>
							(<?php echo (int) $cat->num_atti; ?>)
						</option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php else : ?>
				<input type="hidden" name="filter[catid]" value="<?php echo $filterCatid ?: ''; ?>">
				<?php endif; ?>

				<div class="col-md-4 mb-3 mb-md-0">
					<label for="filter_tipo_atto" class="form-label fw-semibold">
						<?php echo Text::_('COM_ALBOONLINE_FIELD_TIPO_ATTO_LABEL'); ?>
					</label>
					<select name="filter[tipo_atto]"
							id="filter_tipo_atto"
							class="form-select">
						<option value=""><?php echo Text::_('COM_ALBOONLINE_FILTER_ALL_TYPES'); ?></option>
						<?php foreach ($tipiAtto as $value => $label) : ?>
						<option value="<?php echo $value; ?>"
							<?php echo $filterTipo === $value ? 'selected' : ''; ?>>
							<?php echo Text::_($label); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="col-md-4">
					<label for="filter_anno" class="form-label fw-semibold">
						<?php echo Text::_('COM_ALBOONLINE_FILTER_ANNO_LABEL'); ?>
					</label>
					<select name="filter[anno_protocollo]"
							id="filter_anno"
							class="form-select">
						<option value=""><?php echo Text::_('COM_ALBOONLINE_FILTER_ALL_YEARS'); ?></option>
						<?php foreach ($this->years as $year) : ?>
						<option value="<?php echo (int) $year; ?>"
							<?php echo $filterAnno == $year ? 'selected' : ''; ?>>
							<?php echo (int) $year; ?>
						</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<!-- Riga 3: Azioni e Ordinamento -->
			<div class="row mt-4 align-items-end">
				<div class="col-md-6 mb-3 mb-md-0">
					<?php // Il pulsante «Applica Filtri» stava qui: era il secondo dei tre submit
						  // equivalenti (M27). Ne resta uno solo, in cima alla barra. ?>
					<a href="<?php echo $resetLink; ?>"
					   class="btn btn-outline-secondary ms-2"
					   onclick="document.querySelector('#filter_search').value='';
								document.querySelectorAll('.com-alboonline-filterbar select').forEach(function(s){s.value='';});
								document.getElementById('adminForm').submit();
								return false;">
						<span class="icon-times" aria-hidden="true"></span>
						<?php echo Text::_('COM_ALBOONLINE_FILTER_RESET'); ?>
					</a>
				</div>
				<div class="col-md-6 d-md-flex justify-content-md-end align-items-center gap-3">
					<div class="d-flex align-items-center gap-2 mb-2 mb-md-0">
						<label for="list_fullordering" class="form-label mb-0 text-nowrap fw-semibold">
							<?php echo Text::_('JGLOBAL_SORT_BY'); ?>
						</label>
						<select name="list[fullordering]"
								id="list_fullordering"
								class="form-select form-select-sm"
								style="width:auto;min-width:14rem;">
							<?php foreach ($orderOptions as $value => $label) : ?>
							<option value="<?php echo $value; ?>"
								<?php echo $currentOrder === $value ? 'selected' : ''; ?>>
								<?php echo Text::_($label); ?>
							</option>
							<?php endforeach; ?>
						</select>
						<?php // Il pulsante «Ordina» stava qui: terzo submit equivalente (M27).
							  // L'ordinamento si sceglie e si applica con l'unico pulsante della
							  // barra, insieme a ricerca e filtri. ?>
					</div>
					<span class="badge bg-secondary fs-6" role="status" aria-live="polite">
						<?php echo Text::plural('COM_ALBOONLINE_RESULTS_COUNT', $totalItems); ?>
					</span>
				</div>
			</div>

		</div>
		<?php endif; ?>

		<div class="row">
			<?php if ($showSidebar && !empty($this->categoryTree)) : ?>
			<div class="col-lg-3 com-alboonline-atti__sidebar">
				<?php echo $this->loadTemplate('sidebar'); ?>
			</div>
			<div class="col-lg-9 com-alboonline-atti__main" id="albo-elenco" tabindex="-1">
				<?php echo $this->loadTemplate('items'); ?>
			</div>
			<?php else : ?>
			<div class="col-12 com-alboonline-atti__main" id="albo-elenco" tabindex="-1">
				<?php echo $this->loadTemplate('items'); ?>
			</div>
			<?php endif; ?>
		</div>

		<?php // Bersaglio del salto: chi arriva qui ha scavalcato l'intero elenco. `tabindex=-1`
			  // lo rende focalizzabile via link ma NON lo mette nel percorso di Tab. ?>
		<div id="albo-fine-elenco" tabindex="-1"></div>

		<input type="hidden" name="task" value="">
		<input type="hidden" name="boxchecked" value="0">
		<?php echo HTMLHelper::_('form.token'); ?>
	</form>
</div>
