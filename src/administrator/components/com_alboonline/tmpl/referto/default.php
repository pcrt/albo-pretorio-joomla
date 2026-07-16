<?php
/**
 * @package    Com_Alboonline
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 * Elenco dei referti di pubblicazione EMESSI per un atto (M18). Non genera nulla:
 * i documenti sono gia' congelati e sigillati; qui si scaricano.
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$atto    = $this->atto;
$referti = $this->referti;
$token   = Session::getFormToken();
$fmtDate = static fn($d) => (!$d || $d === '0000-00-00 00:00:00') ? '—' : HTMLHelper::_('date', $d, 'd/m/Y');
$fmtDT   = static fn($d) => (!$d || $d === '0000-00-00 00:00:00') ? '—' : HTMLHelper::_('date', $d, 'd/m/Y H:i');
?>
<div class="albo-referti-lista">
    <div class="card mb-3">
        <div class="card-body">
            <h1 class="h4 mb-1"><?php echo Text::_('COM_ALBOONLINE_REFERTO_LISTA_TITOLO'); ?></h1>
            <p class="mb-1">
                <span class="badge bg-primary"><?php echo $this->escape($atto->numero_protocollo ?: '—'); ?></span>
                <span class="ms-1"><?php echo $this->escape($atto->oggetto ?: $atto->titolo); ?></span>
            </p>
            <p class="text-muted small mb-0"><?php echo Text::_('COM_ALBOONLINE_REFERTO_LISTA_INTRO'); ?></p>
        </div>
    </div>

    <?php if (empty($referti)) : ?>
        <div class="alert alert-info" role="alert">
            <span class="icon-info-circle" aria-hidden="true"></span>
            <?php echo Text::_('COM_ALBOONLINE_REFERTO_NESSUNO'); ?>
        </div>
    <?php else : ?>
        <table class="table table-striped align-middle">
            <caption class="visually-hidden"><?php echo Text::_('COM_ALBOONLINE_REFERTO_LISTA_TITOLO'); ?></caption>
            <thead>
                <tr>
                    <th scope="col"><?php echo Text::_('COM_ALBOONLINE_REFERTO_COL_DOCUMENTO'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_ALBOONLINE_REFERTO_COL_PERIODO'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_ALBOONLINE_REFERTO_COL_EMESSO'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_ALBOONLINE_REFERTO_COL_STATO'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_ALBOONLINE_REFERTO_COL_IMPRONTA'); ?></th>
                    <th scope="col" class="text-end"><?php echo Text::_('COM_ALBOONLINE_REFERTO_COL_AZIONI'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($referti as $r) : ?>
                    <?php
                    $tipoLabel = $r->tipo_documento === 'avvio'
                        ? Text::_('COM_ALBOONLINE_REFERTO_TIPO_AVVIO')
                        : Text::_('COM_ALBOONLINE_REFERTO_TIPO_DEFISSIONE');
                    $isFirmato = $r->stato === 'firmato';
                    $dlBase    = 'index.php?option=com_alboonline&task=referto.download&id=' . (int) $r->id . '&' . $token . '=1';
                    ?>
                    <tr>
                        <th scope="row" class="fw-normal"><?php echo $this->escape($tipoLabel); ?></th>
                        <td><?php echo $fmtDate($r->publish_up) . ' &ndash; ' . $fmtDate($r->publish_down); ?></td>
                        <td><?php echo $fmtDT($r->emesso_at); ?></td>
                        <td>
                            <?php if ($isFirmato) : ?>
                                <span class="badge bg-success"><?php echo Text::_('COM_ALBOONLINE_REFERTO_STATO_FIRMATO'); ?></span>
                            <?php else : ?>
                                <span class="badge bg-info"><?php echo Text::_('COM_ALBOONLINE_REFERTO_STATO_EMESSO'); ?></span>
                            <?php endif; ?>

                            <?php // S3-T1: esito verifica firma (layout condiviso, accessibile). ?>
                            <?php if ($isFirmato && $r->firma) : ?>
                                <?php echo LayoutHelper::render('firma.esito',
                                    ['firma' => $r->firma, 'corrisponde' => $r->firma_corrisponde],
                                    JPATH_ADMINISTRATOR . '/components/com_alboonline/layouts'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code class="small" title="SHA-256: <?php echo $this->escape($r->pdf_hash); ?>">
                                <?php echo $this->escape(substr((string) $r->pdf_hash, 0, 12)); ?>&hellip;
                            </code>
                        </td>
                        <td class="text-end">
                            <?php if ($this->canDownload) : ?>
                                <a class="btn btn-sm btn-primary" href="<?php echo Route::_($dlBase); ?>">
                                    <span class="icon-download" aria-hidden="true"></span>
                                    <?php echo Text::_('COM_ALBOONLINE_REFERTO_SCARICA_EMESSO'); ?>
                                </a>
                                <?php if ($isFirmato) : ?>
                                    <a class="btn btn-sm btn-success" href="<?php echo Route::_($dlBase . '&variante=firmato'); ?>">
                                        <span class="icon-download" aria-hidden="true"></span>
                                        <?php echo Text::_('COM_ALBOONLINE_REFERTO_SCARICA_FIRMATO'); ?>
                                    </a>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="text-muted small"><?php echo Text::_('COM_ALBOONLINE_REFERTO_NO_PERMESSO'); ?></span>
                            <?php endif; ?>

                            <?php if ($this->canUpload) : ?>
                                <details class="mt-2 text-start">
                                    <summary class="small"><?php echo Text::_('COM_ALBOONLINE_REFERTO_FIRMATO_CARICA'); ?></summary>
                                    <form action="index.php" method="post" enctype="multipart/form-data" class="mt-2">
                                        <label class="form-label small" for="signed_file_<?php echo (int) $r->id; ?>">
                                            <?php echo Text::_('COM_ALBOONLINE_REFERTO_FIRMATO_FILE_LABEL'); ?>
                                        </label>
                                        <input type="file" id="signed_file_<?php echo (int) $r->id; ?>"
                                               name="signed_file" accept=".pdf,.p7m" required
                                               class="form-control form-control-sm mb-2" />
                                        <p class="text-muted small mb-2"><?php echo Text::_('COM_ALBOONLINE_REFERTO_FIRMATO_HINT'); ?></p>
                                        <input type="hidden" name="option" value="com_alboonline">
                                        <input type="hidden" name="task" value="referto.uploadSigned">
                                        <input type="hidden" name="id" value="<?php echo (int) $r->id; ?>">
                                        <input type="hidden" name="atto_id" value="<?php echo (int) $atto->id; ?>">
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <span class="icon-upload" aria-hidden="true"></span>
                                            <?php echo Text::_('COM_ALBOONLINE_REFERTO_FIRMATO_INVIA'); ?>
                                        </button>
                                    </form>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
