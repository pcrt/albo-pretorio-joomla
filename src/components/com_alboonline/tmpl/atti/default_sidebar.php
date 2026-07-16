<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Pcrt\Component\Alboonline\Site\Helper\RouteHelper;

$activeCatId = (int) $this->state->get('filter.catid', 0);

/**
 * Render category branch recursively.
 */
/**
 * @param  int  $extraSiblings  fratelli resi FUORI da questa funzione allo stesso livello.
 *                              Al primo livello c'è «Tutte le categorie», scritta a mano nel
 *                              template: senza contarla, aria-setsize direbbe «1 di 3» dove
 *                              le voci sono 4, e l'annuncio dello screen reader sarebbe falso.
 */
function renderAlboCategoryBranch(array $categories, int $activeCatId, int $depth = 1, int $extraSiblings = 0): string
{
	$html    = '';
	$setSize = \count($categories) + $extraSiblings;   // fratelli a questo livello → aria-setsize
	$pos     = $extraSiblings;                          // le voci esterne occupano le prime posizioni

	foreach ($categories as $cat)
	{
		$pos++;
		$isActive    = (int) $cat->id === $activeCatId;
		$hasChildren = !empty($cat->children);
		$link        = Route::_(RouteHelper::getAttiRoute((int) $cat->id));
		$groupId     = 'albo-cat-group-' . (int) $cat->id;

		// <li> presentazionale: il ruolo treeitem sta sul link (pattern APG Navigation Treeview).
		$html .= '<li role="none" class="com-alboonline-tree__item' . ($isActive ? ' active' : '') . '">';

		$html .= '<a href="' . $link . '"'
			. ' role="treeitem"'
			. ' aria-level="' . $depth . '"'
			// Con aria-owns la gerarchia DOM non riflette quella logica: senza posinset/setsize
			// lo screen reader non puo' dire «2 di 4».
			. ' aria-posinset="' . $pos . '"'
			. ' aria-setsize="' . $setSize . '"'
			// aria-expanded su chi HA figli: senza, lo screen reader non annuncia che il nodo
			// e' espandibile e la freccia destra non ha senso per chi non vede. L'albero e'
			// sempre aperto (nessun collasso), quindi il valore e' costante: true.
			. ($hasChildren ? ' aria-expanded="true" aria-owns="' . $groupId . '"' : '')
			. ($isActive ? ' aria-current="page"' : '')
			. ' class="com-alboonline-tree__link d-block px-3 py-2"'
			. ' style="padding-left:' . ($depth * 1) . 'rem!important">';
		$html .= htmlspecialchars($cat->title, ENT_QUOTES, 'UTF-8');

		// Il numero si mostra SEMPRE, anche a zero (M29): prima compariva solo se maggiore
		// di zero, e una categoria vuota era indistinguibile da una senza conteggio.
		// L'etichetta nascosta gli da' un significato: senza, lo screen reader annunciava
		// «Determine 5, link» — cinque cosa?
		$html .= ' <span class="badge bg-secondary">'
			. '<span class="visually-hidden">' . Text::_('COM_ALBOONLINE_TREE_ATTI_LABEL') . ' </span>'
			. (int) $cat->num_atti . '</span>';

		$html .= '</a>';

		if ($hasChildren)
		{
			$html .= '<ul role="group" id="' . $groupId . '" class="list-unstyled mb-0">';
			$html .= renderAlboCategoryBranch($cat->children, $activeCatId, $depth + 1);
			$html .= '</ul>';
		}

		$html .= '</li>';
	}

	return $html;
}
?>
<div class="com-alboonline-sidebar card">
	<div class="card-header">
		<h2 class="h6 mb-0"><?php echo Text::_('COM_ALBOONLINE_CATEGORIES'); ?></h2>
	</div>
	<div class="card-body p-0">
		<nav aria-label="<?php echo Text::_('COM_ALBOONLINE_CATEGORIES'); ?>">
			<ul class="com-alboonline-tree list-unstyled mb-0" role="tree"
				aria-label="<?php echo Text::_('COM_ALBOONLINE_CATEGORY_TREE'); ?>" id="alboonline-category-tree">
				<?php // «Tutte le categorie» è il primo treeitem di livello 1: va contata anche
					  // lei in aria-setsize, altrimenti lo screen reader annuncia numeri falsi. ?>
				<li role="none" class="com-alboonline-tree__item<?php echo $activeCatId === 0 ? ' active' : ''; ?>">
					<a href="<?php echo Route::_(RouteHelper::getAttiRoute(0, "*", true)); ?>"
					   role="treeitem" aria-level="1"
					   aria-posinset="1"
					   aria-setsize="<?php echo count($this->categoryTree) + 1; ?>"
					   <?php echo $activeCatId === 0 ? 'aria-current="page"' : ''; ?>
					   class="com-alboonline-tree__link d-block px-3 py-2">
						<?php echo Text::_('COM_ALBOONLINE_ALL_CATEGORIES'); ?>
					</a>
				</li>
				<?php echo renderAlboCategoryBranch($this->categoryTree, $activeCatId, 1, 1); ?>
			</ul>
		</nav>
	</div>
</div>
