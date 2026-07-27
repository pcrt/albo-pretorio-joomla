<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * Assembla i METADATI minimi del documento informatico secondo l'Allegato 5 delle
 * Linee Guida AgID (S3-T3), mappandoli sui campi già presenti nell'atto.
 *
 * Fonte unica: usata dalla pagina dell'atto (esposizione) e riutilizzabile per un
 * futuro export (JSON/XML). Ritorna solo le voci effettivamente valorizzate, con
 * etichetta (chiave di lingua) e valore già pronto per la resa.
 *
 * Copertura vs All. 5 documentata in docs/compliance/04-metadati-agid.md
 * (fascicolazione/classificazione = fuori scope, coerente con la decisione di
 * progetto sul protocollo informatico).
 */
class MetadatiAgid
{
    /**
     * @param   object  $atto  Oggetto atto (con i campi standard)
     *
     * @return  array<int,object>  Elenco {label, value} dei metadati valorizzati.
     */
    public static function build(object $atto): array
    {
        $voci = [];

        $add = static function (string $labelKey, ?string $value) use (&$voci): void {
            $value = trim((string) $value);

            if ($value !== '') {
                $voci[] = (object) ['label' => $labelKey, 'value' => $value];
            }
        };

        $fmtDate = static fn($d) => (!$d || $d === '0000-00-00' || $d === '0000-00-00 00:00:00')
            ? '' : HTMLHelper::_('date', $d, Text::_('DATE_FORMAT_LC4'));

        // Identificativo (repertorio d'albo, univoco e persistente per l'ente).
        $add('COM_ALBOONLINE_META_IDENTIFICATIVO', $atto->numero_protocollo ?? '');

        // Segnatura di protocollo dell'ente (protocollo informatico) — se presente.
        if (!empty($atto->segnatura_protocollo)) {
            $seg = (string) $atto->segnatura_protocollo;

            if (!empty($atto->segnatura_data) && ($d = $fmtDate($atto->segnatura_data)) !== '') {
                $seg .= ' · ' . $d;
            }

            $add('COM_ALBOONLINE_META_SEGNATURA', $seg);
        }

        // Tipologia documentale.
        $add('COM_ALBOONLINE_META_TIPOLOGIA', AlboonlineHelper::getTipoLabel((string) ($atto->tipo_atto ?? '')));

        // Oggetto.
        $add('COM_ALBOONLINE_META_OGGETTO', (string) ($atto->oggetto ?? $atto->titolo ?? ''));

        // Data dell'atto.
        $add('COM_ALBOONLINE_META_DATA', $fmtDate($atto->data_atto ?? null));

        // Soggetto emittente (ente) e responsabile (autore/assegnatario).
        $add('COM_ALBOONLINE_META_ENTE', AlboonlineHelper::getEnteEmanante($atto->ente_emanante ?? null));
        $add('COM_ALBOONLINE_META_RESPONSABILE', (string) ($atto->responsabile ?? ''));

        // Periodo di pubblicazione (data certa iniziale e finale).
        $up = $fmtDate($atto->publish_up ?? null);
        $dn = $fmtDate($atto->publish_down ?? null);

        if ($up !== '') {
            $add('COM_ALBOONLINE_META_PERIODO', $dn !== '' ? ($up . ' – ' . $dn) : $up);
        }

        // Regime / riservatezza: l'albo pretorio è pubblicità legale temporanea.
        $add('COM_ALBOONLINE_META_REGIME', Text::_('COM_ALBOONLINE_META_REGIME_VALORE'));

        return $voci;
    }
}
