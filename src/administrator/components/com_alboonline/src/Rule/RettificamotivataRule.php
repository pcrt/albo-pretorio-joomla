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
 * Impone la motivazione quando un atto è una rettifica (M11).
 *
 * Se `rettifica_di` è valorizzato, `note_rettifica` deve essere non vuota: la
 * rettifica di un atto già pubblicato incide su una situazione consolidata e va
 * motivata (obbligo di motivazione, art. 3 L. 241/1990). La motivazione viene poi
 * congelata nel referto e mostrata nel registro. Per gli atti ordinari (senza
 * rettifica_di) il campo resta facoltativo.
 */
class RettificamotivataRule extends FormRule
{
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        $rettificaDi = $input ? (int) $input->get('rettifica_di', 0) : 0;

        // Nessuna rettifica in corso: il campo è facoltativo.
        if ($rettificaDi <= 0) {
            return true;
        }

        if (trim((string) $value) === '') {
            throw new \UnexpectedValueException(
                Text::_('COM_ALBOONLINE_ERROR_NOTE_RETTIFICA_OBBLIGATORIA')
            );
        }

        return true;
    }
}
