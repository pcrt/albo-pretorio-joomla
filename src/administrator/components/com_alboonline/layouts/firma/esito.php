<?php
/**
 * @package    Com_Alboonline
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 * Layout condiviso: esito della verifica INTERNA della firma (S3-T1).
 * Usato sia nell'elenco referti sia nella scheda allegati dell'atto, così la resa
 * è identica e si manutiene in un solo posto.
 *
 * $displayData:
 *   - firma:        oggetto report di FirmaVerifier (obbligatorio)
 *   - corrisponde:  bool|null — solo per i referti (contenuto == referto emesso)
 *
 * Accessibilità: lo stato NON è affidato al solo colore — c'è sempre l'etichetta
 * testuale ("Verifica firma: ...") e l'esito per esteso; le icone sono decorative
 * (aria-hidden). Il blocco è un role="status" per i lettori di schermo.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$firma       = $displayData['firma'] ?? null;
$corrisponde = $displayData['corrisponde'] ?? null;

// Niente firma → niente da mostrare.
if (!$firma || empty($firma->ha_firma)) {
    return;
}

$esito = $firma->esito ?? '';

// esito => [icona, classe colore Bootstrap, chiave testo]
$map = [
    'integra'        => ['icon-check',        'success',   'COM_ALBOONLINE_FIRMA_INTEGRA'],
    'compromessa'    => ['icon-cancel-circle', 'danger',    'COM_ALBOONLINE_FIRMA_COMPROMESSA'],
    'pades_rilevata' => ['icon-info-circle',  'secondary', 'COM_ALBOONLINE_FIRMA_PADES_RILEVATA'],
];

// Fallback neutro: un esito non mappato non deve ereditare l'etichetta di un altro
// formato (dire «PAdES» su un .p7m sarebbe una dicitura falsa).
$m = $map[$esito] ?? ['icon-info-circle', 'secondary', 'COM_ALBOONLINE_FIRMA_RILEVATA'];

$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="albo-firma-esito border-start border-3 border-<?php echo $m[1]; ?> ps-2 py-1 mt-1 small" role="status">
	<div class="fw-bold text-<?php echo $m[1]; ?>">
		<span class="<?php echo $m[0]; ?>" aria-hidden="true"></span>
		<?php echo Text::_('COM_ALBOONLINE_FIRMA_ETICHETTA'); ?>: <?php echo Text::_($m[2]); ?>
	</div>

	<?php foreach ($firma->firmatari as $f) : ?>
		<div>
			<span class="visually-hidden"><?php echo Text::_('COM_ALBOONLINE_FIRMA_FIRMATARIO'); ?>: </span>
			<span class="icon-user" aria-hidden="true"></span>
			<?php echo $e($f->cn ?: '—'); ?><?php if (!empty($f->codice_fiscale)) : ?> &middot; <?php echo $e($f->codice_fiscale); ?><?php endif; ?>
			<?php if (isset($f->nella_finestra) && $f->nella_finestra === false) : ?>
				<span class="text-warning">(<?php echo Text::_('COM_ALBOONLINE_FIRMA_CERT_SCADUTO'); ?>)</span>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>

	<?php if (!empty($firma->marca_temporale->presente)) : ?>
		<div>
			<span class="icon-clock" aria-hidden="true"></span>
			<?php echo Text::_('COM_ALBOONLINE_FIRMA_MARCA_TEMPORALE'); ?>
		</div>
	<?php endif; ?>

	<?php if ($corrisponde === true) : ?>
		<div class="text-success"><span class="icon-check" aria-hidden="true"></span> <?php echo Text::_('COM_ALBOONLINE_FIRMA_CORRISPONDE'); ?></div>
	<?php elseif ($corrisponde === false) : ?>
		<div class="text-warning"><span class="icon-warning-circle" aria-hidden="true"></span> <?php echo Text::_('COM_ALBOONLINE_FIRMA_NON_CORRISPONDE'); ?></div>
	<?php endif; ?>

	<div class="text-muted fst-italic"><?php echo Text::_('COM_ALBOONLINE_FIRMA_AVANZATA_ND'); ?></div>
</div>
