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
use Joomla\CMS\Session\Session;
use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper;
use Pcrt\Component\Alboonline\Site\Helper\RouteHelper;

$item       = $this->item;
$isArchived = (int) $item->stato_atto === AlboonlineHelper::STATO_ARCHIVIATO;
$tipoLabel  = AlboonlineHelper::getTipoLabel($item->tipo_atto);
$allegati   = $item->allegati ?? [];
?>
<div class="container">
<div class="com-alboonline-atto pt-4" itemscope itemtype="https://schema.org/GovernmentService">
	<?php // Archived banner ?>
	<?php if ($isArchived) : ?>
	<div class="alert alert-warning" role="alert">
		<span class="icon-archive" aria-hidden="true"></span>
		<?php echo Text::_('COM_ALBOONLINE_ATTO_ARCHIVED_NOTICE'); ?>
	</div>
	<?php endif; ?>

	<?php // Rectification banners ?>
	<?php if (!empty($item->atto_rettifica)) : ?>
	<div class="alert alert-info" role="alert">
		<span class="icon-info-circle" aria-hidden="true"></span>
		<?php echo Text::sprintf(
			'COM_ALBOONLINE_ATTO_RETTIFICATO_DA',
			Route::_(RouteHelper::getAttoRoute((int) $item->atto_rettifica->id)),
			htmlspecialchars($item->atto_rettifica->numero_protocollo ?: $item->atto_rettifica->titolo)
		); ?>
	</div>
	<?php endif; ?>

	<?php if (!empty($item->atto_rettificato)) : ?>
	<div class="alert alert-info" role="alert">
		<span class="icon-info-circle" aria-hidden="true"></span>
		<?php echo Text::sprintf(
			'COM_ALBOONLINE_ATTO_RETTIFICA_DI',
			Route::_(RouteHelper::getAttoRoute((int) $item->atto_rettificato->id)),
			htmlspecialchars($item->atto_rettificato->numero_protocollo ?: $item->atto_rettificato->titolo)
		); ?>
	</div>
	<?php endif; ?>

	<header class="com-alboonline-atto__header mb-4">
		<div class="d-flex justify-content-between align-items-start mb-2">
			<?php if (!empty($item->numero_protocollo)) : ?>
			<span class="badge bg-dark fs-5">
				<?php echo Text::_('COM_ALBOONLINE_FIELD_NUMERO_PROTOCOLLO_LABEL'); ?>:
				<?php echo htmlspecialchars($item->numero_protocollo); ?>
			</span>
			<?php endif; ?>
			<div class="d-flex flex-wrap gap-1">
				<span class="badge bg-secondary">
					<?php echo htmlspecialchars($tipoLabel); ?>
				</span>
				<?php if (!empty($item->category_title)) : ?>
				<span class="badge bg-light text-dark border">
					<?php echo htmlspecialchars($item->category_title); ?>
				</span>
				<?php endif; ?>
				<span class="badge <?php echo AlboonlineHelper::getStatoBadgeClass((int) $item->stato_atto); ?>">
					<?php echo htmlspecialchars(AlboonlineHelper::getStatoLabel((int) $item->stato_atto)); ?>
				</span>
			</div>
		</div>
		<h1 itemprop="name"><?php echo htmlspecialchars($item->titolo); ?></h1>
	</header>

	<div class="com-alboonline-atto__body">
		<div class="row">
			<div class="col-lg-8">
				<?php // Oggetto ?>
				<?php if (!empty($item->oggetto)) : ?>
				<section class="mb-4">
					<h2 class="h5"><?php echo Text::_('COM_ALBOONLINE_FIELD_OGGETTO_LABEL'); ?>:</h2>
					<p class="com-alboonline-oggetto" itemprop="description"><?php echo htmlspecialchars($item->oggetto); ?></p>
				</section>
				<?php endif; ?>

				<?php // Descrizione ?>
				<?php if (!empty($item->descrizione)) : ?>
				<section class="mb-4">
					<h2 class="h5"><?php echo Text::_('COM_ALBOONLINE_FIELD_DESCRIZIONE_LABEL'); ?></h2>
					<div><?php echo $item->descrizione; ?></div>
				</section>
				<?php endif; ?>

				<?php // Nota rettifica ?>
				<?php if (!empty($item->note_rettifica)) : ?>
				<section class="mb-4">
					<h2 class="h5"><?php echo Text::_('COM_ALBOONLINE_FIELD_NOTE_RETTIFICA_LABEL'); ?></h2>
					<div class="alert alert-light"><?php echo nl2br(htmlspecialchars($item->note_rettifica)); ?></div>
				</section>
				<?php endif; ?>

				<?php if (!empty($allegati)) : ?>
				<?php
					$canDownload = true;

					if ($isArchived)
					{
						$user = \Joomla\CMS\Factory::getApplication()->getIdentity();
						$canDownload = $user->authorise('alboonline.download.archived', 'com_alboonline');
					}
				?>
				<section class="mb-4">
					<h2 class="h5 d-inline-flex align-items-center gap-2">
						<span class="icon-paperclip" aria-hidden="true"></span>
						<?php echo Text::_('COM_ALBOONLINE_ALLEGATI'); ?>
						<span class="badge bg-secondary"><?php echo count($allegati); ?></span>
					</h2>
					<ul class="list-group">
						<?php foreach ($allegati as $allegato) : ?>
						<li class="list-group-item list-group-item-action d-flex flex-wrap align-items-start gap-2">
							<span class="icon-file flex-shrink-0 mt-1" aria-hidden="true"></span>
							<div class="text-truncate">
								<?php if (!empty($allegato->titolo)) : ?>
									<strong><?php echo htmlspecialchars($allegato->titolo); ?></strong>
									<br><small class="text-muted"><?php echo htmlspecialchars($allegato->original_name ?: $allegato->filename); ?></small>
								<?php else : ?>
									<?php echo htmlspecialchars($allegato->original_name ?: $allegato->filename); ?>
								<?php endif; ?>
								<?php if ($allegato->size > 0) : ?>
								<small class="text-muted ms-1">(<?php echo HTMLHelper::_('number.bytes', $allegato->size); ?>)</small>
								<?php endif; ?>
							</div>
							<div class="ms-auto flex-shrink-0 align-self-center">
								<?php if ($canDownload) : ?>
								<a href="<?php echo Route::_(RouteHelper::getDownloadRoute((int) $allegato->id) . '&' . Session::getFormToken() . '=1'); ?>"
								   class="btn btn-sm btn-primary"
								   aria-label="<?php echo Text::sprintf('COM_ALBOONLINE_DOWNLOAD_ARIA', htmlspecialchars($allegato->original_name ?: $allegato->filename)); ?>">
									<span class="icon-download" aria-hidden="true"></span>
									<?php echo Text::_('COM_ALBOONLINE_DOWNLOAD'); ?>
								</a>
								<?php else : ?>
								<span class="badge bg-secondary" title="<?php echo Text::_('COM_ALBOONLINE_DOWNLOAD_ARCHIVED_DENIED'); ?>">
									<span class="icon-lock" aria-hidden="true"></span>
									<span class="visually-hidden"><?php echo Text::_('COM_ALBOONLINE_DOWNLOAD_ARCHIVED_DENIED'); ?></span>
								</span>
								<?php endif; ?>
							</div>
							<?php // S3-T5: impronta SHA-256 esposta come metadato ri-verificabile dal cittadino. ?>
							<?php if (!empty($allegato->hash)) : ?>
							<div class="w-100 mt-1">
								<details class="small">
									<summary class="text-muted">
										<span class="icon-lock" aria-hidden="true"></span>
										<?php echo Text::_('COM_ALBOONLINE_IMPRONTA_SHA256'); ?>
									</summary>
									<code class="small text-break d-block mt-1"><?php echo htmlspecialchars($allegato->hash); ?></code>
									<span class="text-muted"><?php echo Text::_('COM_ALBOONLINE_IMPRONTA_HELP'); ?></span>
								</details>
							</div>
							<?php endif; ?>
						</li>
						<?php endforeach; ?>
					</ul>
				</section>
				<?php endif; ?>

				<?php if (!empty($item->atti_correlati)) : ?>
				<section class="mb-4">
					<h2 class="h5 d-inline-flex align-items-center gap-2">
						<span class="icon-link" aria-hidden="true"></span>
						<?php echo Text::_('COM_ALBOONLINE_ATTI_CORRELATI'); ?>
					</h2>
					<ul class="list-group">
						<?php foreach ($item->atti_correlati as $correlato) : ?>
						<li class="list-group-item">
							<a href="<?php echo Route::_(RouteHelper::getAttoRoute((int) $correlato->id)); ?>">
								<?php if (!empty($correlato->numero_protocollo)) : ?>
									<span class="badge bg-dark me-1"><?php echo htmlspecialchars($correlato->numero_protocollo); ?></span>
								<?php endif; ?>
								<?php echo htmlspecialchars($correlato->titolo); ?>
							</a>
						</li>
						<?php endforeach; ?>
					</ul>
				</section>
				<?php endif; ?>
			</div>

			<div class="col-lg-4 com-alboonline-atto__sidebar align-self-lg-start"
				 style="position: sticky; top: 10rem;">
				<?php // Info card (unica card in sidebar, sempre breve, sticky reale) ?>
				<div class="card mb-4">
					<div class="card-header">
						<h2 class="h6 mb-0"><?php echo Text::_('COM_ALBOONLINE_ATTO_INFO'); ?></h2>
					</div>
					<div class="card-body">
						<dl class="row g-0 mb-0">
							<?php if (!empty($item->ente_emanante)) : ?>
							<dt class="col-sm-5"><?php echo Text::_('COM_ALBOONLINE_FIELD_ENTE_EMANANTE_LABEL'); ?></dt>
							<dd class="col-sm-7"><?php echo htmlspecialchars($item->ente_emanante); ?></dd>
							<?php endif; ?>

							<?php if (!empty($item->responsabile)) : ?>
							<dt class="col-sm-5"><?php echo Text::_('COM_ALBOONLINE_FIELD_RESPONSABILE_LABEL'); ?></dt>
							<dd class="col-sm-7"><?php echo htmlspecialchars($item->responsabile); ?></dd>
							<?php endif; ?>

							<?php if (!empty($item->data_atto)) : ?>
							<dt class="col-sm-5"><?php echo Text::_('COM_ALBOONLINE_FIELD_DATA_ATTO_LABEL'); ?></dt>
							<dd class="col-sm-7"><?php echo HTMLHelper::_('date', $item->data_atto, Text::_('DATE_FORMAT_LC4')); ?></dd>
							<?php endif; ?>

							<?php // S3-T4: segnatura di protocollo dell'ente (se presente). ?>
							<?php if (!empty($item->segnatura_protocollo)) : ?>
							<dt class="col-sm-5"><?php echo Text::_('COM_ALBOONLINE_FIELD_SEGNATURA_LABEL'); ?></dt>
							<dd class="col-sm-7">
								<?php echo htmlspecialchars($item->segnatura_protocollo); ?><?php if (!empty($item->segnatura_data) && $item->segnatura_data !== '0000-00-00') : ?> &middot; <?php echo HTMLHelper::_('date', $item->segnatura_data, Text::_('DATE_FORMAT_LC4')); ?><?php endif; ?>
							</dd>
							<?php endif; ?>

							<?php if (!empty($item->publish_up)) : ?>
							<dt class="col-sm-5"><?php echo Text::_('COM_ALBOONLINE_FIELD_PUBLISH_UP_LABEL'); ?></dt>
							<dd class="col-sm-7"><?php echo HTMLHelper::_('date', $item->publish_up, Text::_('DATE_FORMAT_LC4')); ?></dd>
							<?php endif; ?>

							<?php if (!empty($item->publish_down)) : ?>
							<dt class="col-sm-5"><?php echo Text::_('COM_ALBOONLINE_FIELD_PUBLISH_DOWN_LABEL'); ?></dt>
							<dd class="col-sm-7"><?php echo HTMLHelper::_('date', $item->publish_down, Text::_('DATE_FORMAT_LC4')); ?></dd>
							<?php endif; ?>

						</dl>

						<?php // S3-T3: metadati del documento (mappati sull'Allegato 5 delle Linee Guida AgID). ?>
						<?php $metadati = \Pcrt\Component\Alboonline\Administrator\Helper\MetadatiAgid::build($item); ?>
						<?php if (!empty($metadati)) : ?>
						<details class="mt-2">
							<summary class="small text-muted"><?php echo Text::_('COM_ALBOONLINE_META_TITOLO'); ?></summary>
							<dl class="row small mt-2 mb-0">
								<?php foreach ($metadati as $m) : ?>
								<dt class="col-sm-5"><?php echo Text::_($m->label); ?></dt>
								<dd class="col-sm-7"><?php echo htmlspecialchars($m->value); ?></dd>
								<?php endforeach; ?>
							</dl>
							<p class="small text-muted mt-2 mb-0"><?php echo Text::_('COM_ALBOONLINE_META_NOTA'); ?></p>
						</details>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>

	<footer class="com-alboonline-atto__footer my-5">
		<?php
		// Il back-link rispetta lo stato dell'atto:
		// archiviato (3) -> menu Archivio, altrimenti -> menu Albo Online.
		$backItemId = RouteHelper::findAttiMenuItemId((int) $item->stato_atto);
		$backUrl    = $backItemId > 0
			? Route::_('index.php?option=com_alboonline&view=atti&Itemid=' . $backItemId)
			: Route::_(RouteHelper::getAttiRoute((int) $item->catid));
		?>
		<a href="<?php echo $backUrl; ?>"
		   class="btn btn-outline-secondary">
			<span class="icon-arrow-left" aria-hidden="true"></span>
			<?php echo Text::_('COM_ALBOONLINE_BACK_TO_LIST'); ?>
		</a>
	</footer>
</div>
</div>
