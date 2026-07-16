<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper;

$wa = $this->document->getWebAssetManager();
$wa->useScript('table.columns')
	->useScript('multiselect')
	->useStyle('com_alboonline.admin');

$user      = Factory::getApplication()->getIdentity();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$saveOrder = $listOrder == 'a.ordering';

if ($saveOrder && !empty($this->items))
{
	$saveOrderingUrl = 'index.php?option=com_alboonline&task=atti.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
	HTMLHelper::_('draggablelist.draggable');
}
?>

<form action="<?php echo Route::_('index.php?option=com_alboonline&view=atti'); ?>" method="post"
	  name="adminForm" id="adminForm">
	<div class="row">
		<div class="col-md-12">
			<div id="j-main-container" class="j-main-container">
				<?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

				<?php if (empty($this->items)) : ?>
					<div class="alert alert-info">
						<span class="icon-info-circle" aria-hidden="true"></span>
						<?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
					</div>
				<?php else : ?>

				<table class="table itemList" id="attiList">
					<caption class="visually-hidden">
						<?php echo Text::_('COM_ALBOONLINE_TABLE_CAPTION'); ?>
					</caption>
					<thead>
						<tr>
							<td class="w-1 text-center">
								<?php echo HTMLHelper::_('grid.checkall'); ?>
							</td>
							<th scope="col" class="w-1 text-center d-none d-md-table-cell">
								<?php echo HTMLHelper::_('searchtools.sort', '', 'a.ordering', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING', 'icon-sort'); ?>
							</th>
							<?php // Nessuna colonna per il flag `state` di Joomla: la visibilita' pubblica
								  // di un atto e' governata solo dal workflow legale (colonna Stato).
								  // Vedi M1 in docs/gap-analysis/05-collaudo-manuale.md ?>
							<th scope="col" class="w-10">
								<?php echo HTMLHelper::_('searchtools.sort', 'COM_ALBOONLINE_HEADING_PROTOCOLLO', 'a.numero_protocollo', $listDirn, $listOrder); ?>
							</th>
							<th scope="col">
								<?php echo HTMLHelper::_('searchtools.sort', 'COM_ALBOONLINE_HEADING_TITOLO', 'a.titolo', $listDirn, $listOrder); ?>
							</th>
							<th scope="col" class="w-10">
								<?php echo HTMLHelper::_('searchtools.sort', 'COM_ALBOONLINE_HEADING_TIPO_ATTO', 'a.tipo_atto', $listDirn, $listOrder); ?>
							</th>
							<th scope="col" class="w-10">
								<?php echo HTMLHelper::_('searchtools.sort', 'COM_ALBOONLINE_HEADING_STATO_ATTO', 'a.stato_atto', $listDirn, $listOrder); ?>
							</th>
							<th scope="col" class="w-10 d-none d-lg-table-cell">
								<?php echo HTMLHelper::_('searchtools.sort', 'COM_ALBOONLINE_HEADING_CODICE_CORRELAZIONE', 'a.codice_correlazione', $listDirn, $listOrder); ?>
							</th>
							<th scope="col" class="w-5 text-center d-none d-lg-table-cell">
								<?php echo Text::_('COM_ALBOONLINE_HEADING_ALLEGATI'); ?>
							</th>
							<th scope="col" class="w-10 d-none d-lg-table-cell">
								<?php echo HTMLHelper::_('searchtools.sort', 'JCATEGORY', 'category_title', $listDirn, $listOrder); ?>
							</th>
							<th scope="col" class="w-5 d-none d-lg-table-cell">
								<?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
							</th>
						</tr>
					</thead>
					<tfoot>
						<tr>
							<td colspan="10">
								<?php echo $this->pagination->getListFooter(); ?>
							</td>
						</tr>
					</tfoot>
					<tbody <?php if (!empty($saveOrder)) : ?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>"<?php endif; ?>>
						<?php foreach ($this->items as $i => $item) :
							$canEdit    = $user->authorise('core.edit', 'com_alboonline');
							$canCheckin = $user->authorise('core.manage', 'com_alboonline');
							$canChange  = $user->authorise('core.edit.state', 'com_alboonline');
							$statoAtto  = (int) $item->stato_atto;
						?>
						<tr class="row<?php echo $i % 2; ?>" data-draggable-group="1" data-transition>
							<td class="text-center">
								<?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
							</td>
							<td class="text-center d-none d-md-table-cell">
								<?php
								$iconClass = '';
								if (!$canChange) {
									$iconClass = ' inactive';
								} elseif (!$saveOrder) {
									$iconClass = ' inactive" title="' . Text::_('JORDERINGDISABLED');
								}
								?>
								<span class="sortable-handler<?php echo $iconClass; ?>">
									<span class="icon-ellipsis-v" aria-hidden="true"></span>
								</span>
								<?php if ($canChange && $saveOrder) : ?>
								<input type="text" name="order[]" size="5" value="<?php echo $item->ordering; ?>" class="width-20 text-area-order hidden" />
								<?php endif; ?>
							</td>
							<td>
								<?php if ($item->numero_protocollo) : ?>
								<span class="badge bg-primary"><?php echo $this->escape($item->numero_protocollo); ?></span>
								<a href="<?php echo Route::_('index.php?option=com_alboonline&view=referto&id=' . (int) $item->id . '&tmpl=component'); ?>"
								   target="_blank" rel="noopener" class="ms-1 text-decoration-none"
								   title="<?php echo Text::_('COM_ALBOONLINE_REFERTO_TOOLTIP'); ?>"
								   aria-label="<?php echo Text::_('COM_ALBOONLINE_REFERTO_TOOLTIP'); ?>">
									<span class="icon-file-text" aria-hidden="true"></span>
									<span class="visually-hidden"><?php echo Text::_('COM_ALBOONLINE_REFERTO'); ?></span>
								</a>
								<?php else : ?>
								<span class="badge bg-secondary">&mdash;</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if (isset($item->checked_out) && $item->checked_out && ($canEdit || $canChange)) : ?>
									<?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->uEditor, $item->checked_out_time, 'atti.', $canCheckin); ?>
								<?php endif; ?>
								<?php if ($canEdit) : ?>
								<a href="<?php echo Route::_('index.php?option=com_alboonline&task=atto.edit&id=' . (int) $item->id); ?>">
									<?php echo $this->escape($item->titolo); ?>
								</a>
								<?php else : ?>
									<?php echo $this->escape($item->titolo); ?>
								<?php endif; ?>
							</td>
							<td>
								<?php echo htmlspecialchars(AlboonlineHelper::getTipoLabel($item->tipo_atto)); ?>
							</td>
							<td>
								<?php if ($statoAtto === AlboonlineHelper::STATO_BOZZA && $canChange) : ?>
								<a href="<?php echo Route::_('index.php?option=com_alboonline&task=atto.publish&id=' . (int) $item->id . '&return=list&' . Session::getFormToken() . '=1'); ?>"
								   class="badge <?php echo AlboonlineHelper::getStatoBadgeClass($statoAtto); ?>"
								   title="<?php echo Text::_('COM_ALBOONLINE_CLICK_TO_PUBLISH'); ?>"
								   onclick="return confirm('<?php echo Text::_('COM_ALBOONLINE_CONFIRM_PUBLISH', true); ?>')">
									<?php echo $this->escape(AlboonlineHelper::getStatoLabel($statoAtto)); ?>
								</a>
								<?php else : ?>
								<span class="badge <?php echo AlboonlineHelper::getStatoBadgeClass($statoAtto); ?>">
									<?php echo $this->escape(AlboonlineHelper::getStatoLabel($statoAtto)); ?>
								</span>
								<?php endif; ?>
							</td>
							<td class="d-none d-lg-table-cell">
								<?php if (!empty($item->codice_correlazione)) : ?>
								<span class="badge bg-outline-secondary border text-dark small"><?php echo $this->escape($item->codice_correlazione); ?></span>
								<?php else : ?>
								<span class="text-muted">&mdash;</span>
								<?php endif; ?>
							</td>
							<td class="text-center d-none d-lg-table-cell">
								<?php $allegatiCount = (int) ($item->allegati_count ?? 0); ?>
								<?php if ($allegatiCount > 0) : ?>
								<span class="badge bg-info badge-allegati">
									<span class="icon-file" aria-hidden="true"></span>
									<?php echo $allegatiCount; ?>
								</span>
								<?php else : ?>
								<span class="text-muted">&mdash;</span>
								<?php endif; ?>
							</td>
							<td class="d-none d-lg-table-cell">
								<?php echo $this->escape($item->category_title ?? ''); ?>
							</td>
							<td class="d-none d-lg-table-cell">
								<?php echo (int) $item->id; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php endif; ?>

				<input type="hidden" name="task" value="" />
				<input type="hidden" name="boxchecked" value="0" />
				<input type="hidden" name="list[fullorder]" value="<?php echo $listOrder; ?> <?php echo $listDirn; ?>" />
				<?php echo HTMLHelper::_('form.token'); ?>
			</div>
		</div>
	</div>
</form>
