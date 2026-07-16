<?php
/**
 * @package    Com_Alboonline
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\Rule;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

/**
 * Impone l'unicita' del codice (`valore`) fra i tipi di atto configurati.
 *
 * `AlboonlineHelper::getTipiAttoConfig()` indicizza i tipi per `valore`: due righe
 * con lo stesso codice collassano, e la successiva sovrascrive la precedente in
 * silenzio. Il funzionario vedrebbe "Determina 99 giorni" in elenco mentre il
 * componente ne applica 15 — e la durata determina la data di fine pubblicazione,
 * da cui decorrono i termini di impugnazione. Vedi M14.
 *
 * Il confronto e' insensibile a maiuscole e spazi: due tipi che differiscono solo
 * per la grafia sarebbero indistinguibili per l'operatore.
 */
class TipiattouniciRule extends FormRule
{
	/**
	 * @param   \SimpleXMLElement  $element  Definizione del campo
	 * @param   mixed              $value    Righe del subform
	 * @param   string|null        $group    Gruppo del campo
	 * @param   Registry|null      $input    Dati completi del form
	 * @param   Form|null          $form     Form in validazione
	 *
	 * @return  bool
	 *
	 * @throws  \UnexpectedValueException  Se due tipi condividono lo stesso codice
	 */
	public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
	{
		if (\is_string($value))
		{
			$value = json_decode($value, true);
		}

		if (!\is_array($value))
		{
			return true;
		}

		$visti = [];

		foreach ($value as $riga)
		{
			if (!\is_array($riga))
			{
				continue;
			}

			$valore = mb_strtolower(trim((string) ($riga['valore'] ?? '')));

			if ($valore === '')
			{
				continue;
			}

			if (isset($visti[$valore]))
			{
				throw new \UnexpectedValueException(
					Text::sprintf('COM_ALBOONLINE_ERROR_TIPO_DUPLICATO', $valore)
				);
			}

			$visti[$valore] = true;
		}

		return true;
	}
}
