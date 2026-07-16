<?php
/**
 * @package    Com_Alboonline
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 * Registro cronologico delle pubblicazioni (repertorio d'albo).
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper;

$fmt = static fn($d) => (!$d || $d === '0000-00-00 00:00:00') ? '—' : HTMLHelper::_('date', $d, 'd/m/Y');
?>
<div class="albo-registro" lang="it">
	<div class="albo-registro__toolbar no-print">
		<?php // Filtro per anno: la vista calcola gia' $this->anni, ma nessun controllo lo
			  // esponeva — il filtro esisteva solo modificando l'URL a mano. Vedi M5. ?>
		<form method="get" action="index.php" class="albo-registro__filtro">
			<input type="hidden" name="option" value="com_alboonline" />
			<input type="hidden" name="view" value="registro" />
			<input type="hidden" name="tmpl" value="component" />

			<label for="albo-registro-anno"><?php echo Text::_('COM_ALBOONLINE_REGISTRO_FILTRO_ANNO'); ?></label>
			<select id="albo-registro-anno" name="anno">
				<option value="0"><?php echo Text::_('COM_ALBOONLINE_REGISTRO_TUTTI'); ?></option>
				<?php foreach ((array) $this->anni as $anno) : ?>
				<option value="<?php echo (int) $anno; ?>"<?php echo ((int) $anno === (int) $this->anno) ? ' selected' : ''; ?>>
					<?php echo (int) $anno; ?>
				</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="btn btn-secondary">
				<?php echo Text::_('COM_ALBOONLINE_REGISTRO_FILTRA'); ?>
			</button>
		</form>

		<button type="button" class="btn btn-primary" onclick="window.print();">
			<span class="icon-print" aria-hidden="true"></span>
			<?php echo Text::_('COM_ALBOONLINE_REGISTRO_STAMPA'); ?>
		</button>
	</div>

	<article class="albo-registro__doc">
		<header class="albo-registro__head">
			<p class="albo-registro__ente"><?php echo htmlspecialchars($this->ente ?: '—'); ?></p>
			<h1 class="albo-registro__title"><?php echo Text::_('COM_ALBOONLINE_REGISTRO_TITOLO'); ?></h1>
			<p class="albo-registro__sub">
				<?php echo $this->anno > 0
					? Text::sprintf('COM_ALBOONLINE_REGISTRO_ANNO', (int) $this->anno)
					: Text::_('COM_ALBOONLINE_REGISTRO_TUTTI'); ?>
			</p>
		</header>

		<?php if (empty($this->items)) : ?>
			<p><?php echo Text::_('COM_ALBOONLINE_REGISTRO_VUOTO'); ?></p>
		<?php else : ?>
		<table class="albo-registro__tab">
			<caption class="visually-hidden"><?php echo Text::_('COM_ALBOONLINE_REGISTRO_TITOLO'); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php echo Text::_('COM_ALBOONLINE_REFERTO_REPERTORIO'); ?></th>
					<th scope="col"><?php echo Text::_('COM_ALBOONLINE_REFERTO_OGGETTO'); ?></th>
					<th scope="col"><?php echo Text::_('COM_ALBOONLINE_REFERTO_TIPO'); ?></th>
					<th scope="col"><?php echo Text::_('COM_ALBOONLINE_REFERTO_DAL'); ?></th>
					<th scope="col"><?php echo Text::_('COM_ALBOONLINE_REFERTO_AL'); ?></th>
					<th scope="col"><?php echo Text::_('COM_ALBOONLINE_REFERTO_STATO'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->items as $item) : ?>
				<tr>
					<td class="albo-registro__rep"><?php echo htmlspecialchars($item->numero_protocollo); ?></td>
					<td>
						<?php echo htmlspecialchars($item->oggetto ?: $item->titolo); ?>
						<?php // M10/M11: legame di rettifica + motivazione, congelati nel referto e qui esposti nel registro. ?>
						<?php if (!empty($item->rettifica_repertorio)) : ?>
						<div class="albo-registro__rettifica">
							&#8627; <?php echo Text::sprintf('COM_ALBOONLINE_REGISTRO_RETTIFICA_DI', htmlspecialchars($item->rettifica_repertorio)); ?>
							<?php if (trim((string) $item->note_rettifica) !== '') : ?>
								<span class="albo-registro__motivazione">&mdash; <?php echo htmlspecialchars($item->note_rettifica); ?></span>
							<?php endif; ?>
						</div>
						<?php endif; ?>
					</td>
					<td><?php echo htmlspecialchars(AlboonlineHelper::getTipoLabel((string) $item->tipo_atto)); ?></td>
					<td class="text-nowrap"><?php echo $fmt($item->publish_up); ?></td>
					<td class="text-nowrap"><?php echo $fmt($item->publish_down); ?></td>
					<td><?php echo htmlspecialchars(AlboonlineHelper::getStatoLabel((int) $item->stato_atto)); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php // Il totale fa parte del repertorio: deve comparire anche nella stampa. M6. ?>
		<p class="albo-registro__count">
			<?php echo Text::sprintf('COM_ALBOONLINE_REGISTRO_TOTALE', count($this->items)); ?>
		</p>
		<?php endif; ?>
	</article>
</div>

<style>
.albo-registro { max-width: 1000px; margin: 1rem auto; font-family: Georgia, 'Times New Roman', serif; color: #1a1a1a; }
.albo-registro__toolbar { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
.albo-registro__filtro { display: flex; align-items: center; gap: .5rem; }
.albo-registro__filtro label { margin: 0; font-weight: bold; }
.albo-registro__doc { border: 1px solid #333; padding: 2rem; background: #fff; }
.albo-registro__head { text-align: center; border-bottom: 2px solid #333; padding-bottom: 1rem; margin-bottom: 1.5rem; }
.albo-registro__ente { font-weight: bold; text-transform: uppercase; margin: 0 0 .5rem; }
.albo-registro__title { font-size: 1.5rem; margin: .25rem 0; }
.albo-registro__sub { color: #444; margin: 0; }
.albo-registro__tab { width: 100%; border-collapse: collapse; font-size: .9rem; }
.albo-registro__tab th, .albo-registro__tab td { border: 1px solid #bbb; padding: .4rem .55rem; text-align: left; vertical-align: top; }
.albo-registro__tab thead th { background: #f0f0f0; }
.albo-registro__rep { font-weight: bold; white-space: nowrap; }
.albo-registro__rettifica { margin-top: .35rem; font-size: .82rem; font-style: italic; color: #555; }
.albo-registro__motivazione { color: #666; }
.albo-registro__count { margin-top: 1rem; color: #444; }
.visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
/* Stampa: la tabella ha 6 colonne e su A4 verticale deborda. Si stampa in
   orizzontale, con larghezze fisse e testo che va a capo. L'intestazione si
   ripete su ogni pagina e le righe non si spezzano a meta'. Vedi M6. */
@page { size: A4 landscape; margin: 12mm; }

@media print {
	.no-print { display: none !important; }
	.albo-registro { margin: 0; max-width: none; }
	.albo-registro__doc { border: none; padding: 0; }
	.albo-registro__tab { table-layout: fixed; width: 100%; font-size: .78rem; }
	.albo-registro__tab th,
	.albo-registro__tab td { padding: .25rem .35rem; overflow-wrap: anywhere; }
	.albo-registro__tab thead { display: table-header-group; }
	.albo-registro__tab tr { page-break-inside: avoid; }

	/* repertorio · oggetto · tipo · dal · al · stato */
	.albo-registro__tab th:nth-child(1), .albo-registro__tab td:nth-child(1) { width: 12%; }
	.albo-registro__tab th:nth-child(2), .albo-registro__tab td:nth-child(2) { width: 40%; }
	.albo-registro__tab th:nth-child(3), .albo-registro__tab td:nth-child(3) { width: 12%; }
	.albo-registro__tab th:nth-child(4), .albo-registro__tab td:nth-child(4) { width: 11%; }
	.albo-registro__tab th:nth-child(5), .albo-registro__tab td:nth-child(5) { width: 11%; }
	.albo-registro__tab th:nth-child(6), .albo-registro__tab td:nth-child(6) { width: 14%; }
}
</style>
