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
$wa->useScript('keepalive')
	->useScript('form.validate')
	->useStyle('com_alboonline.admin')
	->useScript('com_alboonline.admin');

// Servono ad admin.js per i pulsanti «Carica» ed «Elimina» della scheda allegati. Vanno
// passate di qui: admin.js e' un file statico e non puo' tradurre da solo.
Text::script('COM_ALBOONLINE_ALLEGATI_CARICA_NESSUN_FILE');
Text::script('COM_ALBOONLINE_ALLEGATO_DELETE_CONFIRM');

$isNew = ($this->item->id == 0);
$statoAtto = (int) ($this->item->stato_atto ?? 0);
$isLocked = AlboonlineHelper::isLocked($this->item);
$allegati = $this->item->allegati ?? [];
?>

<?php if (!$isNew && $isLocked) : ?>
<div class="alert alert-info border-info mb-4" role="alert">
	<div class="d-flex align-items-start">
		<span class="icon-lock fs-4 me-3 mt-1" aria-hidden="true"></span>
		<div>
			<h4 class="alert-heading h5 mb-1"><?php echo Text::_('COM_ALBOONLINE_LOCKED_TITLE'); ?></h4>
			<p class="mb-2"><?php echo Text::_('COM_ALBOONLINE_LOCKED_EXPLANATION'); ?></p>
			<hr class="my-2">
			<p class="mb-0 small">
				<span class="icon-copy me-1" aria-hidden="true"></span>
				<?php echo Text::_('COM_ALBOONLINE_LOCKED_SUGGEST_RECTIFY'); ?>
			</p>
		</div>
	</div>
</div>

<?php // PROROGA: modifiable field for locked published/archived acts ?>
<?php if ($statoAtto >= AlboonlineHelper::STATO_PUBBLICATO) : ?>
<?php $isArchivedAtto = ($statoAtto === AlboonlineHelper::STATO_ARCHIVIATO); ?>
<div class="card border-primary mb-4">
	<div class="card-header bg-primary text-white">
		<span class="icon-calendar me-1" aria-hidden="true"></span>
		<?php echo Text::_('COM_ALBOONLINE_EXTEND_TITLE'); ?>
	</div>
	<div class="card-body">
		<?php if ($isArchivedAtto) : ?>
		<div class="alert alert-warning small mb-3">
			<span class="icon-info-circle me-1" aria-hidden="true"></span>
			<?php echo Text::_('COM_ALBOONLINE_EXTEND_REACTIVATE_WARNING'); ?>
		</div>
		<?php endif; ?>
		<p class="card-text small text-muted mb-3"><?php echo Text::_('COM_ALBOONLINE_EXTEND_EXPLANATION'); ?></p>
		<form action="<?php echo Route::_('index.php?option=com_alboonline&task=atto.extendPublishDown&id=' . (int) $this->item->id); ?>"
			  method="post">
			<label class="form-label fw-bold" for="extend_publish_down">
				<?php echo Text::_('COM_ALBOONLINE_FIELD_PUBLISH_DOWN'); ?>
			</label>
			<div class="text-muted small mb-2">
				<?php echo Text::_('COM_ALBOONLINE_EXTEND_CURRENT'); ?>:
				<strong><?php echo HTMLHelper::_('date', $this->item->publish_down, 'd/m/Y H:i'); ?></strong>
			</div>
			<?php // Input e pulsante sulla STESSA riga, allineati in alto (align-items-start):
				  // l'aiuto sotto l'input non deve spingere giu' il pulsante. ?>
			<div class="row g-2 align-items-start">
				<div class="col-md-5">
					<?php // `min` va espresso nel fuso dell'ente (l'input datetime-local e' "naive"),
						  // non in quello del server: Factory::getDate()->format(..., true) = locale. ?>
					<input type="datetime-local" id="extend_publish_down" name="extend_publish_down"
						   class="form-control" required aria-describedby="extend_publish_down_help"
						   min="<?php echo Factory::getDate()->format('Y-m-d\TH:i', true); ?>" />
					<div class="form-text" id="extend_publish_down_help">
						<?php echo Text::_('COM_ALBOONLINE_EXTEND_HINT'); ?>
					</div>
				</div>
				<div class="col-auto">
					<button type="submit" class="btn btn-primary">
						<span class="icon-calendar-plus me-1" aria-hidden="true"></span>
						<?php echo Text::_($isArchivedAtto ? 'COM_ALBOONLINE_TOOLBAR_REACTIVATE' : 'COM_ALBOONLINE_TOOLBAR_EXTEND'); ?>
					</button>
				</div>
			</div>
			<?php echo HTMLHelper::_('form.token'); ?>
		</form>
	</div>
</div>
<?php endif; ?>

<?php // ATTO ORFANO (M30): la categoria non esiste piu'. Su un atto blindato il campo
	  // Categoria e' di sola lettura, quindi questa e' l'unica via per rimediare. E'
	  // ammessa perche' la categoria e' una classificazione: non tocca termini, protocollo,
	  // date ne' documento. L'azione finisce nell'audit log. ?>
<?php if (!$isNew && !empty($this->categoriaOrfana)) : ?>
<div class="card border-warning mb-4">
	<div class="card-header bg-warning">
		<span class="icon-warning me-1" aria-hidden="true"></span>
		<?php echo Text::_('COM_ALBOONLINE_CATEGORIA_ORFANA_TITLE'); ?>
	</div>
	<div class="card-body">
		<p class="card-text"><?php echo Text::_('COM_ALBOONLINE_CATEGORIA_ORFANA_EXPLANATION'); ?></p>

		<?php if (!empty($this->categorieDisponibili)) : ?>
		<form action="<?php echo Route::_('index.php?option=com_alboonline&task=atto.riassegnaCategoria&id=' . (int) $this->item->id); ?>"
			  method="post" class="row g-3 align-items-end">
			<div class="col-md-9">
				<label class="form-label fw-bold" for="nuova_categoria">
					<?php echo Text::_('COM_ALBOONLINE_CATEGORIA_ORFANA_SELECT_LABEL'); ?>
				</label>
				<select name="nuova_categoria" id="nuova_categoria" class="form-select" required>
					<option value=""><?php echo Text::_('JOPTION_SELECT_CATEGORY'); ?></option>
					<?php foreach ($this->categorieDisponibili as $cat) : ?>
					<option value="<?php echo (int) $cat->id; ?>">
						<?php echo str_repeat('&nbsp;&nbsp;', max(0, (int) $cat->level - 1))
							. htmlspecialchars($cat->title, ENT_QUOTES, 'UTF-8'); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="col-md-3">
				<button type="submit" class="btn btn-warning w-100">
					<?php echo Text::_('COM_ALBOONLINE_CATEGORIA_ORFANA_SUBMIT'); ?>
				</button>
			</div>
			<?php echo HTMLHelper::_('form.token'); ?>
		</form>
		<?php else : ?>
		<div class="alert alert-danger mb-0">
			<?php echo Text::_('COM_ALBOONLINE_CATEGORIA_ORFANA_NESSUNA'); ?>
		</div>
		<?php endif; ?>
	</div>
</div>
<?php endif; ?>

<?php // DIRITTO ALL'OBLIO ATTIVO: la riattivazione e' sbarrata finche' non lo si revoca,
	  // con motivazione tracciata nell'audit log. Vedi M16.
	  $canRevokeOblio = AlboonlineHelper::getActions()->get('alboonline.revokeoblio'); ?>
<?php if (!$isNew && $isLocked && (int) ($this->item->diritto_oblio ?? 0) === 1) : ?>
<div class="card border-danger mb-4">
	<div class="card-header bg-danger text-white">
		<span class="icon-lock me-1" aria-hidden="true"></span>
		<?php echo Text::_('COM_ALBOONLINE_OBLIO_ATTIVO_TITLE'); ?>
	</div>
	<div class="card-body">
		<p class="card-text"><?php echo Text::_('COM_ALBOONLINE_OBLIO_ATTIVO_EXPLANATION'); ?></p>

		<?php if ($canRevokeOblio) : ?>
		<form action="<?php echo Route::_('index.php?option=com_alboonline&task=atto.revokeOblio&id=' . (int) $this->item->id); ?>"
			  method="post" class="row g-3 align-items-end">
			<div class="col-md-9">
				<label class="form-label fw-bold" for="motivo_revoca_oblio">
					<?php echo Text::_('COM_ALBOONLINE_OBLIO_MOTIVO_LABEL'); ?>
				</label>
				<textarea id="motivo_revoca_oblio" name="motivo_revoca_oblio" rows="2" required
						  class="form-control"
						  placeholder="<?php echo Text::_('COM_ALBOONLINE_OBLIO_MOTIVO_PLACEHOLDER'); ?>"></textarea>
				<div class="form-text"><?php echo Text::_('COM_ALBOONLINE_OBLIO_MOTIVO_HELP'); ?></div>
			</div>
			<div class="col-md-3">
				<button type="submit" class="btn btn-danger w-100">
					<span class="icon-unlock me-1" aria-hidden="true"></span>
					<?php echo Text::_('COM_ALBOONLINE_OBLIO_REVOKE_BUTTON'); ?>
				</button>
			</div>
			<?php echo HTMLHelper::_('form.token'); ?>
		</form>
		<?php else : ?>
		<p class="mb-0 text-muted"><?php echo Text::_('COM_ALBOONLINE_OBLIO_NO_PERMISSION'); ?></p>
		<?php endif; ?>
	</div>
</div>
<?php endif; ?>

<?php // S4-T4: ATTIVA OBLIO su un atto già pubblicato/archiviato (istanza di
	  // cancellazione, art. 17 GDPR). Sui blindati il campo del form è disabilitato:
	  // questa è la via tracciata e motivata. Stesso permesso della revoca. ?>
<?php if (!$isNew && $isLocked && (int) ($this->item->diritto_oblio ?? 0) !== 1 && $canRevokeOblio) : ?>
<div class="card border-warning mb-4">
	<div class="card-header bg-warning text-dark">
		<span class="icon-lock me-1" aria-hidden="true"></span>
		<?php echo Text::_('COM_ALBOONLINE_OBLIO_ATTIVA_TITLE'); ?>
	</div>
	<div class="card-body">
		<p class="card-text"><?php echo Text::_('COM_ALBOONLINE_OBLIO_ATTIVA_EXPLANATION'); ?></p>
		<form action="<?php echo Route::_('index.php?option=com_alboonline&task=atto.attivaOblio&id=' . (int) $this->item->id); ?>"
			  method="post" class="row g-3 align-items-end">
			<div class="col-md-9">
				<label class="form-label fw-bold" for="motivo_attiva_oblio">
					<?php echo Text::_('COM_ALBOONLINE_OBLIO_ATTIVA_MOTIVO_LABEL'); ?>
				</label>
				<textarea id="motivo_attiva_oblio" name="motivo_attiva_oblio" rows="2" required
						  class="form-control"
						  placeholder="<?php echo Text::_('COM_ALBOONLINE_OBLIO_ATTIVA_PLACEHOLDER'); ?>"></textarea>
				<div class="form-text"><?php echo Text::_('COM_ALBOONLINE_OBLIO_MOTIVO_HELP'); ?></div>
			</div>
			<div class="col-md-3">
				<button type="submit" class="btn btn-warning w-100">
					<span class="icon-lock me-1" aria-hidden="true"></span>
					<?php echo Text::_('COM_ALBOONLINE_OBLIO_ATTIVA_BUTTON'); ?>
				</button>
			</div>
			<?php echo HTMLHelper::_('form.token'); ?>
		</form>
	</div>
</div>
<?php endif; ?>

<?php // VISIBILITA NEI MODULI: modifiable on locked acts ?>
<div class="card border-secondary mb-4">
	<div class="card-header bg-secondary text-white">
		<span class="icon-eye me-1" aria-hidden="true"></span>
		<?php echo Text::_('COM_ALBOONLINE_FIELD_VISIBILITA_HOME'); ?>
	</div>
	<div class="card-body">
		<form action="<?php echo Route::_('index.php?option=com_alboonline&task=atto.saveVisibilita&id=' . (int) $this->item->id); ?>"
			  method="post" class="row g-3 align-items-end">
			<div class="col-md-4">
				<select name="visibilita_home" class="form-select">
					<option value="nessuna"<?php echo ($this->item->visibilita_home ?? 'nessuna') === 'nessuna' ? ' selected' : ''; ?>><?php echo Text::_('COM_ALBOONLINE_HOME_NESSUNA'); ?></option>
					<option value="comunicazioni"<?php echo ($this->item->visibilita_home ?? '') === 'comunicazioni' ? ' selected' : ''; ?>><?php echo Text::_('COM_ALBOONLINE_HOME_COMUNICAZIONI'); ?></option>
					<option value="evidenza"<?php echo ($this->item->visibilita_home ?? '') === 'evidenza' ? ' selected' : ''; ?>><?php echo Text::_('COM_ALBOONLINE_HOME_EVIDENZA'); ?></option>
				</select>
			</div>
			<div class="col-auto">
				<button type="submit" class="btn btn-secondary">
					<span class="icon-save me-1" aria-hidden="true"></span>
					<?php echo Text::_('JAPPLY'); ?>
				</button>
			</div>
			<?php echo HTMLHelper::_('form.token'); ?>
		</form>
	</div>
</div>
<?php endif; ?>

<form action="<?php echo Route::_('index.php?option=com_alboonline&layout=edit&id=' . (int) $this->item->id); ?>"
	  method="post" name="adminForm" id="adminForm" class="form-validate" enctype="multipart/form-data">

	<?php // S4-T2: avviso su possibili categorie particolari di dati (art. 9 GDPR). Non bloccante. ?>
	<?php if (!empty($this->catPart)) : ?>
	<div class="alert alert-warning" role="alert">
		<h4 class="alert-heading h6"><span class="icon-warning" aria-hidden="true"></span> <?php echo Text::_('COM_ALBOONLINE_CATPART_TITOLO'); ?></h4>
		<p class="mb-2"><?php echo Text::_('COM_ALBOONLINE_CATPART_INTRO'); ?></p>
		<ul class="mb-2">
			<?php foreach ($this->catPart as $categoria => $termini) : ?>
			<li>
				<strong><?php echo Text::_('COM_ALBOONLINE_CATPART_' . strtoupper($categoria)); ?>:</strong>
				<?php echo htmlspecialchars(implode(', ', $termini)); ?>
			</li>
			<?php endforeach; ?>
		</ul>
		<p class="mb-0 small text-muted"><?php echo Text::_('COM_ALBOONLINE_CATPART_NOTA'); ?></p>
	</div>
	<?php endif; ?>

	<?php // S4-T3: checklist di controllo prima di pubblicare. Promemoria, non bloccante. ?>
	<?php if ($this->showChecklist) : ?>
	<details class="mb-3">
		<summary class="fw-bold"><span class="icon-checkbox" aria-hidden="true"></span> <?php echo Text::_('COM_ALBOONLINE_CHECKLIST_TITOLO'); ?></summary>
		<div class="alert alert-info mt-2 mb-0">
			<p class="mb-2 small"><?php echo Text::_('COM_ALBOONLINE_CHECKLIST_INTRO'); ?></p>
			<ul class="list-unstyled mb-1">
				<?php for ($i = 1; $i <= 5; $i++) : ?>
				<li class="mb-1">
					<label class="d-flex gap-2 align-items-start" style="cursor:pointer">
						<input type="checkbox" class="form-check-input mt-1 flex-shrink-0">
						<span><?php echo Text::_('COM_ALBOONLINE_CHECKLIST_' . $i); ?></span>
					</label>
				</li>
				<?php endfor; ?>
			</ul>
			<p class="mb-0 small text-muted"><?php echo Text::_('COM_ALBOONLINE_CHECKLIST_NOTA'); ?></p>
		</div>
	</details>
	<?php endif; ?>

	<?php if (!empty($this->item->rettifica_di)) : ?>
	<div class="alert alert-info" role="alert">
		<span class="icon-link" aria-hidden="true"></span>
		<?php echo Text::sprintf('COM_ALBOONLINE_RETTIFICA_DI_INFO', (int) $this->item->rettifica_di); ?>
	</div>
	<?php endif; ?>

	<?php if (!empty($this->item->rettificato_da)) : ?>
	<div class="alert alert-danger" role="alert">
		<span class="icon-warning" aria-hidden="true"></span>
		<?php echo Text::sprintf('COM_ALBOONLINE_RETTIFICATO_DA_INFO', (int) $this->item->rettificato_da); ?>
	</div>
	<?php endif; ?>

	<?php // 'recall': Carica ed Elimina ricaricano la pagina, e senza questo si ripartirebbe
	      // sempre da Dettagli invece che dalla scheda su cui si stava lavorando. ?>
	<?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'details', 'recall' => true]); ?>

	<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'details', Text::_('COM_ALBOONLINE_TAB_DETAILS')); ?>
	<div class="row">
		<div class="col-lg-9">
			<?php echo $this->form->renderField('titolo'); ?>
			<?php echo $this->form->renderField('alias'); ?>
			<?php echo $this->form->renderField('oggetto'); ?>
			<?php echo $this->form->renderField('descrizione'); ?>
		</div>
		<div class="col-lg-3">
			<?php echo $this->form->renderField('stato_atto'); ?>
			<?php echo $this->form->renderField('numero_protocollo'); ?>
			<?php // Su atto blindato il widget JS di `categoryedit` ignora `disabled` e
				  // apparirebbe modificabile: lo si sostituisce con un testo inerte. M3. ?>
			<?php if ($isLocked) : ?>
			<div class="mb-3">
				<label class="form-label" for="jform_catid_readonly"><?php echo Text::_('JCATEGORY'); ?></label>
				<input type="text" id="jform_catid_readonly" class="form-control" readonly disabled
					   value="<?php echo $this->escape($this->item->category_title ?? ''); ?>" />
			</div>
			<?php else : ?>
			<?php echo $this->form->renderField('catid'); ?>
			<?php endif; ?>
			<?php echo $this->form->renderField('tipo_atto'); ?>
			<?php echo $this->form->renderField('ente_emanante'); ?>
			<?php echo $this->form->renderField('responsabile'); ?>
			<?php echo $this->form->renderField('data_atto'); ?>
			<?php echo $this->form->renderField('segnatura_protocollo'); ?>
			<?php echo $this->form->renderField('segnatura_data'); ?>
			<?php echo $this->form->renderField('codice_correlazione'); ?>
			<?php echo $this->form->renderField('access'); ?>
			<?php echo $this->form->renderField('language'); ?>
		</div>
	</div>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>

	<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'allegati', Text::_('COM_ALBOONLINE_TAB_ALLEGATI')); ?>
	<div class="row">
		<div class="col-lg-12">
			<?php if ($isLocked) : ?>
			<div class="alert alert-info mb-3">
				<span class="icon-lock" aria-hidden="true"></span>
				<?php echo Text::_('COM_ALBOONLINE_ALLEGATI_LOCKED_INFO'); ?>
			</div>
			<?php endif; ?>

			<?php if (!empty($allegati)) : ?>
			<table class="table table-striped allegati-table" id="allegatiTable">
				<thead>
					<tr>
						<?php if (!$isLocked) : ?>
						<th class="w-1 text-center">&nbsp;</th>
						<?php endif; ?>
						<th><?php echo Text::_('COM_ALBOONLINE_ALLEGATO_TITOLO'); ?></th>
						<th><?php echo Text::_('COM_ALBOONLINE_FIELD_ALLEGATO_NOME'); ?></th>
						<th class="w-10"><?php echo Text::_('COM_ALBOONLINE_ALLEGATO_SIZE'); ?></th>
						<?php if ($isLocked) : ?>
						<th class="w-20"><?php echo Text::_('COM_ALBOONLINE_ALLEGATO_HASH'); ?></th>
						<?php endif; ?>
						<?php if (!$isLocked) : ?>
						<th class="w-10 text-center"><?php echo Text::_('COM_ALBOONLINE_ALLEGATO_DELETE'); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($allegati as $i => $allegato) : ?>
					<tr data-allegato-id="<?php echo (int) $allegato->id; ?>">
						<?php if (!$isLocked) : ?>
						<td class="text-center">
							<span class="icon-ellipsis-v sortable-handler" aria-hidden="true"></span>
							<input type="hidden" name="allegati_order[<?php echo (int) $allegato->id; ?>]" value="<?php echo (int) $allegato->ordering; ?>" class="allegato-ordering" />
						</td>
						<?php endif; ?>
						<td>
							<?php if ($isLocked) : ?>
								<?php echo htmlspecialchars($allegato->titolo); ?>
							<?php else : ?>
								<input type="text" name="allegati_titolo[<?php echo (int) $allegato->id; ?>]"
									   value="<?php echo htmlspecialchars($allegato->titolo); ?>"
									   class="form-control form-control-sm"
									   placeholder="<?php echo Text::_('COM_ALBOONLINE_ALLEGATO_TITOLO_PLACEHOLDER'); ?>" />
							<?php endif; ?>
						</td>
						<td>
							<span class="icon-file" aria-hidden="true"></span>
							<?php echo htmlspecialchars($allegato->original_name ?: $allegato->filename); ?>
							<?php // S3-T1: esito verifica firma dell'allegato (layout condiviso, accessibile). ?>
							<?php echo LayoutHelper::render('firma.esito',
								['firma' => $allegato->firma ?? null],
								JPATH_ADMINISTRATOR . '/components/com_alboonline/layouts'); ?>

							<?php // S3-T2: formato del documento (PDF/A o scansione). Avvisi non bloccanti. ?>
							<?php $formato = $allegato->formato ?? null; ?>
							<?php if ($formato && !empty($formato->is_pdf)) : ?>
							<div class="albo-formato small mt-1">
								<?php if ($formato->pdfa) : ?>
									<div class="text-success"><span class="icon-checkmark" aria-hidden="true"></span> <?php echo Text::sprintf('COM_ALBOONLINE_FORMATO_PDFA', htmlspecialchars($formato->pdfa_livello)); ?></div>
								<?php endif; ?>
								<?php foreach ($formato->avvisi as $av) : ?>
									<?php if ($av === 'non_pdfa') : ?>
										<div class="text-muted"><span class="icon-info-circle" aria-hidden="true"></span> <?php echo Text::_('COM_ALBOONLINE_FORMATO_NON_PDFA'); ?></div>
									<?php elseif ($av === 'solo_immagine') : ?>
										<div class="text-warning"><span class="icon-warning-circle" aria-hidden="true"></span> <?php echo Text::_('COM_ALBOONLINE_FORMATO_SOLO_IMMAGINE'); ?></div>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
							<?php endif; ?>
						</td>
						<td>
							<?php echo HTMLHelper::_('number.bytes', $allegato->size); ?>
						</td>
						<?php if ($isLocked) : ?>
						<td>
							<?php if (!empty($allegato->hash)) : ?>
							<code class="small"><?php echo htmlspecialchars(substr($allegato->hash, 0, 16)); ?>&hellip;</code>
							<?php else : ?>
							<span class="text-muted">&mdash;</span>
							<?php endif; ?>
						</td>
						<?php endif; ?>
						<?php if (!$isLocked) : ?>
						<td class="text-center">
							<?php // Un link, non un submit: deve cancellare SOLO questo allegato senza far
							      // partire il form, che caricherebbe anche il file scelto nel selettore
							      // qui sotto. Le due azioni della scheda restano distinte. ?>
							<a class="btn btn-sm btn-outline-danger elimina-allegato"
							   href="<?php echo Route::_('index.php?option=com_alboonline&task=atto.deleteAllegato&id=' . (int) $this->item->id . '&allegato_id=' . (int) $allegato->id . '&' . Session::getFormToken() . '=1'); ?>"
							   data-nome="<?php echo htmlspecialchars($allegato->original_name ?: $allegato->filename); ?>"
							   aria-label="<?php echo Text::sprintf('COM_ALBOONLINE_DELETE_ALLEGATO_ARIA', htmlspecialchars($allegato->original_name)); ?>">
								<span class="icon-trash" aria-hidden="true"></span>
								<?php echo Text::_('COM_ALBOONLINE_ALLEGATO_DELETE'); ?>
							</a>
						</td>
						<?php endif; ?>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php elseif (!$isNew) : ?>
			<div class="alert alert-secondary">
				<?php echo Text::_('COM_ALBOONLINE_NO_ALLEGATI'); ?>
			</div>
			<?php endif; ?>

			<?php if (!$isLocked) : ?>
			<div class="allegati-upload mt-3" id="allegatiUpload">
				<h4 class="h6"><?php echo Text::_('COM_ALBOONLINE_ADD_ALLEGATI'); ?></h4>
				<p class="text-muted small"><?php echo Text::_('COM_ALBOONLINE_ALLEGATI_HELP'); ?></p>
				<div id="newAllegatiContainer">
					<div class="allegato-new-row row mb-2">
						<div class="col-md-5">
							<input type="file" name="allegati_files[]" accept=".pdf,.p7m" class="form-control form-control-sm allegati-file-input" />
						</div>
						<div class="col-md-5">
							<input type="text" name="allegati_new_titolo[]" class="form-control form-control-sm"
								   placeholder="<?php echo Text::_('COM_ALBOONLINE_ALLEGATO_TITOLO_PLACEHOLDER'); ?>" />
						</div>
						<div class="col-md-2">
							<button type="button" class="btn btn-sm btn-outline-danger remove-allegato-row" style="display:none;">
								<span class="icon-times" aria-hidden="true"></span>
							</button>
						</div>
					</div>
				</div>
				<button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addAllegatoRow">
					<span class="icon-plus" aria-hidden="true"></span>
					<?php echo Text::_('COM_ALBOONLINE_ADD_ANOTHER_FILE'); ?>
				</button>
				<?php // Su un atto ancora da salvare non esiste un id a cui agganciare i file:
				      // li carica il Salva della barra, come ha sempre fatto. ?>
				<?php if (!$isNew) : ?>
				<button type="button" class="btn btn-sm btn-primary mt-2 ms-1" id="caricaAllegatiBtn">
					<span class="icon-upload" aria-hidden="true"></span>
					<?php echo Text::_('COM_ALBOONLINE_ALLEGATI_CARICA'); ?>
				</button>
				<p class="text-muted small mt-2 mb-0"><?php echo Text::_('COM_ALBOONLINE_ALLEGATI_CARICA_HINT'); ?></p>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>

	<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'pubblicazione', Text::_('COM_ALBOONLINE_TAB_PUBBLICAZIONE')); ?>
	<div class="row">
		<div class="col-lg-9">
			<?php echo $this->form->renderField('publish_up'); ?>
			<?php if (!$isLocked) : ?>
			<div class="alert alert-info small mb-3">
				<span class="icon-info-circle me-1" aria-hidden="true"></span>
				<?php echo Text::_('COM_ALBOONLINE_PUBLISH_DOWN_HELP'); ?>
			</div>
			<?php endif; ?>
			<?php echo $this->form->renderField('publish_down'); ?>
			<?php echo $this->form->renderField('diritto_oblio'); ?>
			<?php if (!$isLocked) : ?>
			<?php echo $this->form->renderField('visibilita_home'); ?>
			<?php endif; ?>
			<?php echo $this->form->renderField('robots'); ?>
			<?php echo $this->form->renderField('buffer_expires_at'); ?>
			<?php echo $this->form->renderField('locked_at'); ?>
		</div>
	</div>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>

	<?php if (!empty($this->item->rettifica_di) || !empty($this->item->rettificato_da)) : ?>
	<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'rettifica', Text::_('COM_ALBOONLINE_TAB_RETTIFICA')); ?>
	<div class="row">
		<div class="col-lg-9">
			<?php echo $this->form->renderField('note_rettifica'); ?>
		</div>
	</div>
	<?php echo HTMLHelper::_('uitab.endTab'); ?>
	<?php endif; ?>

	<?php echo HTMLHelper::_('uitab.endTabSet'); ?>

	<?php echo $this->form->getInput('created_by'); ?>
	<?php echo $this->form->getInput('modified_by'); ?>

	<input type="hidden" name="task" value="" />
	<?php echo HTMLHelper::_('form.token'); ?>
</form>

