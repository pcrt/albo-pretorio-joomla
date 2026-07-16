<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper;
use Pcrt\Component\Alboonline\Administrator\Helper\ProtocolloHelper;

class AttoModel extends AdminModel
{
	protected $text_prefix = 'COM_ALBOONLINE';

	public $typeAlias = 'com_alboonline.atto';

	protected $item = null;

	public function getTable($type = 'Atto', $prefix = 'Administrator', $config = [])
	{
		return parent::getTable($type, $prefix, $config);
	}

	public function getForm($data = [], $loadData = true)
	{
		$form = $this->loadForm(
			'com_alboonline.atto',
			'atto',
			['control' => 'jform', 'load_data' => $loadData]
		);

		if (empty($form))
		{
			return false;
		}

		// Atto blindato: TUTTI i campi di sola lettura, `publish_down` compresa (M34).
		//
		// Prima `publish_down` restava scrivibile «per le proroghe tecniche», ma era una
		// trappola: il salvataggio di un atto blindato è comunque rifiutato in blocco da
		// save() (LOCKED_IMMUTABLE) e sull'atto blindato non c'è nemmeno il pulsante Salva.
		// Si poteva quindi digitare una nuova data credendo di aver prorogato, senza che
		// nulla venisse salvato. L'unica via alla proroga è il riquadro dedicato, che valida
		// (solo estensione, mai accorciamento: accorciare taglierebbe i termini di
		// impugnazione del cittadino).
		$item = $this->getItem();

		if ($item && AlboonlineHelper::isLocked($item))
		{
			$allFields = [
				'titolo', 'alias', 'oggetto', 'descrizione', 'catid',
				'tipo_atto', 'ente_emanante', 'responsabile', 'data_atto',
				'stato_atto', 'numero_protocollo', 'access', 'language',
				'publish_up', 'publish_down', 'buffer_expires_at', 'locked_at',
				'note_rettifica', 'codice_correlazione', 'diritto_oblio', 'visibilita_home', 'robots',
				'segnatura_protocollo', 'segnatura_data',
			];

			foreach ($allFields as $field)
			{
				$form->setFieldAttribute($field, 'readonly', 'true');
				$form->setFieldAttribute($field, 'disabled', 'true');
			}
		}

		return $form;
	}

	protected function loadFormData()
	{
		$data = Factory::getApplication()->getUserState('com_alboonline.edit.atto.data', []);

		if (empty($data))
		{
			if ($this->item === null)
			{
				$this->item = $this->getItem();
			}

			$data = $this->item;

			// Ente emanante obbligatorio (M33): non lo si fa digitare, si presenta gia'
			// compilato con l'ente configurato, storicizzato al momento della creazione.
			// Vale per gli atti NUOVI e per quelli ancora modificabili (bozze) rimasti SENZA
			// ente — dati importati, seed, o atti creati prima di configurare le Opzioni:
			// senza, un campo obbligatorio vuoto impedirebbe QUALSIASI salvataggio (compreso
			// l'aggiunta di un allegato). Sugli atti BLINDATI l'ente e' gia' valorizzato (lo
			// esige la pubblicazione) e il campo e' readonly: non lo si tocca, cosi' non si
			// eredita retroattivamente un ente sui referti gia' emessi.
			if (\is_object($data) && empty($data->ente_emanante) && !AlboonlineHelper::isLocked($data))
			{
				$data->ente_emanante = ComponentHelper::getParams('com_alboonline')
					->get('ente_emanante_default', '');
			}
		}

		return $data;
	}

	public function getItem($pk = null)
	{
		if ($item = parent::getItem($pk))
		{
			if (isset($item->params))
			{
				$item->params = json_encode($item->params);
			}

			// Load allegati
			if (!empty($item->id))
			{
				$item->allegati = $this->getAllegati((int) $item->id);
			}
			else
			{
				$item->allegati = [];
			}

			// Titolo della categoria: su un atto blindato il campo `catid` viene reso come
			// testo di sola lettura, perche' il widget JS di `categoryedit` non recepisce
			// l'attributo `disabled` e apparirebbe modificabile. Vedi M3.
			$item->category_title = '';

			if (!empty($item->catid))
			{
				$db    = $this->getDbo();
				$query = $db->getQuery(true)
					->select($db->quoteName('title'))
					->from($db->quoteName('#__categories'))
					->where($db->quoteName('id') . ' = ' . (int) $item->catid);
				$db->setQuery($query);

				$item->category_title = (string) $db->loadResult();
			}
		}

		return $item;
	}

	/**
	 * Get all allegati for an atto.
	 */
	public function getAllegati(int $attoId): array
	{
		$db = $this->getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__alboonline_allegati'))
			->where($db->quoteName('atto_id') . ' = ' . $attoId)
			->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');
		$db->setQuery($query);

		return $db->loadObjectList() ?: [];
	}

	public function save($data)
	{
		$app = Factory::getApplication();
		$isNew = empty($data['id']);

		// Set default ente_emanante from config if empty on new items
		if ($isNew && empty($data['ente_emanante']))
		{
			$params = ComponentHelper::getParams('com_alboonline');
			$data['ente_emanante'] = $params->get('ente_emanante_default', '');
		}

		// IMMUTABILITY ENFORCEMENT: locked items cannot be saved at all.
		// The only allowed modifications are via dedicated methods:
		// extendPublishDown() and saveVisibilita().
		if (!$isNew)
		{
			$existing = $this->getTable();
			$existing->load($data['id']);

			if (AlboonlineHelper::isLocked($existing))
			{
				$this->setError(Text::_('COM_ALBOONLINE_ERROR_LOCKED_IMMUTABLE'));
				return false;
			}
		}

		$statoPrima = null;

		if (!$isNew)
		{
			$existing = $this->getTable();
			$existing->load($data['id']);
			$statoPrima = (int) $existing->stato_atto;
		}

		$result = parent::save($data);

		if ($result)
		{
			$id = (int) $this->getState($this->getName() . '.id');

			// Save allegati (only for unlocked items)
			$table = $this->getTable();
			$table->load($id);

			if (!AlboonlineHelper::isLocked($table))
			{
				$this->saveAllegati($id);
			}

			AlboonlineHelper::logAction(
				$id,
				$isNew ? 'created' : 'modified',
				$statoPrima,
				(int) ($data['stato_atto'] ?? 0)
			);
		}

		return $result;
	}

	/**
	 * Update visibilita_home for a locked atto.
	 * This is a presentation-only setting, safe to change on locked acts.
	 */
	public function saveVisibilita(int $id, string $value): bool
	{
		// Solo dato di presentazione (in quale modulo di homepage appare l'atto): non tocca
		// protocollo, date o termini. Basta core.edit, ma un permesso ci vuole: senza, chi ha
		// il solo accesso al componente potrebbe modificarlo.
		if (!Factory::getApplication()->getIdentity()->authorise('core.edit', 'com_alboonline'))
		{
			$this->setError(Text::_('JERROR_ALERTNOAUTHOR'));
			return false;
		}

		$allowed = ['nessuna', 'comunicazioni', 'evidenza'];
		if (!in_array($value, $allowed, true))
		{
			$this->setError('Invalid visibilita_home value.');
			return false;
		}

		$table = $this->getTable();
		$table->load($id);

		if (!$table->id)
		{
			$this->setError(Text::_('JGLOBAL_RESOURCE_NOT_FOUND'));
			return false;
		}

		$old = $table->visibilita_home ?? 'nessuna';
		if ($old === $value)
		{
			return true;
		}

		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__alboonline_atti'))
			->set($db->quoteName('visibilita_home') . ' = ' . $db->quote($value))
			->where($db->quoteName('id') . ' = ' . (int) $id);
		$db->setQuery($query);
		$db->execute();

		AlboonlineHelper::logAction($id, 'modified', (int) $table->stato_atto, (int) $table->stato_atto, 'visibilita_home: ' . $old . ' -> ' . $value);

		return true;
	}

	/**
	 * Extend publish_down date for a locked atto.
	 * This is the ONLY modification allowed on a locked act per legal requirements.
	 * The new date must be later than both the current publish_down and NOW.
	 */
	public function extendPublishDown(int $id, string $newDate): bool
	{
		// Prorogare tocca i termini di pubblicita' legale e, su un atto archiviato, lo
		// RIPORTA ONLINE (riattivazione). E' governo del ciclo di pubblicazione: richiede
		// alboonline.publish, come le altre transizioni. Senza, il solo core.manage bastava
		// a ripubblicare un atto la cui pubblicita' legale era conclusa.
		if (!Factory::getApplication()->getIdentity()->authorise('alboonline.publish', 'com_alboonline'))
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_NO_PUBLISH_PERMISSION'));
			return false;
		}

		$table = $this->getTable();
		$table->load($id);

		if (!AlboonlineHelper::isLocked($table))
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_EXTEND_ONLY_LOCKED'));
			return false;
		}

		$wasArchived = ((int) $table->stato_atto === AlboonlineHelper::STATO_ARCHIVIATO);

		// Riattivare un atto archiviato significa riportarlo online. Due sbarramenti.
		// Vedi M16 in docs/gap-analysis/05-collaudo-manuale.md
		if ($wasArchived)
		{
			// 1) Diritto all'oblio: l'atto e' stato rimosso su richiesta dell'interessato.
			// Ripubblicarlo sarebbe un trattamento illecito (art. 17 GDPR). Per farlo
			// occorre prima revocare l'oblio, con motivazione tracciata (revokeOblio()).
			if ((int) $table->diritto_oblio === 1)
			{
				$this->setError(Text::_('COM_ALBOONLINE_ERROR_REACTIVATE_OBLIO'));

				return false;
			}

			// 2) Documenti distrutti: un atto ripubblicato senza il suo documento e'
			// giuridicamente vuoto. Non si torna in pubblicazione a mani vuote.
			if ($this->hasMissingAllegatiFiles($id))
			{
				$this->setError(Text::_('COM_ALBOONLINE_ERROR_REACTIVATE_NO_FILES'));

				return false;
			}
		}

		// Normalise datetime-local format (browser sends YYYY-MM-DDTHH:mm)
		$newDate = str_replace('T', ' ', $newDate);

		// Timezone-aware parsing: l'utente inserisce l'orario nella propria
		// timezone (es. Europe/Rome). Senza specificarlo, Factory::getDate
		// interpreta come UTC e l'orario viene salvato/visualizzato con
		// sfasamento di 1-2 ore rispetto a quanto digitato.
		// Joomla 6: Factory::getUser() è rimosso → getApplication()->getIdentity()
		$userTimezone = Factory::getApplication()->getIdentity()->getTimezone();

		try
		{
			$jDate = Factory::getDate($newDate, $userTimezone);
			$newTimestamp = $jDate->toUnix();
		}
		catch (\Exception $e)
		{
			// Data non interpretabile: e' un errore diverso da "non e' nel futuro". Vedi M8.
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_EXTEND_DATE_INVALID'));

			return false;
		}

		$now = Factory::getDate()->toUnix();

		if ($newTimestamp <= $now)
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_EXTEND_FUTURE_ONLY'));
			return false;
		}

		if (!empty($table->publish_down))
		{
			$currentTimestamp = Factory::getDate($table->publish_down)->toUnix();

			if ($newTimestamp <= $currentTimestamp)
			{
				$this->setError(Text::_('COM_ALBOONLINE_ERROR_EXTEND_MUST_BE_LATER'));
				return false;
			}
		}

		// Format date for DB storage
		$dbDate = $jDate->toSql();

		$db = $this->getDbo();

		$query = $db->getQuery(true)
			->update($db->quoteName('#__alboonline_atti'))
			->set($db->quoteName('publish_down') . ' = ' . $db->quote($dbDate))
			->where($db->quoteName('id') . ' = ' . $id)
			->where($db->quoteName('stato_atto') . ' >= ' . AlboonlineHelper::STATO_PUBBLICATO);

		// Reactivate archived atto: set back to published
		if ($wasArchived)
		{
			// Una riattivazione apre un NUOVO periodo di pubblicazione: non si allunga
			// quello vecchio, gia' concluso con l'archiviazione. Lasciare intatto
			// `publish_up` farebbe attestare al referto una pubblicazione continua che
			// include i giorni in cui l'atto era invisibile: un'attestazione falsa.
			$nuovoPublishUp = Factory::getDate()->toSql();

			$query->set($db->quoteName('stato_atto') . ' = ' . AlboonlineHelper::STATO_PUBBLICATO);
			$query->set($db->quoteName('state') . ' = 1');
			$query->set($db->quoteName('publish_up') . ' = ' . $db->quote($nuovoPublishUp));
		}

		$db->setQuery($query);
		$db->execute();

		if ($wasArchived)
		{
			// Il periodo precedente e' concluso: lo si registra nel log, perche' e'
			// il presupposto del referto (un periodo reale = un'attestazione).
			AlboonlineHelper::logAction(
				$id,
				'reactivated',
				AlboonlineHelper::STATO_ARCHIVIATO,
				AlboonlineHelper::STATO_PUBBLICATO,
				sprintf(
					'Nuovo periodo di pubblicazione: dal %s al %s. Periodo precedente chiuso: dal %s al %s.',
					$nuovoPublishUp,
					$dbDate,
					$table->publish_up ?: '-',
					$table->publish_down ?: '-'
				)
			);
		}
		else
		{
			AlboonlineHelper::logAction(
				$id,
				'extended',
				(int) $table->stato_atto,
				(int) $table->stato_atto,
				'Proroga fine pubblicazione: ' . $dbDate
			);
		}

		return true;
	}

	/**
	 * Lunghezza minima della motivazione richiesta per revocare il diritto all'oblio.
	 */
	protected const REVOKE_OBLIO_MOTIVO_MIN = 15;

	/**
	 * Vero se almeno un allegato dell'atto ha perso il proprio file: `filename` svuotato
	 * (cancellazione da diritto all'oblio) oppure file assente dal disco.
	 * Un atto in queste condizioni non puo' tornare in pubblicazione. Vedi M16.
	 */
	/**
	 * Ripara un atto ORFANO: gli assegna una categoria quando quella attuale non esiste piu'
	 * (M30). E' l'unica modifica di categoria ammessa su un atto blindato, e solo in questo
	 * caso: da oggi il plugin content impedisce di cancellare una categoria che contiene
	 * atti, ma un'installazione aggiornata puo' avere orfani gia' prodotti — e su un atto
	 * blindato il campo Categoria e' di sola lettura, quindi senza questa via sarebbe un
	 * vicolo cieco.
	 *
	 * E' legittimo anche su atto blindato perche' la categoria e' una classificazione: non
	 * incide sui termini di legge, sul protocollo, sulle date ne' sul documento. L'azione e'
	 * comunque tracciata a log.
	 */
	public function riassegnaCategoria(int $id, int $catId): bool
	{
		$user = Factory::getApplication()->getIdentity();

		if (!$user->authorise('core.edit', 'com_alboonline'))
		{
			$this->setError(Text::_('JERROR_ALERTNOAUTHOR'));

			return false;
		}

		$table = $this->getTable();

		if (!$table->load($id))
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_ATTO_NOT_FOUND'));

			return false;
		}

		// Si ripara solo cio' che e' rotto: se la categoria attuale esiste, questa via non
		// deve diventare una scorciatoia per cambiare categoria a un atto blindato.
		if (!$this->isCategoriaOrfana((int) $table->catid))
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_CATEGORIA_NON_ORFANA'));

			return false;
		}

		if ($catId <= 0 || !$this->isCategoriaValida($catId))
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_CATEGORIA_NON_VALIDA'));

			return false;
		}

		$vecchia          = (int) $table->catid;
		$table->catid     = $catId;

		if (!$table->store(true))
		{
			$this->setError($table->getError());

			return false;
		}

		AlboonlineHelper::logAction(
			$id,
			'categoria_riassegnata',
			null,
			null,
			Text::sprintf('COM_ALBOONLINE_LOG_CATEGORIA_RIASSEGNATA', $vecchia, $catId)
		);

		return true;
	}

	/**
	 * Il catid punta a una categoria che non esiste piu'? (0 = "nessuna categoria" non e'
	 * un orfano: e' un atto mai classificato.)
	 */
	public function isCategoriaOrfana(int $catId): bool
	{
		if ($catId <= 0)
		{
			return false;
		}

		$db = $this->getDbo();

		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__categories'))
			->where($db->quoteName('id') . ' = ' . $catId);

		return (int) $db->setQuery($query)->loadResult() === 0;
	}

	/** La categoria esiste, e' pubblicata ed e' dell'albo? */
	protected function isCategoriaValida(int $catId): bool
	{
		$db = $this->getDbo();

		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__categories'))
			->where($db->quoteName('id') . ' = ' . $catId)
			->where($db->quoteName('extension') . ' = ' . $db->quote('com_alboonline'))
			->where($db->quoteName('published') . ' = 1');

		return (int) $db->setQuery($query)->loadResult() === 1;
	}

	/**
	 * Quanti allegati ha l'atto. Serve accanto a hasMissingAllegatiFiles(), che davanti a
	 * ZERO allegati risponde false ("nessun file mancante"): due domande diverse.
	 */
	protected function countAllegati(int $attoId): int
	{
		$db = $this->getDbo();

		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__alboonline_allegati'))
			->where($db->quoteName('atto_id') . ' = ' . $attoId);

		return (int) $db->setQuery($query)->loadResult();
	}

	protected function hasMissingAllegatiFiles(int $attoId): bool
	{
		$db = $this->getDbo();

		$query = $db->getQuery(true)
			->select($db->quoteName('filename'))
			->from($db->quoteName('#__alboonline_allegati'))
			->where($db->quoteName('atto_id') . ' = ' . $attoId);
		$db->setQuery($query);
		$filenames = $db->loadColumn();

		if (empty($filenames))
		{
			return false;
		}

		$basePath = JPATH_ROOT . '/media/com_alboonline/allegati/';

		foreach ($filenames as $filename)
		{
			if ((string) $filename === '' || !file_exists($basePath . $filename))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Revoca il diritto all'oblio su un atto.
	 *
	 * E' l'unica via per riportare in pubblicazione un atto rimosso su richiesta
	 * dell'interessato. Richiede un permesso dedicato e una motivazione, che viene
	 * registrata nell'audit log: un aggiramento tracciato e' preferibile a un divieto
	 * assoluto, che verrebbe eluso modificando il database a mano. Vedi M16.
	 *
	 * Nota: revocare l'oblio non basta a riattivare l'atto se i suoi PDF sono gia'
	 * stati distrutti (`hasMissingAllegatiFiles()`): in quel caso serve un atto nuovo.
	 */
	public function revokeOblio(int $id, string $motivo): bool
	{
		$user = Factory::getApplication()->getIdentity();

		if (!$user->authorise('alboonline.revokeoblio', 'com_alboonline'))
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_REVOKE_OBLIO_NO_PERMISSION'));

			return false;
		}

		$table = $this->getTable();

		if (!$table->load($id) || (int) $table->diritto_oblio !== 1)
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_REVOKE_OBLIO_NOT_SET'));

			return false;
		}

		$motivo = trim($motivo);

		if (mb_strlen($motivo) < self::REVOKE_OBLIO_MOTIVO_MIN)
		{
			$this->setError(Text::sprintf('COM_ALBOONLINE_ERROR_REVOKE_OBLIO_MOTIVO', self::REVOKE_OBLIO_MOTIVO_MIN));

			return false;
		}

		$db = $this->getDbo();

		$query = $db->getQuery(true)
			->update($db->quoteName('#__alboonline_atti'))
			->set($db->quoteName('diritto_oblio') . ' = 0')
			->where($db->quoteName('id') . ' = ' . $id)
			->where($db->quoteName('diritto_oblio') . ' = 1');
		$db->setQuery($query);
		$db->execute();

		if ($db->getAffectedRows() === 0)
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_REVOKE_OBLIO_NOT_SET'));

			return false;
		}

		AlboonlineHelper::logAction(
			$id,
			'oblio_revoked',
			(int) $table->stato_atto,
			(int) $table->stato_atto,
			'Revoca del diritto all\'oblio. Motivazione: ' . $motivo
		);

		return true;
	}

	/**
	 * Attiva il diritto all'oblio su un atto, con motivazione tracciata (S4-T4).
	 *
	 * È la via per dar seguito a un'istanza di cancellazione dell'interessato
	 * (art. 17 GDPR) anche su un atto già pubblicato o archiviato — dove il campo
	 * del form è disabilitato. Simmetrica a revokeOblio(): stesso permesso, stessa
	 * motivazione obbligatoria, azione registrata nell'audit log (accountability
	 * art. 5.2 GDPR).
	 *
	 * Effetto sulla visibilità:
	 *  - atto ARCHIVIATO (termine concluso): l'oblio prevale, rimozione IMMEDIATA
	 *    (state = 0, invisibile);
	 *  - atto ancora in pubblicazione (nei termini): l'oblio NON prevale
	 *    (art. 17.3.b GDPR), si imposta solo il flag e la rimozione scatta alla
	 *    defissione, tramite lo scheduler.
	 */
	public function attivaOblio(int $id, string $motivo): bool
	{
		$user = Factory::getApplication()->getIdentity();

		if (!$user->authorise('alboonline.revokeoblio', 'com_alboonline'))
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_ATTIVA_OBLIO_NO_PERMISSION'));

			return false;
		}

		$table = $this->getTable();

		if (!$table->load($id))
		{
			$this->setError(Text::_('JGLOBAL_ITEM_NOT_FOUND'));

			return false;
		}

		if ((int) $table->diritto_oblio === 1)
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_ATTIVA_OBLIO_ALREADY'));

			return false;
		}

		$motivo = trim($motivo);

		if (mb_strlen($motivo) < self::REVOKE_OBLIO_MOTIVO_MIN)
		{
			$this->setError(Text::sprintf('COM_ALBOONLINE_ERROR_ATTIVA_OBLIO_MOTIVO', self::REVOKE_OBLIO_MOTIVO_MIN));

			return false;
		}

		// Rimozione immediata solo se il periodo di pubblicazione è concluso.
		$immediato = ((int) $table->stato_atto === AlboonlineHelper::STATO_ARCHIVIATO);

		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->update($db->quoteName('#__alboonline_atti'))
			->set($db->quoteName('diritto_oblio') . ' = 1')
			->where($db->quoteName('id') . ' = ' . $id);

		if ($immediato)
		{
			$query->set($db->quoteName('state') . ' = 0');
		}

		$db->setQuery($query);
		$db->execute();

		AlboonlineHelper::logAction(
			$id,
			'oblio_attivato',
			(int) $table->stato_atto,
			(int) $table->stato_atto,
			'Attivazione del diritto all\'oblio'
			. ($immediato ? ' (rimozione immediata)' : ' (applicazione alla defissione)')
			. '. Motivazione: ' . $motivo
		);

		return true;
	}

	/**
	 * Handle multi-allegato save: title/ordering updates, new uploads.
	 *
	 * Le cancellazioni NON stanno piu' qui: le fa il pulsante «Elimina» della riga (task
	 * atto.deleteAllegato). Tenerle anche nel salvataggio significava che premendo
	 * «Carica» si cancellava anche: ogni pulsante deve fare il suo lavoro e basta.
	 */
	protected function saveAllegati(int $attoId): void
	{
		$app = Factory::getApplication();
		$db = $this->getDbo();

		// 1. Process title and ordering updates for existing allegati
		$titoli = $app->input->get('allegati_titolo', [], 'array');
		$orderings = $app->input->get('allegati_order', [], 'array');

		foreach ($titoli as $allegatoId => $titolo)
		{
			$allegatoId = (int) $allegatoId;
			$titolo = trim($titolo);
			$ordering = isset($orderings[$allegatoId]) ? (int) $orderings[$allegatoId] : 0;

			$update = $db->getQuery(true)
				->update($db->quoteName('#__alboonline_allegati'))
				->set($db->quoteName('titolo') . ' = ' . $db->quote($titolo))
				->set($db->quoteName('ordering') . ' = ' . $ordering)
				->where($db->quoteName('id') . ' = ' . $allegatoId)
				->where($db->quoteName('atto_id') . ' = ' . $attoId);
			$db->setQuery($update);
			$db->execute();
		}

		// 2. I file nuovi passano dalla stessa strada del pulsante «Carica»: la logica di
		//    caricamento deve restare una sola.
		$this->uploadAllegati($attoId);
	}

	/**
	 * Carica i file scelti e li aggancia all'atto.
	 *
	 * Estratta da saveAllegati() perche' la usa anche il pulsante «Carica» della scheda
	 * allegati, che deve fare SOLO questo: caricare. Niente salvataggio del resto
	 * dell'atto, niente cancellazioni: le azioni della scheda restano distinte, altrimenti
	 * un pulsante finisce per fare anche il lavoro dell'altro.
	 *
	 * @param   int  $attoId  Atto a cui agganciare i file.
	 *
	 * @return  int  Quanti file sono stati caricati davvero.
	 */
	public function uploadAllegati(int $attoId): int
	{
		$app      = Factory::getApplication();
		$db       = $this->getDbo();
		$user     = $app->getIdentity();
		$now      = Factory::getDate()->toSql();
		$caricati = 0;

		// Joomla's Files input may already restructure array inputs via decodeData()
		// into per-file arrays: [0 => ['name'=>..., 'tmp_name'=>...], 1 => [...]]
		// Handle both that format and raw PHP $_FILES pivot format for robustness.
		$rawFiles = $app->input->files->get('allegati_files', [], 'raw');
		$newTitoli = $app->input->get('allegati_new_titolo', [], 'array');

		$files = [];

		if (!empty($rawFiles))
		{
			$firstKey = array_key_first($rawFiles);

			if (\is_int($firstKey) && isset($rawFiles[$firstKey]['name']))
			{
				// Already restructured by Joomla: [0 => ['name'=>..., 'tmp_name'=>...], ...]
				$files = $rawFiles;
			}
			elseif (isset($rawFiles['name']) && \is_array($rawFiles['name']))
			{
				// Raw PHP $_FILES pivot: ['name' => [0 => 'f1.pdf'], 'tmp_name' => [...], ...]
				foreach ($rawFiles['name'] as $i => $name)
				{
					$files[$i] = [
						'name'     => $rawFiles['name'][$i],
						'type'     => $rawFiles['type'][$i] ?? '',
						'tmp_name' => $rawFiles['tmp_name'][$i] ?? '',
						'error'    => $rawFiles['error'][$i] ?? UPLOAD_ERR_NO_FILE,
						'size'     => $rawFiles['size'][$i] ?? 0,
					];
				}
			}
			elseif (isset($rawFiles['name']) && \is_string($rawFiles['name']))
			{
				// Single file (non-array input)
				$files[0] = $rawFiles;
			}
		}

		if (empty($files))
		{
			return 0;
		}

		// Get current max ordering
		$query = $db->getQuery(true)
			->select('COALESCE(MAX(ordering), 0)')
			->from($db->quoteName('#__alboonline_allegati'))
			->where($db->quoteName('atto_id') . ' = ' . $attoId);
		$db->setQuery($query);
		$maxOrdering = (int) $db->loadResult();

		foreach ($files as $index => $file)
		{
			if (empty($file['name']) || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK)
			{
				continue;
			}

			$uploadResult = $this->handleFileUpload($file);

			// Un file scartato va DETTO. Prima si saltava in silenzio e l'atto risultava
			// "salvato" senza il suo allegato: e' cosi' che il rifiuto dei .p7m e' passato
			// inosservato, perche' a video non compariva nessun errore.
			if ($uploadResult === false)
			{
				$app->enqueueMessage(
					Text::sprintf('COM_ALBOONLINE_ALLEGATO_SCARTATO', $file['name'], $this->getError()),
					'warning'
				);

				continue;
			}

			$maxOrdering++;
			$titolo = !empty($newTitoli[$index]) ? trim($newTitoli[$index]) : '';

			$record = (object) [
				'atto_id'       => $attoId,
				'titolo'        => $titolo,
				'filename'      => $uploadResult['filename'],
				'original_name' => $uploadResult['original_name'],
				'hash'          => null,
				'size'          => $uploadResult['size'],
				'mime'          => $uploadResult['mime'],
				'ordering'      => $maxOrdering,
				'created'       => $now,
				'created_by'    => $user->id,
			];

			$db->insertObject('#__alboonline_allegati', $record);
			$caricati++;
		}

		return $caricati;
	}

	/**
	 * Elimina un singolo allegato, senza passare dal salvataggio dell'atto: la usa il
	 * pulsante «Elimina» della riga, che deve cancellare quello e nient'altro.
	 */
	public function deleteAllegato(int $allegatoId, int $attoId): bool
	{
		$db = $this->getDbo();

		// Gli allegati di un atto blindato non si toccano: e' la promessa dell'albo e va
		// difesa QUI, non solo nascondendo il pulsante — un URL basterebbe ad aggirarla.
		$atto = $this->getItem($attoId);

		if (!$atto || AlboonlineHelper::isLocked($atto))
		{
			$this->setError(Text::_('COM_ALBOONLINE_ALLEGATI_LOCKED_INFO'));

			return false;
		}

		$allegatoTable = new \Pcrt\Component\Alboonline\Administrator\Table\AllegatoTable($db);
		$allegatoTable->load($allegatoId);

		if ((int) $allegatoTable->atto_id !== $attoId)
		{
			return false;
		}

		return $allegatoTable->delete($allegatoId);
	}

	/**
	 * Presidio sulla toggle nativa di Joomla (lista atti e menu "Cambia stato").
	 *
	 * La visibilita' pubblica di un atto e' determinata esclusivamente dal workflow
	 * legale (`stato_atto`): pubblicazione -> buffer -> blindatura -> archiviazione,
	 * con lo scheduler che agisce via SQL diretta. Consentire il flag `state` di
	 * Joomla permetterebbe di rendere pubblico un atto privo di protocollo e di data
	 * certa, oppure di sospendere o archiviare un atto blindato mentre decorrono i
	 * termini di legge. Vedi M1 in docs/gap-analysis/05-collaudo-manuale.md
	 *
	 * NB: si solleva un'eccezione invece di restituire `false`. `AdminController::publish()`
	 * **ignora il valore di ritorno** del modello (controlla `getErrors()` solo quando
	 * $value === 1) e mostrerebbe comunque il messaggio di successo su cestinamento,
	 * sospensione e archiviazione. L'eccezione viene intercettata dal suo `catch`, che
	 * riporta il messaggio d'errore e salta il messaggio di successo. Vedi M20.
	 *
	 * @param   array  $pks    Id degli atti
	 * @param   int    $value  Nuovo valore di `state`
	 *
	 * @return  bool
	 *
	 * @throws  \RuntimeException  Se l'operazione violerebbe il workflow legale
	 */
	public function publish(&$pks, $value = 1)
	{
		$value = (int) $value;

		foreach ((array) $pks as $pk)
		{
			$table = $this->getTable();

			if (!$table->load((int) $pk))
			{
				continue;
			}

			// Atto blindato: e' in pubblicita' legale, nessuna variazione di stato.
			if (AlboonlineHelper::isLocked($table))
			{
				throw new \RuntimeException(Text::_('COM_ALBOONLINE_ERROR_STATE_LOCKED'));
			}

			// Atto non blindato: solo cestinamento (-2) e ripristino (0).
			// Diventare pubblico richiede il workflow legale (atto.publish).
			if (!\in_array($value, [0, -2], true))
			{
				throw new \RuntimeException(Text::_('COM_ALBOONLINE_ERROR_STATE_VIA_WORKFLOW'));
			}
		}

		return parent::publish($pks, $value);
	}

	public function publishAtto(int $id): bool
	{
		$table = $this->getTable();
		$table->load($id);

		if ((int) $table->stato_atto !== AlboonlineHelper::STATO_BOZZA)
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_ONLY_DRAFT_CAN_PUBLISH'));
			return false;
		}

		$user = Factory::getApplication()->getIdentity();

		if (!$user->authorise('alboonline.publish', 'com_alboonline'))
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_NO_PUBLISH_PERMISSION'));
			return false;
		}

		// L'ente emanante compare sui documenti probatori (referto, registro): senza
		// di esso si produrrebbe un'attestazione priva dell'amministrazione che
		// pubblica, cioe' non utilizzabile come prova. Vedi M4/M12.
		if (AlboonlineHelper::getEnteEmanante($table->ente_emanante) === '')
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_ENTE_MANCANTE'));

			return false;
		}

		$params = ComponentHelper::getParams('com_alboonline');

		// La pubblicita' legale e' la pubblicazione DI UN DOCUMENTO: un atto senza allegato
		// fa decorrere i termini su nulla, e alla defissione il referto attesterebbe la
		// pubblicazione di niente. E' la stessa ragione per cui M16 vieta di riattivare un
		// atto i cui PDF sono stati distrutti ("giuridicamente vuoto"): qui la applichiamo
		// anche alla PRIMA pubblicazione, che e' il momento che fa decorrere i termini (M31).
		// Configurabile: un ente puo' avere avvisi il cui contenuto sta tutto nell'oggetto.
		if ((int) $params->get('allegato_obbligatorio', 1) === 1)
		{
			if ($this->countAllegati($id) === 0)
			{
				$this->setError(Text::_('COM_ALBOONLINE_ERROR_ALLEGATO_MANCANTE'));

				return false;
			}

			if ($this->hasMissingAllegatiFiles($id))
			{
				$this->setError(Text::_('COM_ALBOONLINE_ERROR_ALLEGATO_FILE_MANCANTE'));

				return false;
			}
		}

		$bufferMinutes = (int) $params->get('buffer_duration', 60);
		$defaultDays = (int) $params->get('default_publish_days', 15);

		// Pubblicità legale: la durata di pubblicazione può variare per tipologia
		// di atto (es. delibere 15 gg, atti urbanistici 30/45 gg). Se il tipo ha una
		// durata propria la si usa, altrimenti si applica il default globale.
		$giorniTipo  = AlboonlineHelper::getGiorniPubblicazione((string) $table->tipo_atto);
		$publishDays = $giorniTipo ?? $defaultDays;

		// Se l'atto ha gia' un protocollo assegnato (da pubblicazione precedente annullata),
		// riutilizzalo invece di generarne uno nuovo.
		if (!empty($table->numero_protocollo))
		{
			$protocollo = (object) [
				'anno_protocollo'        => (int) $table->anno_protocollo,
				'progressivo_protocollo' => (int) $table->progressivo_protocollo,
				'numero_protocollo'      => $table->numero_protocollo,
			];
		}
		else
		{
			$protocollo = ProtocolloHelper::generate();
		}

		// Tutti i timestamp sono scritti in UTC: e' la convenzione di Joomla (UTC nel
		// database, conversione al fuso dell'utente in visualizzazione) e lo scheduler
		// confronta anch'esso in UTC. Vedi M9 in docs/gap-analysis/05-collaudo-manuale.md
		$now = Factory::getDate();

		$table->numero_protocollo      = $protocollo->numero_protocollo;
		$table->anno_protocollo        = $protocollo->anno_protocollo;
		$table->progressivo_protocollo = $protocollo->progressivo_protocollo;
		$table->stato_atto             = AlboonlineHelper::STATO_IN_ATTESA;
		$table->state                  = 0; // Still unpublished during buffer
		$table->publish_up             = $now->toSql();
		$table->buffer_expires_at      = (clone $now)->modify('+' . (int) $bufferMinutes . ' minutes')->toSql();

		// La durata di legge e' espressa in GIORNI: la somma va fatta nel fuso
		// dell'ente, cosi' l'ora di scadenza resta la stessa anche a cavallo del
		// cambio di ora legale. toSql() memorizza poi il valore in UTC.
		$siteTz         = new \DateTimeZone(Factory::getApplication()->get('offset') ?: 'UTC');
		$scadenzaMinima = (clone $now)->setTimezone($siteTz)->modify('+' . (int) $publishDays . ' days');

		if (empty($table->publish_down))
		{
			$table->publish_down = $scadenzaMinima->toSql();
		}
		elseif (Factory::getDate($table->publish_down)->toUnix() < $scadenzaMinima->toUnix())
		{
			// La durata per tipologia e' un MINIMO di legge, non un valore predefinito
			// sovrascrivibile: si puo' pubblicare piu' a lungo, mai per meno del termine.
			// La proroga applica gia' questa regola (solo estensione); la pubblicazione
			// la ignorava, permettendo di accorciare il termine a mano. Vedi M2.
			$this->setError(Text::sprintf(
				'COM_ALBOONLINE_ERROR_DURATA_MINIMA',
				(int) $publishDays,
				$scadenzaMinima->format('d/m/Y H:i', true)
			));

			return false;
		}

		if (!$table->store())
		{
			$this->setError($table->getError());
			return false;
		}

		// Se questo atto e' una rettifica di un altro, e' adesso (alla pubblicazione)
		// il momento in cui marchiamo l'originale come "rettificato_da".
		// Cosi' se la bozza-rettifica viene cancellata prima della pubblicazione,
		// l'atto originale non resta marchiato a vuoto.
		if (!empty($table->rettifica_di))
		{
			$original = $this->getTable();

			if ($original->load((int) $table->rettifica_di) && empty($original->rettificato_da))
			{
				$original->rettificato_da = $id;
				$original->store();

				AlboonlineHelper::logAction(
					(int) $table->rettifica_di,
					'rectified',
					null,
					null,
					'Rettificato da atto #' . $id
				);
			}
		}

		AlboonlineHelper::logAction(
			$id,
			'published',
			AlboonlineHelper::STATO_BOZZA,
			AlboonlineHelper::STATO_IN_ATTESA,
			'Protocollo: ' . $protocollo->numero_protocollo
		);

		return true;
	}

	public function cancelPublish(int $id): bool
	{
		// Annullare una pubblicazione e' l'operazione inversa di pubblicarla: richiede lo
		// stesso permesso (alboonline.publish). Senza questo controllo, un utente con il solo
		// core.manage sul componente potrebbe disfare la pubblicazione decisa da chi ne ha
		// titolo. Coerente con publishAtto/rectify/revokeOblio, che gia' verificano nel modello.
		if (!Factory::getApplication()->getIdentity()->authorise('alboonline.publish', 'com_alboonline'))
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_NO_PUBLISH_PERMISSION'));
			return false;
		}

		$table = $this->getTable();
		$table->load($id);

		if ((int) $table->stato_atto !== AlboonlineHelper::STATO_IN_ATTESA)
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_ONLY_PENDING_CAN_CANCEL'));
			return false;
		}

		$statoPrima = (int) $table->stato_atto;

		// Protocol number is NOT released (stays assigned)
		$table->stato_atto        = AlboonlineHelper::STATO_BOZZA;
		$table->state             = 0;
		$table->publish_up        = null;
		$table->buffer_expires_at = null;
		// publish_down e' DERIVATA dalla pubblicazione (publish_up + durata di tipologia):
		// annullata la pubblicazione, deve sparire con lei. Lasciarla appesa rendeva l'atto
		// impubblicabile, perche' alla ripubblicazione risultava anteriore al nuovo minimo
		// di legge e il presidio della durata minima la rifiutava (M32).
		$table->publish_down      = null;

		if (!$table->store(true))
		{
			$this->setError($table->getError());
			return false;
		}

		// Se questo atto era una rettifica gia' "marchiata" sull'originale a
		// publishAtto, rimuoviamo il marker: la rettifica non e' piu' attiva.
		if (!empty($table->rettifica_di))
		{
			$original = $this->getTable();

			if ($original->load((int) $table->rettifica_di) && (int) $original->rettificato_da === $id)
			{
				$original->rettificato_da = null;
				$original->store();
			}
		}

		AlboonlineHelper::logAction(
			$id,
			'cancelled',
			$statoPrima,
			AlboonlineHelper::STATO_BOZZA,
			'Pubblicazione annullata. Protocollo ' . $table->numero_protocollo . ' non rilasciato.'
		);

		return true;
	}

	public function rectify(int $originalId): ?int
	{
		$user = Factory::getApplication()->getIdentity();

		if (!$user->authorise('alboonline.rectify', 'com_alboonline'))
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_NO_RECTIFY_PERMISSION'));
			return null;
		}

		$original = $this->getTable();
		$original->load($originalId);

		if ((int) $original->stato_atto < AlboonlineHelper::STATO_PUBBLICATO)
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_ONLY_LOCKED_CAN_RECTIFY'));
			return null;
		}

		// Create new atto as draft, pre-filled from original
		$newData = [
			'id'             => 0,
			'catid'          => $original->catid,
			'titolo'         => Text::sprintf('COM_ALBOONLINE_RETTIFICA_PREFIX', $original->titolo),
			'alias'          => '',
			'oggetto'        => $original->oggetto,
			'descrizione'    => $original->descrizione,
			'tipo_atto'      => $original->tipo_atto,
			'ente_emanante'  => $original->ente_emanante,
			'responsabile'   => $original->responsabile,
			// Data di calendario (non un istante): va presa nel fuso dell'ente,
			// altrimenti a cavallo di mezzanotte risulterebbe il giorno precedente.
			'data_atto'      => Factory::getDate()->format('Y-m-d', true),
			'stato_atto'     => AlboonlineHelper::STATO_BOZZA,
			'state'          => 0,
			'rettifica_di'   => $originalId,
			'access'         => $original->access,
			'language'       => $original->language,
		];

		if (!$this->save($newData))
		{
			return null;
		}

		$newId = (int) $this->getState($this->getName() . '.id');

		// Copy allegati from original to new atto
		$this->copyAllegati($originalId, $newId);

		// NB: l'atto originale NON viene marchiato come "rettificato_da" qui.
		// Il marker viene applicato solo quando la rettifica entra in pubblicazione
		// (publishAtto). Se l'utente cancella la bozza prima di pubblicarla,
		// l'originale rimane intatto agli occhi del frontend.

		AlboonlineHelper::logAction($newId, 'created', null, AlboonlineHelper::STATO_BOZZA, 'Rettifica di atto #' . $originalId);

		return $newId;
	}

	/**
	 * Copy allegati from one atto to another (used in rectification).
	 * Physical files are duplicated. Hash is set to NULL on copies.
	 */
	protected function copyAllegati(int $sourceAttoId, int $destAttoId): void
	{
		$allegati = $this->getAllegati($sourceAttoId);

		if (empty($allegati))
		{
			return;
		}

		$db = $this->getDbo();
		$user = Factory::getApplication()->getIdentity();
		$now = Factory::getDate()->toSql();
		$basePath = JPATH_ROOT . '/media/com_alboonline/allegati/';

		foreach ($allegati as $allegato)
		{
			// Copy physical file
			$sourcePath = $basePath . $allegato->filename;

			if (!file_exists($sourcePath))
			{
				continue;
			}

			$ext = pathinfo($allegato->filename, PATHINFO_EXTENSION);
			$newHashName = hash('sha256', $allegato->filename . microtime(true) . random_bytes(16));
			$newFilename = $newHashName . '.' . $ext;
			$destPath = $basePath . $newFilename;

			if (!File::copy($sourcePath, $destPath))
			{
				continue;
			}

			$record = (object) [
				'atto_id'       => $destAttoId,
				'titolo'        => $allegato->titolo,
				'filename'      => $newFilename,
				'original_name' => $allegato->original_name,
				'hash'          => null,
				'size'          => $allegato->size,
				'mime'          => $allegato->mime,
				'ordering'      => $allegato->ordering,
				'created'       => $now,
				'created_by'    => $user->id,
			];

			$db->insertObject('#__alboonline_allegati', $record);
		}
	}

	protected function handleFileUpload(array $file): array|false
	{
		$maxSize = 20 * 1024 * 1024; // 20MB

		if ($file['error'] !== UPLOAD_ERR_OK)
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_FILE_UPLOAD'));
			return false;
		}

		if ($file['size'] > $maxSize)
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_FILE_TOO_LARGE'));
			return false;
		}

		// L'estensione decide per prima: un formato non previsto va RIFIUTATO, mai
		// riscritto in .pdf, altrimenti un file qualsiasi finirebbe salvato come PDF.
		$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

		if (!in_array($ext, ['pdf', 'p7m'], true))
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_FILE_TYPE_NOT_ALLOWED'));
			return false;
		}

		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$detectedMime = finfo_file($finfo, $file['tmp_name']);
		finfo_close($finfo);

		// PDF (PAdES): controllo stretto sui magic byte. Il .p7m (CAdES) e' una busta
		// DER binaria e finfo la rileva come application/octet-stream: pretendere un
		// mime fisso qui rifiutava OGNI p7m. Ci si fida dell'estensione e si conserva
		// il mime reale; la firma la verifica FirmaVerifier. Stesso criterio di
		// RefertoModel::validateAndStore(), dove il p7m ha sempre funzionato.
		if ($ext === 'pdf')
		{
			$head = (string) file_get_contents($file['tmp_name'], false, null, 0, 5);

			if (substr($head, 0, 5) !== '%PDF-' || $detectedMime !== 'application/pdf')
			{
				$this->setError(Text::_('COM_ALBOONLINE_ERROR_FILE_TYPE_NOT_ALLOWED'));
				return false;
			}
		}

		$hashName = hash('sha256', $file['name'] . microtime(true) . random_bytes(16));
		$storedName = $hashName . '.' . $ext;
		$destPath = JPATH_ROOT . '/media/com_alboonline/allegati/' . $storedName;

		if (!File::upload($file['tmp_name'], $destPath))
		{
			$this->setError(Text::_('COM_ALBOONLINE_ERROR_FILE_MOVE'));
			return false;
		}

		return [
			'filename'      => $storedName,
			'original_name' => $file['name'],
			'size'          => $file['size'],
			'mime'          => $detectedMime ?: 'application/octet-stream',
		];
	}

	protected function prepareTable($table)
	{
		if (empty($table->id))
		{
			if (@$table->ordering === '')
			{
				$db = $this->getDbo();
				$db->setQuery('SELECT MAX(ordering) FROM #__alboonline_atti');
				$max = $db->loadResult();
				$table->ordering = $max + 1;
			}
		}
	}
}
