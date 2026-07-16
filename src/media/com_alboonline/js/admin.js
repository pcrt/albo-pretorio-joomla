/**
 * Com_Alboonline - Admin scripts
 */
((document) => {
	'use strict';

	const MAX_FILE_SIZE = 20 * 1024 * 1024; // 20MB
	const MAX_FILE_SIZE_LABEL = '20MB';

	function init() {
		// File size validation on input change
		document.addEventListener('change', (e) => {
			const input = e.target.closest('.allegati-file-input');
			if (!input || !input.files.length) return;

			for (const file of input.files) {
				if (file.size > MAX_FILE_SIZE) {
					const sizeMB = (file.size / 1024 / 1024).toFixed(1);
					Joomla.renderMessages({
						error: [
							'Il file "' + file.name + '" pesa ' + sizeMB + 'MB e supera il limite massimo di ' + MAX_FILE_SIZE_LABEL + '. Scegliere un file più piccolo.'
						]
					});
					input.value = '';
					return;
				}
			}
		});

		// Block form submit if any file exceeds the limit
		const adminForm = document.getElementById('adminForm');
		if (adminForm) {
			adminForm.addEventListener('submit', (e) => {
				const fileInputs = adminForm.querySelectorAll('.allegati-file-input');
				const oversized = [];

				fileInputs.forEach((input) => {
					for (const file of input.files) {
						if (file.size > MAX_FILE_SIZE) {
							oversized.push(file.name + ' (' + (file.size / 1024 / 1024).toFixed(1) + 'MB)');
						}
					}
				});

				if (oversized.length) {
					e.preventDefault();
					e.stopImmediatePropagation();
					Joomla.renderMessages({
						error: [
							'Impossibile salvare: ' + (oversized.length === 1 ? 'il file ' : 'i file ') +
							oversized.join(', ') +
							' ' + (oversized.length === 1 ? 'supera' : 'superano') +
							' il limite massimo di ' + MAX_FILE_SIZE_LABEL + '.'
						]
					});
					window.scrollTo({ top: 0, behavior: 'smooth' });
					return false;
				}
			}, true);
		}

		// Multi-file upload: add new row
		const addBtn = document.getElementById('addAllegatoRow');
		const container = document.getElementById('newAllegatiContainer');

		if (addBtn && container) {
			addBtn.addEventListener('click', () => {
				const firstRow = container.querySelector('.allegato-new-row');
				if (!firstRow) return;

				const newRow = firstRow.cloneNode(true);

				// Clear values
				const fileInput = newRow.querySelector('input[type="file"]');
				const textInput = newRow.querySelector('input[type="text"]');

				if (fileInput) {
					// Replace file input to fully reset it (some browsers block .value = '')
					const freshInput = fileInput.cloneNode(false);
					freshInput.value = '';
					fileInput.parentNode.replaceChild(freshInput, fileInput);
				}

				if (textInput) {
					textInput.value = '';
				}

				// Show remove button on the new row
				const removeBtn = newRow.querySelector('.remove-allegato-row');
				if (removeBtn) {
					removeBtn.style.display = '';
				}

				container.appendChild(newRow);
				updateRemoveButtons(container);
			});
		}

		// Remove row handler (delegated)
		if (container) {
			container.addEventListener('click', (e) => {
				const btn = e.target.closest('.remove-allegato-row');
				if (btn) {
					btn.closest('.allegato-new-row').remove();
					updateRemoveButtons(container);
				}
			});
		}

		// Pulsante "Carica": manda il form al task che carica e basta (atto.uploadAllegati),
		// NON al salvataggio. Cosi' non rifa il lavoro del Salva della barra e non tocca gli
		// allegati gia' presenti: e' una funzione in piu', non un doppione.
		const caricaBtn = document.getElementById('caricaAllegatiBtn');

		if (caricaBtn) {
			caricaBtn.addEventListener('click', () => {
				let scelti = 0;

				document.querySelectorAll('.allegati-file-input').forEach((input) => {
					scelti += input.files.length;
				});

				// Senza file non c'e' niente da caricare: meglio dirlo che ricaricare la pagina.
				if (!scelti) {
					Joomla.renderMessages({
						warning: [Joomla.Text._('COM_ALBOONLINE_ALLEGATI_CARICA_NESSUN_FILE')]
					});
					window.scrollTo({ top: 0, behavior: 'smooth' });

					return;
				}

				// submitform e non submitbutton: caricare un file non deve dipendere dal fatto
				// che il resto dell'atto sia gia' compilato per intero.
				Joomla.submitform('atto.uploadAllegati', document.getElementById('adminForm'));
			});
		}

		// "Elimina" e' un link al task atto.deleteAllegato, non un submit: cancella solo quella
		// riga e non fa partire il form (che caricherebbe il file scelto nel selettore). Qui
		// serve solo la conferma, perche' il file sparisce dal disco e non si torna indietro.
		document.addEventListener('click', (e) => {
			const btn = e.target.closest('.elimina-allegato');

			if (!btn) {
				return;
			}

			const nome = btn.getAttribute('data-nome') || '';

			if (!window.confirm(Joomla.Text._('COM_ALBOONLINE_ALLEGATO_DELETE_CONFIRM').replace('%s', nome))) {
				e.preventDefault();
			}
		});
	}

	function updateRemoveButtons(container) {
		const rows = container.querySelectorAll('.allegato-new-row');
		rows.forEach((row) => {
			const btn = row.querySelector('.remove-allegato-row');
			if (btn) {
				btn.style.display = rows.length > 1 ? '' : 'none';
			}
		});
	}

	// Run init: handle both cases - DOM already ready or still loading
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(document);
