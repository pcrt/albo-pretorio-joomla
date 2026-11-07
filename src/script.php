<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

class Com_AlboonlineInstallerScript
{
	protected $minimumPhp = '8.3.0';
	protected $minimumJoomla = '5.1.0';

	/**
	 * I plugin spediti col componente, installati e disinstallati da QUESTO script.
	 *
	 * Fino alla 1.2.1 l'elenco stava nel manifest, in un elemento <plugins> che Joomla
	 * ignora (lo leggeva solo questo script) e che lo schema dei manifest non prevede: un
	 * componente non elenca altre estensioni, quella e' la forma di un pacchetto. Il manifest
	 * ora dichiara solo cio' che Joomla sa leggere; i plugin li conosce chi li installa.
	 * Le cartelle restano in plugins/<gruppo>/<nome> dentro il pacchetto.
	 */
	private const PLUGINS = [
		['group' => 'task',    'plugin' => 'alboonline'],
		['group' => 'content', 'plugin' => 'alboonline'],
	];

	public function preflight($type, InstallerAdapter $parent)
	{
		if (version_compare(PHP_VERSION, $this->minimumPhp, '<'))
		{
			Factory::getApplication()->enqueueMessage(
				Text::sprintf('COM_ALBOONLINE_PHP_VERSION_ERROR', $this->minimumPhp),
				'error'
			);
			return false;
		}

		return true;
	}

	public function install(InstallerAdapter $parent)
	{
		$this->createAllegatiDirectory();
		$this->createRefertiDirectory();
		$this->createImagesDirectory();
		$this->impostaParametriIniziali();
		$this->installPlugins($parent);
		return true;
	}

	/**
	 * Parametri iniziali del componente su un'installazione NUOVA.
	 *
	 * Fino alla 1.2.1 li seminava l'elemento <config> del manifest, che Joomla legge
	 * (Installer::getParams()) ma che lo schema dei manifest riserva a moduli, plugin e
	 * template: per i componenti i parametri stanno in config.xml. Senza quel seme, pero',
	 * `save_history` resterebbe non impostato finche' l'amministratore non salva le Opzioni,
	 * e il plugin di versionamento di Joomla lo legge con predefinito 0: la cronologia delle
	 * modifiche agli atti partirebbe spenta. Quindi il seme lo mette questo script, SOLO se
	 * il parametro manca: un valore scelto dall'ente non si tocca. Gira dopo che Joomla ha
	 * scritto la riga in #__extensions (InstallerAdapter: storeExtension precede install).
	 */
	private function impostaParametriIniziali(): void
	{
		try
		{
			$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

			$query = $db->getQuery(true)
				->select($db->quoteName(['extension_id', 'params']))
				->from($db->quoteName('#__extensions'))
				->where($db->quoteName('type') . ' = ' . $db->quote('component'))
				->where($db->quoteName('element') . ' = ' . $db->quote('com_alboonline'));
			$riga = $db->setQuery($query)->loadObject();

			if (!$riga)
			{
				return;
			}

			$params = json_decode((string) $riga->params, true);
			$params = \is_array($params) ? $params : [];

			if (\array_key_exists('save_history', $params))
			{
				return;
			}

			$params['save_history'] = '1';

			$query = $db->getQuery(true)
				->update($db->quoteName('#__extensions'))
				->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($params)))
				->where($db->quoteName('extension_id') . ' = ' . (int) $riga->extension_id);
			$db->setQuery($query)->execute();
		}
		catch (\Throwable $e)
		{
			// Non si fa fallire l'installazione per un'opzione: l'amministratore la trova
			// nelle Opzioni (config.xml la propone gia' a Si'). Ma lo si dice.
			Factory::getApplication()->enqueueMessage(
				'Albo Pretorio Online: could not set the initial option save_history=1; check it in Options.',
				'warning'
			);
		}
	}

	public function update(InstallerAdapter $parent)
	{
		$this->createAllegatiDirectory();
		$this->createRefertiDirectory();
		$this->createImagesDirectory();
		$this->rimuoviLingueLasciateNelCore();
		$this->installPlugins($parent);
		return true;
	}

	public function uninstall(InstallerAdapter $parent)
	{
		$this->rimuoviLingueLasciateNelCore();
		$this->uninstallPlugins($parent);
		return true;
	}

	/**
	 * Toglie dalle cartelle di lingua del CORE i nostri .ini lasciati li' dalle versioni
	 * fino alla 1.1.0, che li spedivano con <languages>.
	 *
	 * PERCHE' SERVE, ed e' meno ovvio di quanto sembri.
	 * Le lingue ora viaggiano dentro l'estensione e <languages> non c'e' piu'. Ma Joomla NON
	 * cancella i file che un manifest ha smesso di dichiarare: aggiornando da una versione
	 * vecchia restano li'. E non restano innocui — VINCONO: Language::load() cerca prima nel
	 * core e ripiega sull'estensione solo se non trova niente
	 * (load($ext, JPATH_BASE) || load($ext, $percorsoEstensione)). Quindi l'ente si terrebbe
	 * le traduzioni della versione vecchia per sempre, e ogni etichetta NUOVA gli uscirebbe
	 * come codice grezzo: un pulsante che si chiama COM_ALBOONLINE_ALLEGATI_CARICA.
	 *
	 * Verificato aggiornando la 1.0.0 pubblicata al pacchetto nuovo: 4 codici grezzi nella
	 * scheda atto. Non e' un'ipotesi.
	 *
	 * ATTENZIONE: qui si cancella dentro casa di Joomla. Si tolgono SOLO i file con i nostri
	 * nomi esatti — mai un glob, mai una cartella. Un file di lingua del core cancellato per
	 * sbaglio rompe il sito dell'ente, non il nostro componente.
	 */
	private function rimuoviLingueLasciateNelCore(): void
	{
		$nostri = [
			JPATH_ADMINISTRATOR . '/language' => [
				'com_alboonline.ini',
				'com_alboonline.sys.ini',
				'plg_task_alboonline.ini',
				'plg_task_alboonline.sys.ini',
				'plg_content_alboonline.ini',
				'plg_content_alboonline.sys.ini',
			],
			JPATH_SITE . '/language' => [
				'com_alboonline.ini',
			],
		];

		$tolti = 0;

		foreach ($nostri as $cartellaLingue => $nomi)
		{
			if (!is_dir($cartellaLingue))
			{
				continue;
			}

			foreach (Folder::folders($cartellaLingue) as $tag)
			{
				foreach ($nomi as $nome)
				{
					$file = $cartellaLingue . '/' . $tag . '/' . $nome;

					if (is_file($file) && File::delete($file))
					{
						$tolti++;
					}
				}
			}
		}

		// Non si avvisa l'amministratore: per lui non e' successo niente di interessante,
		// e un messaggio in piu' all'installazione e' solo rumore in cui perdere quelli veri.
		unset($tolti);
	}

	public function postflight($type, InstallerAdapter $parent)
	{
		$this->regenerateNamespaceMap();
		$this->createScheduledTasks();
		return true;
	}

	/**
	 * I DUE LAVORI PIANIFICATI SENZA I QUALI L'ALBO NON E' UN ALBO (M37).
	 *
	 * L'albo pretorio non e' un elenco di pagine: e' un procedimento con delle scadenze.
	 * Due passaggi non li fa nessun essere umano, li deve fare il sistema da solo:
	 *
	 *   - `lock`    allo scadere del buffer rende l'atto PUBBLICO e lo sigilla.
	 *               Se non gira, gli atti restano «In attesa» per sempre: l'ente crede
	 *               di aver pubblicato e NON ha pubblicato. La pubblicita' legale non avviene.
	 *   - `archive` a fine periodo DEFIGGE l'atto ed emette il referto.
	 *               Se non gira, gli atti restano online oltre i termini (permanenza
	 *               indebita: fattispecie per cui il Garante ha gia' sanzionato enti reali)
	 *               e non resta alcuna prova di quando e per quanto si e' pubblicato.
	 *
	 * Finora l'installazione li lasciava creare a mano. Chiedere a un funzionario di
	 * ricordarsi due attivita' pianificate, in una schermata che non e' la nostra, perche'
	 * altrimenti gli atti "sembrano pubblicati ma non lo sono", non e' una configurazione:
	 * e' una trappola. Quindi li creiamo noi.
	 *
	 * DUE REGOLE, e sono deliberate:
	 *
	 *  1. Si crea SOLO cio' che NON esiste. Il confronto e' sul `type` (l'identificativo
	 *     della routine), non sul titolo: il titolo l'amministratore puo' cambiarlo.
	 *     Cosi' l'aggiornamento non duplica nulla.
	 *
	 *  2. Non si tocca MAI un task che gia' esiste, nemmeno se e' DISATTIVATO.
	 *     Riaccenderlo di nascosto a ogni aggiornamento significherebbe scavalcare una
	 *     scelta esplicita dell'amministratore. Se e' spento, il componente lo dice gia'
	 *     in cima all'elenco atti (Atti\HtmlView::verificaTaskPianificati()): meglio un
	 *     avviso che vede, di una modifica che non vede.
	 *
	 * LIMITE ONESTO, da dire nella guida: creare i task NON basta. Joomla non ha un
	 * orologio proprio — se il sito e' impostato sullo "scheduler pigro" (predefinito), i
	 * lavori partono solo QUANDO QUALCUNO VISITA IL SITO. Su un albo poco visitato un atto
	 * puo' restare «In attesa» per ore. Questo lo puo' sistemare solo l'amministratore
	 * (cron di sistema): noi possiamo solo dirglielo, ed e' quello che facciamo qui sotto.
	 */
	private function createScheduledTasks()
	{
		$app = Factory::getApplication();

		// In fase di installazione NESSUNA di queste due lingue e' gia' caricata: senza
		// questi due load, l'amministratore vede a schermo la chiave grezza
		// «COM_ALBOONLINE_TASK_CREATI» invece del messaggio. (Successo davvero: il primo
		// pacchetto installato lo ha stampato cosi'.)
		$lang = Factory::getLanguage();
		$lang->load('plg_task_alboonline', JPATH_ADMINISTRATOR);
		$lang->load('com_alboonline', JPATH_ADMINISTRATOR);

		$tasks = [
			[
				'type'    => 'plg_task_alboonline_lock',
				'title'   => 'PLG_TASK_ALBOONLINE_LOCK_TITLE',
				'fallback' => 'Albo Pretorio - Blocca Atti Pubblicati',
				// Ogni 5 minuti: e' la finestra tra "scade il buffer" e "l'atto e' davvero
				// pubblico". Piu' lunga, e la data certa si allontana dal momento reale.
				'minuti'  => 5,
				'exp'     => 'PT5M',
				'nota'    => 'Creato automaticamente all\'installazione. Rende pubblici e sigilla gli atti il cui buffer e\' scaduto. Senza questo lavoro gli atti NON diventano mai pubblici.',
			],
			[
				'type'    => 'plg_task_alboonline_archive',
				'title'   => 'PLG_TASK_ALBOONLINE_ARCHIVE_TITLE',
				'fallback' => 'Albo Pretorio - Archivia Atti Scaduti',
				// Ogni 15 minuti: la defissione non ha bisogno del minuto, ma un ritardo
				// lungo e' permanenza indebita.
				'minuti'  => 15,
				'exp'     => 'PT15M',
				'nota'    => 'Creato automaticamente all\'installazione. Defigge gli atti scaduti ed emette il referto di pubblicazione. Senza questo lavoro gli atti restano online oltre i termini.',
			],
		];

		$db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$creati = [];

		foreach ($tasks as $task)
		{
			try
			{
				// Esiste gia' un task di questo tipo, in QUALUNQUE stato? Allora non si tocca.
				$query = $db->getQuery(true)
					->select('COUNT(*)')
					->from($db->quoteName('#__scheduler_tasks'))
					->where($db->quoteName('type') . ' = :type')
					->bind(':type', $task['type']);

				if ((int) $db->setQuery($query)->loadResult() > 0)
				{
					continue;
				}

				$titolo = Text::_($task['title']);

				// Se la lingua non e' caricata, Text::_ restituisce la chiave: meglio
				// un titolo leggibile che "PLG_TASK_ALBOONLINE_LOCK_TITLE" nella schermata.
				if ($titolo === $task['title'])
				{
					$titolo = $task['fallback'];
				}

				$now = Factory::getDate()->toSql();

				$row = (object) [
					'title'           => $titolo,
					'type'            => $task['type'],
					'execution_rules' => json_encode([
						'rule-type'        => 'interval-minutes',
						'interval-minutes' => (string) $task['minuti'],
						'exec-day'         => '1',
						'exec-time'        => '00:00',
					]),
					'cron_rules'      => json_encode([
						'type' => 'interval',
						'exp'  => $task['exp'],
					]),
					'state'           => 1,
					'priority'        => 0,
					'ordering'        => 0,
					'cli_exclusive'   => 0,
					'params'          => json_encode([]),
					'note'            => $task['nota'],
					'created'         => $now,
					// Da CLI (extension:install) non c'e' un utente collegato: getIdentity()
					// puo' non esserci. Un installatore che va in errore fatale per il campo
					// "chi l'ha creato" sarebbe un pessimo scambio.
					'created_by'      => (int) ($app->getIdentity()?->id ?? 0),
					// Esigibile subito: senza, il primo giro potrebbe non partire mai.
					'next_execution'  => $now,
					'times_executed'  => 0,
					'times_failed'    => 0,
					'asset_id'        => 0,
				];

				$db->insertObject('#__scheduler_tasks', $row);
				$creati[] = $titolo;
			}
			catch (\Throwable $e)
			{
				// Non si fa fallire l'installazione per questo: il componente resta usabile
				// e l'avviso in cima all'elenco atti dira' che il lavoro manca. Ma lo si
				// dice subito, invece di lasciar credere che sia tutto a posto.
				$app->enqueueMessage(
					Text::sprintf('COM_ALBOONLINE_TASK_CREAZIONE_FALLITA', $task['fallback']),
					'warning'
				);
			}
		}

		if ($creati)
		{
			$app->enqueueMessage(
				Text::sprintf('COM_ALBOONLINE_TASK_CREATI', implode('», «', $creati)),
				'notice'
			);
		}
	}

	private function regenerateNamespaceMap()
	{
		try
		{
			$mapFile = JPATH_ADMINISTRATOR . '/cache/autoload_psr4.php';

			if (class_exists('JNamespacePsr4Map'))
			{
				$map = new \JNamespacePsr4Map();
				$map->create();
			}
			elseif (file_exists(JPATH_LIBRARIES . '/namespacemap.php'))
			{
				require_once JPATH_LIBRARIES . '/namespacemap.php';

				if (class_exists('JNamespacePsr4Map'))
				{
					$map = new \JNamespacePsr4Map();
					$map->create();
				}
			}
		}
		catch (\Throwable $e)
		{
			// Silently ignore — namespace map will be regenerated on next page load
		}
	}

	private function createRefertiDirectory()
	{
		$path = JPATH_ROOT . '/media/com_alboonline/referti';

		if (!is_dir($path))
		{
			Folder::create($path);
		}

		$htaccess = $path . '/.htaccess';

		if (!file_exists($htaccess))
		{
			file_put_contents($htaccess, "Require all denied\n");
		}

		$indexHtml = $path . '/index.html';

		if (!file_exists($indexHtml))
		{
			file_put_contents($indexHtml, '<html><body></body></html>');
		}
	}

	private function createImagesDirectory()
	{
		$path = JPATH_ROOT . '/images/com_alboonline';

		if (!is_dir($path))
		{
			Folder::create($path);
		}

		$indexHtml = $path . '/index.html';

		if (!file_exists($indexHtml))
		{
			file_put_contents($indexHtml, '<html><body></body></html>');
		}
	}

	private function createAllegatiDirectory()
	{
		$path = JPATH_ROOT . '/media/com_alboonline/allegati';

		if (!is_dir($path))
		{
			Folder::create($path);
		}

		$htaccess = $path . '/.htaccess';

		if (!file_exists($htaccess))
		{
			file_put_contents($htaccess, "Require all denied\n");
		}

		$indexHtml = $path . '/index.html';

		if (!file_exists($indexHtml))
		{
			file_put_contents($indexHtml, '<html><body></body></html>');
		}
	}

	private function installPlugins(InstallerAdapter $parent)
	{
		$installationFolder = $parent->getParent()->getPath('source');
		$app = Factory::getApplication();

		$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$query = $db->getQuery(true);

		foreach (self::PLUGINS as $plugin)
		{
			$pluginName  = $plugin['plugin'];
			$pluginGroup = $plugin['group'];
			$path        = $installationFolder . '/plugins/' . $pluginGroup . '/' . $pluginName;
			$installer   = new Installer;
			// Joomla 6: l'Installer richiede il database impostato esplicitamente
			$installer->setDatabase($db);

			if (!file_exists(JPATH_PLUGINS . '/' . $pluginGroup . '/' . $pluginName))
			{
				$result = $installer->install($path);
			}
			else
			{
				$result = $installer->update($path);
			}

			if ($result)
			{
				$app->enqueueMessage('Plugin ' . $pluginName . ' was installed successfully');
			}
			else
			{
				$app->enqueueMessage('There was an issue installing the plugin ' . $pluginName, 'error');
			}

			// Enable the plugin
			$query->clear()
				->update('#__extensions')
				->set('enabled = 1')
				->where([
					'type LIKE ' . $db->quote('plugin'),
					'element LIKE ' . $db->quote($pluginName),
					'folder LIKE ' . $db->quote($pluginGroup),
				]);
			$db->setQuery($query);
			$db->execute();
		}
	}

	private function uninstallPlugins(InstallerAdapter $parent)
	{
		$app = Factory::getApplication();

		$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$query = $db->getQuery(true);

		foreach (self::PLUGINS as $plugin)
		{
			$pluginName  = $plugin['plugin'];
			$pluginGroup = $plugin['group'];

			$query->clear()
				->select('extension_id')
				->from('#__extensions')
				->where([
					'type LIKE ' . $db->quote('plugin'),
					'element LIKE ' . $db->quote($pluginName),
					'folder LIKE ' . $db->quote($pluginGroup),
				]);
			$db->setQuery($query);
			$extension = $db->loadResult();

			if (!empty($extension))
			{
				$installer = new Installer;
				// Joomla 6: l'Installer richiede il database impostato esplicitamente
				$installer->setDatabase($db);
				$result = $installer->uninstall('plugin', $extension);

				if ($result)
				{
					$app->enqueueMessage('Plugin ' . $pluginName . ' was uninstalled successfully');
				}
				else
				{
					$app->enqueueMessage('There was an issue uninstalling the plugin ' . $pluginName, 'error');
				}
			}
		}
	}
}
