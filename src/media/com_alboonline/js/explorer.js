/**
 * Com_Alboonline - Explorer (frontend) scripts
 * Navigazione da tastiera dell'albero categorie (WCAG 2.1 AA, pattern WAI-ARIA Tree).
 *
 * Progressive enhancement: l'HTML e' una navigazione di link nativamente
 * focalizzabili con Tab. Questo script vi applica il "roving tabindex" e la
 * navigazione con le frecce solo dopo il caricamento; se lo script non parte,
 * i link restano tutti raggiungibili con Tab.
 */
((document) => {
	'use strict';

	document.addEventListener('DOMContentLoaded', () => {
		const tree = document.getElementById('alboonline-category-tree');

		if (!tree) {
			return;
		}

		const items = Array.from(tree.querySelectorAll('.com-alboonline-tree__link'));

		if (!items.length) {
			return;
		}

		// Roving tabindex: solo l'elemento attivo (o il primo) e' nel flusso di Tab.
		let currentIndex = items.findIndex((el) => el.getAttribute('aria-current') === 'page');

		if (currentIndex < 0) {
			currentIndex = 0;
		}

		items.forEach((el, i) => el.setAttribute('tabindex', i === currentIndex ? '0' : '-1'));

		// Scegliere una categoria ricarica la pagina, e il focus torna in cima al documento:
		// chi naviga da tastiera deve rifare TUTTO il percorso di Tab per tornare all'albero.
		// Punisce esattamente chi la tastiera e' costretto a usarla (WCAG 2.4.3, ordine del
		// focus). Rimettiamo quindi il focus sulla voce scelta, ma SOLO se la navigazione e'
		// partita dall'albero: altrimenti sposteremmo il focus senza che nessuno l'abbia
		// chiesto (WCAG 3.2.x), il che sarebbe un difetto diverso ma altrettanto vero.
		const SEGNALE = 'albo-tree-navigato';

		tree.addEventListener('click', () => {
			try {
				sessionStorage.setItem(SEGNALE, '1');
			} catch (e) {
				// sessionStorage negato (navigazione privata rigida): pazienza, si degrada.
			}
		});

		try {
			if (sessionStorage.getItem(SEGNALE)) {
				sessionStorage.removeItem(SEGNALE);
				items[currentIndex].focus();
			}
		} catch (e) {
			// niente sessionStorage: nessun ripristino, ma nulla si rompe.
		}

		function focusItem(index) {
			if (index < 0 || index >= items.length) {
				return;
			}

			items[currentIndex].setAttribute('tabindex', '-1');
			currentIndex = index;
			items[currentIndex].setAttribute('tabindex', '0');
			items[currentIndex].focus();
		}

		// Freccia Destra: entra nel primo figlio (gruppo associato via aria-owns).
		function firstChildIndex(link) {
			const groupId = link.getAttribute('aria-owns');

			if (!groupId) {
				return -1;
			}

			const group = document.getElementById(groupId);
			const child = group ? group.querySelector('.com-alboonline-tree__link') : null;

			return child ? items.indexOf(child) : -1;
		}

		// Freccia Sinistra: risale al treeitem genitore.
		function parentIndex(link) {
			const li = link.closest('li');
			const group = li ? li.parentElement : null;

			if (!group || group.getAttribute('role') !== 'group') {
				return -1;
			}

			const parentLi = group.closest('li');
			const parentLink = parentLi ? parentLi.querySelector(':scope > .com-alboonline-tree__link') : null;

			return parentLink ? items.indexOf(parentLink) : -1;
		}

		// Il focus puo' arrivare su una voce SENZA passare dalle frecce (clic, focus
		// programmatico, ritorno dalla cronologia). Se non risincronizziamo l'indice, la
		// freccia successiva riparte dalla voce sbagliata e il focus "salta".
		tree.addEventListener('focusin', (e) => {
			const index = items.indexOf(e.target);

			if (index >= 0 && index !== currentIndex) {
				items[currentIndex].setAttribute('tabindex', '-1');
				currentIndex = index;
				items[currentIndex].setAttribute('tabindex', '0');
			}
		});

		tree.addEventListener('keydown', (e) => {
			const link = items[currentIndex];
			let target;

			switch (e.key) {
				case 'ArrowDown':
					e.preventDefault();
					focusItem(currentIndex + 1);
					break;
				case 'ArrowUp':
					e.preventDefault();
					focusItem(currentIndex - 1);
					break;
				case 'ArrowRight':
					e.preventDefault();
					target = firstChildIndex(link);
					if (target >= 0) {
						focusItem(target);
					}
					break;
				case 'ArrowLeft':
					e.preventDefault();
					target = parentIndex(link);
					if (target >= 0) {
						focusItem(target);
					}
					break;
				case 'Home':
					e.preventDefault();
					focusItem(0);
					break;
				case 'End':
					e.preventDefault();
					focusItem(items.length - 1);
					break;
				case 'Enter':
				case ' ':
					e.preventDefault();
					link.click();
					break;
			}
		});
	});
})(document);
