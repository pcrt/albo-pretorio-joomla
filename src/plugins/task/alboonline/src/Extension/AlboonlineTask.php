<?php
/**
 * @package    Plg_Task_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Plugin\Task\Alboonline\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Pcrt\Component\Alboonline\Administrator\Helper\RefertoGenerator;

class AlboonlineTask extends CMSPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;
	use TaskPluginTrait {
		logTask as traitLogTask;
	}

	/**
	 * Mappa delle routine task.
	 * Due routine: lock (scadenza buffer) e archive (scadenza publish_down).
	 */
	protected const TASKS_MAP = [
		'plg_task_alboonline_lock' => [
			'langConstPrefix' => 'PLG_TASK_ALBOONLINE_LOCK',
			'form'            => 'lock_params',
			'method'          => 'lockExpiredBuffer',
		],
		'plg_task_alboonline_archive' => [
			'langConstPrefix' => 'PLG_TASK_ALBOONLINE_ARCHIVE',
			'form'            => 'archive_params',
			'method'          => 'archiveExpired',
		],
	];

	protected $autoloadLanguage = true;

	/**
	 * Override logTask to accumulate output in snapshot for Task Scheduler execution history.
	 */
	protected function logTask(string $message, string $priority = 'info'): void
	{
		if (!empty($this->snapshot))
		{
			$this->snapshot['output'] = ($this->snapshot['output'] ?? '') . $message . "\n";
		}

		try
		{
			$this->traitLogTask($message, $priority);
		}
		catch (\Throwable $e)
		{
			Log::add('plg_task_alboonline: ' . $message, Log::INFO, 'task.alboonline');
		}
	}

	public static function getSubscribedEvents(): array
	{
		return [
			'onTaskOptionsList'    => 'advertiseRoutines',
			'onExecuteTask'        => 'standardRoutineHandler',
			'onContentPrepareForm' => 'enhanceTaskItemForm',
		];
	}

	/**
	 * Blocca atti il cui periodo buffer e' scaduto.
	 * Transizione: In Attesa (1) -> Pubblicato/Bloccato (2)
	 *
	 * Tutti i confronti e le scritture usano l'istante corrente in UTC
	 * (`Factory::getDate()->toSql()`), coerente con le date memorizzate nel
	 * database. NON si usa `NOW()` di MySQL, che restituisce l'ora locale del
	 * server e reintrodurrebbe una seconda base di tempo. Vedi M9.
	 *
	 * Strategia:
	 * 1. Priorita': buffer_expires_at impostato e scaduto
	 * 2. Fallback: publish_up + 15 minuti gia' trascorsi (orario preciso)
	 * 3. Ultimo resort: data_atto + 15 minuti gia' trascorsi (solo data, senza orario)
	 *
	 * Azioni:
	 * - stato_atto = 2, state = 1 (Joomla published)
	 * - locked_at = istante corrente (UTC)
	 * - Calcola hash SHA-256 per ogni allegato
	 * - Registra transizione nel log di audit
	 */
	protected function lockExpiredBuffer(ExecuteTaskEvent $event): int
	{
		try
		{
			$db  = $this->getDatabase();

			// Tutti i timestamp del componente sono in UTC (vedi M9): confrontiamo in UTC.
			$now = Factory::getDate()->toSql();

			$this->logTask('[LOCK] Task avviato – timestamp UTC: ' . $now);

			// Conta atti in attesa
			$countQuery = $db->getQuery(true)
				->select('COUNT(*)')
				->from($db->quoteName('#__alboonline_atti'))
				->where($db->quoteName('stato_atto') . ' = 1');
			$db->setQuery($countQuery);
			$totalPending = (int) $db->loadResult();

			$this->logTask('[LOCK] Atti in stato "In Attesa" (stato_atto=1): ' . $totalPending);

			if ($totalPending === 0)
			{
				$this->logTask('[LOCK] Nessun atto in attesa. Task completato.');

				return TaskStatus::OK;
			}

			// Log diagnostico: mostra date per ogni atto in attesa
			$diagQuery = $db->getQuery(true)
				->select(['id', 'titolo', 'buffer_expires_at', 'publish_up', 'data_atto'])
				->from($db->quoteName('#__alboonline_atti'))
				->where($db->quoteName('stato_atto') . ' = 1');
			$db->setQuery($diagQuery);
			$pendingActs = $db->loadObjectList();

			foreach ($pendingActs as $pa)
			{
				$this->logTask(sprintf(
					'[LOCK DIAG] Atto #%d "%s" – buffer_expires_at: %s | publish_up: %s | data_atto: %s',
					$pa->id,
					$pa->titolo,
					$pa->buffer_expires_at ?: 'NULL',
					$pa->publish_up ?: 'NULL',
					$pa->data_atto ?: 'NULL'
				));
			}

			// Seleziona atti da bloccare:
			// Tier 1: buffer_expires_at esplicito e scaduto
			// Tier 2: publish_up + 15 minuti (DATETIME, preciso al minuto)
			// Tier 3: data_atto + 15 minuti (DATE, ultimo resort)
			$quotedNow = $db->quote($now);

			$query = $db->getQuery(true)
				->select(['id', 'titolo', 'buffer_expires_at', 'publish_up', 'data_atto'])
				->from($db->quoteName('#__alboonline_atti'))
				->where($db->quoteName('stato_atto') . ' = 1')
				->where(
					'('
					// Tier 1: buffer_expires_at esplicito e scaduto
					. '(' . $db->quoteName('buffer_expires_at') . ' IS NOT NULL'
						. ' AND ' . $db->quoteName('buffer_expires_at') . ' <= ' . $quotedNow . ')'
					. ' OR '
					// Tier 2: nessun buffer, ma publish_up + 15 min scaduto (preciso al minuto)
					. '(' . $db->quoteName('buffer_expires_at') . ' IS NULL'
						. ' AND ' . $db->quoteName('publish_up') . ' IS NOT NULL'
						. ' AND DATE_ADD(' . $db->quoteName('publish_up') . ', INTERVAL 15 MINUTE) <= ' . $quotedNow . ')'
					. ' OR '
					// Tier 3: nessun buffer ne publish_up, ma data_atto + 15 min scaduto
					. '(' . $db->quoteName('buffer_expires_at') . ' IS NULL'
						. ' AND ' . $db->quoteName('publish_up') . ' IS NULL'
						. ' AND ' . $db->quoteName('data_atto') . ' IS NOT NULL'
						. ' AND DATE_ADD(' . $db->quoteName('data_atto') . ', INTERVAL 15 MINUTE) <= ' . $quotedNow . ')'
					. ')'
				);

			$this->logTask('[LOCK] Query: ' . $query->__toString());

			$db->setQuery($query);
			$acts = $db->loadObjectList();

			$this->logTask('[LOCK] Trovati ' . \count($acts) . ' atti da bloccare');

			if (empty($acts))
			{
				$this->logTask('[LOCK] Nessun atto con buffer scaduto. Task completato.');

				return TaskStatus::OK;
			}

			$locked = 0;

			foreach ($acts as $act)
			{
				$this->logTask(sprintf(
					'[LOCK] Elaborazione atto #%d "%s" (buffer_expires_at=%s, publish_up=%s, data_atto=%s)',
					$act->id,
					$act->titolo,
					$act->buffer_expires_at ?: 'NULL',
					$act->publish_up ?: 'NULL',
					$act->data_atto ?: 'NULL'
				));

				// Calcola hash SHA-256 per ogni allegato
				$this->hashAllegati($db, (int) $act->id);

				// Transizione: stato_atto 1 -> 2, state -> 1 (Joomla published)
				$update = $db->getQuery(true)
					->update($db->quoteName('#__alboonline_atti'))
					->set($db->quoteName('stato_atto') . ' = 2')
					->set($db->quoteName('state') . ' = 1')
					->set($db->quoteName('locked_at') . ' = ' . $db->quote($now))
					->where($db->quoteName('id') . ' = ' . (int) $act->id)
					->where($db->quoteName('stato_atto') . ' = 1');

				$db->setQuery($update);
				$db->execute();
				$rows = $db->getAffectedRows();

				$this->logTask('[LOCK] Atto #' . $act->id . ' – righe aggiornate: ' . $rows);

				if ($rows > 0)
				{
					$this->logTransition((int) $act->id, 'locked', 1, 2, $now);
					$locked++;
				}
				else
				{
					$this->logTask('[LOCK] Atto #' . $act->id . ' – nessuna riga aggiornata (stato gia\' cambiato?)', 'warning');
				}
			}

			$this->logTask(sprintf('[LOCK] Completato – bloccati %d/%d atti', $locked, \count($acts)));

			return TaskStatus::OK;
		}
		catch (\Throwable $e)
		{
			$this->safeLog(
				'[LOCK FATAL] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
				'error'
			);

			return TaskStatus::KNOCKOUT;
		}
	}

	/**
	 * Archivia atti il cui periodo di pubblicazione e' scaduto.
	 * Transizione: Pubblicato (2) -> Archiviato (3)
	 */
	protected function archiveExpired(ExecuteTaskEvent $event): int
	{
		try
		{
			$db  = $this->getDatabase();

			// Tutti i timestamp del componente sono in UTC (vedi M9): confrontiamo in UTC.
			$now = Factory::getDate()->toSql();

			$this->logTask('[ARCHIVE] Task avviato – timestamp UTC: ' . $now);

			$query = $db->getQuery(true)
				->select(['id', 'diritto_oblio'])
				->from($db->quoteName('#__alboonline_atti'))
				->where($db->quoteName('stato_atto') . ' = 2')
				->where($db->quoteName('publish_down') . ' IS NOT NULL')
				->where($db->quoteName('publish_down') . ' <= ' . $db->quote($now));

			$db->setQuery($query);
			$acts = $db->loadObjectList();

			$this->logTask('[ARCHIVE] Trovati ' . \count($acts) . ' atti da archiviare');

			if (empty($acts))
			{
				$this->logTask('[ARCHIVE] Nessun atto da archiviare. Task completato.');

				return TaskStatus::OK;
			}

			$archived = 0;

			foreach ($acts as $act)
			{
				$update = $db->getQuery(true)
					->update($db->quoteName('#__alboonline_atti'))
					->set($db->quoteName('stato_atto') . ' = 3')
					->where($db->quoteName('id') . ' = ' . (int) $act->id)
					->where($db->quoteName('stato_atto') . ' = 2');

				if ((int) $act->diritto_oblio === 1)
				{
					// Diritto all'oblio: state = 0 (Joomla unpublished) — non visibile al pubblico
					$update->set($db->quoteName('state') . ' = 0');
				}
				else
				{
					// Senza diritto oblio: state = 2 (Joomla archived) — resta visibile
					$update->set($db->quoteName('state') . ' = 2');
				}

				$db->setQuery($update);
				$db->execute();

				if ($db->getAffectedRows() > 0)
				{
					$this->logTransition((int) $act->id, 'archived', 2, 3, $now);
					$archived++;

					// M18: alla defissione il periodo è concluso e le date sono
					// definitive: è il momento di emettere il referto congelato.
					$this->emitReferto((int) $act->id);

					if ((int) $act->diritto_oblio === 1)
					{
						$this->logTask('[ARCHIVE] Atto #' . $act->id . ' archiviato CON diritto all\'oblio (non visibile)');

						// Cancellazione fisica file PDF se configurata
						$params = ComponentHelper::getParams('com_alboonline');

						if ((int) $params->get('delete_pdf_on_oblio', 0) === 1)
						{
							$this->deleteAllegatiFiles($db, (int) $act->id);
						}
					}
					else
					{
						$this->logTask('[ARCHIVE] Atto #' . $act->id . ' archiviato SENZA diritto all\'oblio (resta visibile)');
					}
				}
			}

			$this->logTask(sprintf('[ARCHIVE] Completato – archiviati %d/%d atti', $archived, \count($acts)));

			return TaskStatus::OK;
		}
		catch (\Throwable $e)
		{
			$this->safeLog(
				'[ARCHIVE FATAL] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
				'error'
			);

			return TaskStatus::KNOCKOUT;
		}
	}

	/**
	 * Calcola e salva hash SHA-256 per ogni allegato di un atto.
	 */
	private function hashAllegati($db, int $attoId): void
	{
		$query = $db->getQuery(true)
			->select(['id', 'filename'])
			->from($db->quoteName('#__alboonline_allegati'))
			->where($db->quoteName('atto_id') . ' = ' . $attoId);
		$db->setQuery($query);
		$allegati = $db->loadObjectList();

		$this->logTask('[LOCK] Atto #' . $attoId . ': ' . \count($allegati) . ' allegati trovati');

		foreach ($allegati as $allegato)
		{
			if (empty($allegato->filename))
			{
				continue;
			}

			$filePath = JPATH_ROOT . '/media/com_alboonline/allegati/' . $allegato->filename;

			if (!file_exists($filePath))
			{
				$this->logTask('[LOCK] File mancante per allegato #' . $allegato->id . ': ' . $allegato->filename, 'warning');
				continue;
			}

			$hash = hash_file('sha256', $filePath);

			$hashUpdate = $db->getQuery(true)
				->update($db->quoteName('#__alboonline_allegati'))
				->set($db->quoteName('hash') . ' = ' . $db->quote($hash))
				->where($db->quoteName('id') . ' = ' . (int) $allegato->id);
			$db->setQuery($hashUpdate);
			$db->execute();

			$this->logTask('[LOCK] Hash SHA-256 salvato per allegato #' . $allegato->id);
		}
	}

	/**
	 * Elimina fisicamente i file allegati di un atto dal filesystem.
	 * I record nel database vengono mantenuti (filename viene svuotato).
	 */
	private function deleteAllegatiFiles($db, int $attoId): void
	{
		$query = $db->getQuery(true)
			->select(['id', 'filename'])
			->from($db->quoteName('#__alboonline_allegati'))
			->where($db->quoteName('atto_id') . ' = ' . $attoId)
			->where($db->quoteName('filename') . ' != ' . $db->quote(''));
		$db->setQuery($query);
		$allegati = $db->loadObjectList();

		$deleted = 0;

		foreach ($allegati as $allegato)
		{
			$filePath = JPATH_ROOT . '/media/com_alboonline/allegati/' . $allegato->filename;

			if (file_exists($filePath) && @unlink($filePath))
			{
				$deleted++;
				$this->logTask('[ARCHIVE] File eliminato: ' . $allegato->filename);
			}
			else
			{
				$this->logTask('[ARCHIVE] File non trovato o non eliminabile: ' . $allegato->filename, 'warning');
			}

			// Svuota filename nel record (il record resta per tracciabilità)
			$upd = $db->getQuery(true)
				->update($db->quoteName('#__alboonline_allegati'))
				->set($db->quoteName('filename') . ' = ' . $db->quote(''))
				->where($db->quoteName('id') . ' = ' . (int) $allegato->id);
			$db->setQuery($upd);
			$db->execute();
		}

		$this->logTask(sprintf('[ARCHIVE] Atto #%d: %d/%d file PDF eliminati (diritto oblio)', $attoId, $deleted, \count($allegati)));

		// Log nell'audit trail
		$this->logTransition($attoId, 'pdf_deleted', 3, 3, Factory::getDate()->toSql());
	}

	/**
	 * Emette il referto di pubblicazione congelato per un atto appena archiviato.
	 *
	 * L'emissione è SECONDARIA rispetto alla defissione (che è l'atto legale
	 * primario): un errore qui viene loggato ma NON deve far fallire il task, per
	 * non lasciare l'atto pubblicato oltre i termini. L'attore è "System Task".
	 */
	private function emitReferto(int $attoId): void
	{
		try
		{
			$generator = new RefertoGenerator($this->getDatabase());
			$refertoId = $generator->emit($attoId, 0, 'System Task', RefertoGenerator::TIPO_DEFISSIONE);

			if ($refertoId > 0)
			{
				$this->logTask('[ARCHIVE] Referto di pubblicazione emesso per atto #' . $attoId . ' (referto #' . $refertoId . ')');
			}
			elseif ($refertoId === 0)
			{
				$this->logTask('[ARCHIVE] Referto non emesso per atto #' . $attoId . ' (disattivato o già esistente)');
			}
		}
		catch (\Throwable $e)
		{
			$this->safeLog('[ARCHIVE] Emissione referto FALLITA per atto #' . $attoId . ': ' . $e->getMessage(), 'warning');
		}
	}

	/**
	 * Scrive una transizione nella tabella audit log.
	 */
	private function logTransition(int $attoId, string $azione, int $statoPrima, int $statoDopo, string $now): void
	{
		try
		{
			$db = $this->getDatabase();

			$columns = ['atto_id', 'azione', 'stato_prima', 'stato_dopo', 'user_id', 'user_name', 'note', 'created'];
			$values  = [
				$attoId,
				$db->quote($azione),
				$statoPrima,
				$statoDopo,
				0,
				$db->quote('System Task'),
				$db->quote('Transizione automatica da task schedulato'),
				$db->quote($now),
			];

			$query = $db->getQuery(true)
				->insert($db->quoteName('#__alboonline_log'))
				->columns($db->quoteName($columns))
				->values(implode(',', $values));

			$db->setQuery($query);
			$db->execute();
		}
		catch (\Throwable $e)
		{
			$this->logTask('Errore scrittura audit log per atto #' . $attoId . ': ' . $e->getMessage(), 'warning');
		}
	}

	/**
	 * Log con fallback: prova logTask (trait), altrimenti Log::add diretto.
	 * Garantisce che il messaggio venga scritto anche se il trait fallisce.
	 */
	private function safeLog(string $message, string $priority = 'info'): void
	{
		try
		{
			$this->logTask($message, $priority);
		}
		catch (\Throwable $e)
		{
			$priorityMap = [
				'debug'   => Log::DEBUG,
				'error'   => Log::ERROR,
				'info'    => Log::INFO,
				'warning' => Log::WARNING,
			];

			Log::add(
				'plg_task_alboonline: ' . $message,
				$priorityMap[$priority] ?? Log::ERROR,
				'task.alboonline'
			);
		}
	}
}
