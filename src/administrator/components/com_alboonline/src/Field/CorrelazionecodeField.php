<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\Field;

defined('JPATH_BASE') or die;

use Joomla\CMS\Form\Field\TextField;
use Joomla\CMS\Factory;

class CorrelazionecodeField extends TextField
{
	protected $type = 'correlazionecode';

	protected function getInput()
	{
		$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

		try
		{
			$query = $db->getQuery(true)
				->select('DISTINCT ' . $db->quoteName('codice_correlazione'))
				->from($db->quoteName('#__alboonline_atti'))
				->where($db->quoteName('codice_correlazione') . ' IS NOT NULL')
				->where($db->quoteName('codice_correlazione') . ' != ' . $db->quote(''))
				->order($db->quoteName('codice_correlazione') . ' ASC');
			$db->setQuery($query);
			$codes = $db->loadColumn() ?: [];
		}
		catch (\Exception $e)
		{
			$codes = [];
		}

		// Get the standard Joomla text input
		$html = parent::getInput();

		if (!empty($codes))
		{
			$jsonCodes = json_encode($codes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
			$inputId   = $this->id;

			$html .= <<<SCRIPT
<div id="{$inputId}_suggestions" style="display:none;position:absolute;z-index:9999;background:#fff;border:1px solid #ced4da;border-top:0;border-radius:0 0 .25rem .25rem;max-height:200px;overflow-y:auto;width:100%;box-shadow:0 4px 6px rgba(0,0,0,.1);"></div>
<script>
(function(){
	var codes = {$jsonCodes};
	var input = document.getElementById('{$inputId}');
	var box   = document.getElementById('{$inputId}_suggestions');
	if (!input || !box || !codes.length) return;

	// Position the suggestions box relative to the input's parent
	input.parentNode.style.position = 'relative';

	function showSuggestions() {
		var val = input.value.toUpperCase();
		if (val.length < 1) { box.style.display = 'none'; return; }
		var matches = codes.filter(function(c){ return c.toUpperCase().indexOf(val) !== -1; });
		if (!matches.length) { box.style.display = 'none'; return; }
		box.innerHTML = '';
		matches.forEach(function(m){
			var div = document.createElement('div');
			div.textContent = m;
			div.style.cssText = 'padding:8px 12px;cursor:pointer;font-size:14px;';
			div.addEventListener('mouseenter', function(){ this.style.backgroundColor='#e9ecef'; });
			div.addEventListener('mouseleave', function(){ this.style.backgroundColor='#fff'; });
			div.addEventListener('mousedown', function(e){
				e.preventDefault();
				input.value = m;
				box.style.display = 'none';
				input.dispatchEvent(new Event('change'));
			});
			box.appendChild(div);
		});
		box.style.display = 'block';
	}

	input.addEventListener('input', showSuggestions);
	input.addEventListener('focus', showSuggestions);
	input.addEventListener('blur', function(){ setTimeout(function(){ box.style.display='none'; }, 150); });
})();
</script>
SCRIPT;
		}

		return $html;
	}
}
