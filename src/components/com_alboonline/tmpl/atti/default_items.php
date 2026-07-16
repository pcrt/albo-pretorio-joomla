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
use Pcrt\Component\Alboonline\Site\Helper\RouteHelper;
?>
<?php if (empty($this->items)) : ?>
	<div class="alert alert-info">
		<?php echo Text::_('COM_ALBOONLINE_NO_ATTI'); ?>
	</div>
<?php else : ?>
	<div class="table-responsive">
		<table class="table table-striped table-hover com-alboonline-table">
			<caption class="visually-hidden"><?php echo Text::_('COM_ALBOONLINE_ATTI_LIST'); ?></caption>
			<thead>
				<tr>
					<th scope="col" class="text-nowrap" style="width: 8.75rem;"><?php echo Text::_('COM_ALBOONLINE_FIELD_NUMERO_PROTOCOLLO_LABEL'); ?></th>
					<th scope="col"><?php echo Text::_('COM_ALBOONLINE_FIELD_TITOLO_LABEL'); ?></th>
					<th scope="col" class="text-nowrap"><?php echo Text::_('COM_ALBOONLINE_FIELD_PUBLISH_UP_LABEL'); ?></th>
					<th scope="col" class="text-nowrap d-none d-md-table-cell text-end"><?php echo Text::_('COM_ALBOONLINE_FIELD_TIPO_ATTO_LABEL'); ?></th>
					<th scope="col" class="text-nowrap d-none d-md-table-cell text-end"><?php echo Text::_('COM_ALBOONLINE_FIELD_CATEGORY_LABEL'); ?></th>
					<th scope="col" class="text-center text-nowrap"><?php echo Text::_('COM_ALBOONLINE_ALLEGATI'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->items as $item) : ?>
				<?php
					$link = Route::_(RouteHelper::getAttoRoute((int) $item->id));
					$isArchived = (int) $item->stato_atto === AlboonlineHelper::STATO_ARCHIVIATO;
					$tipiAtto = AlboonlineHelper::TIPO_ATTI;
					$tipoLabel = $tipiAtto[$item->tipo_atto] ?? $item->tipo_atto;
					$allegatiCount = (int) ($item->allegati_count ?? 0);
				?>
				<tr<?php echo $isArchived ? ' class="text-muted"' : ''; ?>>
					<td class="text-nowrap" style="width: 8.75rem;">
						<?php if (!empty($item->numero_protocollo)) : ?>
							<strong class="badge-protocollo"><?php echo htmlspecialchars($item->numero_protocollo); ?></strong>
						<?php else : ?>
							<span class="badge bg-secondary">&mdash;</span>
						<?php endif; ?>
					</td>
					<td>
						<a href="<?php echo $link; ?>">
							<?php echo htmlspecialchars($item->titolo); ?>
						</a>
						<?php if ($isArchived) : ?>
							<span class="badge bg-warning text-white ms-1"><?php echo Text::_('COM_ALBOONLINE_STATO_ARCHIVIATO'); ?></span>
						<?php endif; ?>
						<?php if (!empty($item->rettificato_da)) : ?>
							<span class="badge bg-info ms-1"><?php echo Text::_('COM_ALBOONLINE_RETTIFICATO'); ?></span>
						<?php endif; ?>
					</td>
					<td class="text-nowrap">
						<?php echo $item->publish_up ? HTMLHelper::_('date', $item->publish_up, Text::_('DATE_FORMAT_LC4')) : '&mdash;'; ?>
					</td>
					<td class="d-none d-md-table-cell text-end">
						<span class="badge bg-secondary"><?php echo htmlspecialchars($tipoLabel); ?></span>
					</td>
					<td class="d-none d-md-table-cell text-end">
						<span class="badge bg-light text-dark border"><?php echo htmlspecialchars($item->category_title ?? ''); ?></span>
					</td>
					<td class="text-center">
						<?php // Il conteggio allegati NON è un link (WCAG 2.4.1 / usabilità da
							  // tastiera). Prima lo era, e puntava allo STESSO indirizzo del
							  // titolo: un link duplicato, che non portava da nessuna parte di
							  // nuovo ma RADDOPPIAVA le fermate di Tab. Con 20 atti in pagina
							  // erano 40 pressioni per attraversare l'elenco invece di 20.
							  // Chi vuole gli allegati apre l'atto: il titolo è già lì accanto. ?>
						<?php if ($allegatiCount > 0) : ?>
							<span class="badge bg-dark badge-allegati">
								<span class="icon-file" aria-hidden="true"></span>
								<?php echo $allegatiCount; ?>
								<span class="visually-hidden">
									<?php echo Text::plural('COM_ALBOONLINE_ALLEGATI_COUNT', $allegatiCount); ?>
								</span>
							</span>
						<?php else : ?>
							<span class="text-muted">&mdash;</span>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php // Via d'uscita IN FONDO all'elenco. Il link di salto in cima serve solo a chi non c'è
		  // ancora entrato: chi è già dentro, per tornare ai filtri o alle categorie, dovrebbe
		  // rifare a ritroso tutta la lista. Questo lo riporta indietro in una sola mossa. ?>
	<a href="#albo-filtri" class="visually-hidden-focusable com-alboonline-skip">
		<?php echo Text::_('COM_ALBOONLINE_SKIP_BACK_FILTERS'); ?>
	</a>

	<?php if ($this->pagination->pagesTotal > 1) : ?>
	<nav class="com-alboonline-atti__pagination" aria-label="<?php echo Text::_('COM_ALBOONLINE_PAGINATION_LABEL'); ?>">
		<?php echo $this->pagination->getListFooter(); ?>
	</nav>
	<?php endif; ?>
<?php endif; ?>
