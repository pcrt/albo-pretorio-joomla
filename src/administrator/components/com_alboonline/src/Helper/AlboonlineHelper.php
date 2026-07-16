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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Object\CMSObject;

class AlboonlineHelper
{
	public const STATO_BOZZA      = 0;
	public const STATO_IN_ATTESA  = 1;
	public const STATO_PUBBLICATO = 2;
	public const STATO_ARCHIVIATO = 3;
	public const STATO_CESTINATO  = -2;

	/**
	 * Default tipi atto (fallback when component params are empty).
	 * Kept as constant for backward compatibility with existing code that references TIPO_ATTI.
	 */
	public const TIPO_ATTI = [
		'generico'  => 'Generico',
		'determina' => 'Determina',
		'delibera'  => 'Delibera',
		'decreto'   => 'Decreto',
		'bando'     => 'Bando',
		'avviso'    => 'Avviso',
	];

	/**
	 * Restituisce i tipi atto configurati nelle Opzioni del componente.
	 * Formato: ['valore' => 'Etichetta', ...]
	 */
	public static function getTipiAtto(): array
	{
		static $cache = null;

		if ($cache !== null)
		{
			return $cache;
		}

		$tipi = [];

		foreach (self::getTipiAttoConfig() as $valore => $cfg)
		{
			$tipi[$valore] = $cfg['etichetta'];
		}

		return $cache = $tipi;
	}

	/**
	 * Configurazione completa dei tipi atto dalle Opzioni componente.
	 * Formato: ['valore' => ['etichetta' => string, 'giorni' => int|null], ...]
	 * 'giorni' = durata di pubblicazione specifica del tipo (null = usa il default globale).
	 */
	public static function getTipiAttoConfig(): array
	{
		static $cacheFull = null;

		if ($cacheFull !== null)
		{
			return $cacheFull;
		}

		// Fallback (costante) senza durata specifica.
		$fallback = [];
		foreach (self::TIPO_ATTI as $valore => $etichetta)
		{
			$fallback[$valore] = ['etichetta' => $etichetta, 'giorni' => null];
		}

		// Reload diretto da DB: bypassa la cache statica di ComponentHelper
		// per riflettere subito le modifiche fatte in Opzioni componente.
		$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$query = $db->getQuery(true)
			->select($db->quoteName('params'))
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('element') . ' = ' . $db->quote('com_alboonline'))
			->where($db->quoteName('type') . ' = ' . $db->quote('component'));
		$db->setQuery($query);
		$paramsJson = (string) $db->loadResult();

		$tipiRaw = '';
		if ($paramsJson !== '')
		{
			$decoded = json_decode($paramsJson, true);
			if (is_array($decoded) && isset($decoded['tipi_atto']))
			{
				$tipiRaw = $decoded['tipi_atto'];
			}
		}

		if (is_string($tipiRaw))
		{
			$tipiRaw = json_decode($tipiRaw, true);
		}

		if (!is_array($tipiRaw) || empty($tipiRaw))
		{
			return $cacheFull = $fallback;
		}

		$tipi = [];

		foreach ($tipiRaw as $item)
		{
			if (is_object($item))
			{
				$item = (array) $item;
			}

			if (!empty($item['valore']) && !empty($item['etichetta']))
			{
				$giorni = null;

				if (isset($item['giorni_pubblicazione']) && (int) $item['giorni_pubblicazione'] > 0)
				{
					$giorni = (int) $item['giorni_pubblicazione'];
				}

				$tipi[trim($item['valore'])] = [
					'etichetta' => trim($item['etichetta']),
					'giorni'    => $giorni,
				];
			}
		}

		return $cacheFull = (!empty($tipi) ? $tipi : $fallback);
	}

	/**
	 * Giorni di pubblicazione specifici per una tipologia di atto.
	 *
	 * @return int|null  null se il tipo non ha una durata propria (usa il default globale)
	 */
	public static function getGiorniPubblicazione(string $tipoAtto): ?int
	{
		$config = self::getTipiAttoConfig();

		return $config[$tipoAtto]['giorni'] ?? null;
	}

	/**
	 * Restituisce l'etichetta di un tipo atto.
	 */
	public static function getTipoLabel(string $tipoAtto): string
	{
		$tipi = self::getTipiAtto();

		return $tipi[$tipoAtto] ?? ucfirst($tipoAtto);
	}

	public static function getActions()
	{
		$user = Factory::getApplication()->getIdentity();
		$result = new CMSObject;

		$assetName = 'com_alboonline';

		$actions = [
			'core.admin', 'core.manage', 'core.create', 'core.edit', 'core.edit.own',
			'core.edit.state', 'core.delete',
			'alboonline.publish', 'alboonline.rectify', 'alboonline.download.archived',
			// alboonline.revokeoblio governa la gestione dell'oblio (attivazione e revoca,
			// S4-T4/M16). Mancava qui: il relativo pannello non compariva mai in UI.
			'alboonline.revokeoblio',
		];

		foreach ($actions as $action)
		{
			$result->set($action, $user->authorise($action, $assetName));
		}

		return $result;
	}

	/**
	 * Ente emanante da apporre sui documenti probatori (referto, registro).
	 *
	 * Risolve nell'ordine: ente proprio dell'atto -> ente predefinito del componente.
	 * Il valore e' sempre normalizzato con trim(): un ente non deve mai comparire
	 * su un'attestazione con spaziatura spuria.
	 *
	 * @param   string|null  $enteAtto  Ente indicato sull'atto (facoltativo)
	 *
	 * @return  string  Ente risolto, oppure stringa vuota se non configurato.
	 */
	public static function getEnteEmanante(?string $enteAtto = null): string
	{
		$ente = trim((string) $enteAtto);

		if ($ente !== '')
		{
			return $ente;
		}

		$params = ComponentHelper::getParams('com_alboonline');

		return trim((string) $params->get('ente_emanante_default', ''));
	}

	/**
	 * Chiave di lingua dello stato. NON e' testo: va tradotta prima di mostrarla.
	 * Nella maggior parte dei casi serve `getStatoLabel()`, che restituisce gia'
	 * l'etichetta tradotta.
	 */
	public static function getStatoLabelKey(int $stato): string
	{
		$chiavi = [
			self::STATO_BOZZA      => 'COM_ALBOONLINE_STATO_BOZZA',
			self::STATO_IN_ATTESA  => 'COM_ALBOONLINE_STATO_IN_ATTESA',
			self::STATO_PUBBLICATO => 'COM_ALBOONLINE_STATO_PUBBLICATO',
			self::STATO_ARCHIVIATO => 'COM_ALBOONLINE_STATO_ARCHIVIATO',
			self::STATO_CESTINATO  => 'COM_ALBOONLINE_STATO_CESTINATO',
		];

		return $chiavi[$stato] ?? 'COM_ALBOONLINE_STATO_BOZZA';
	}

	/**
	 * Etichetta dello stato, gia' tradotta e pronta da mostrare.
	 *
	 * Prima restituiva la chiave di lingua: referto e registro la stampavano grezza,
	 * esponendo `COM_ALBOONLINE_STATO_PUBBLICATO` sui due documenti probatori. Il nome
	 * prometteva un'etichetta e consegnava una chiave. Vedi M15.
	 */
	public static function getStatoLabel(int $stato): string
	{
		return Text::_(self::getStatoLabelKey($stato));
	}

	public static function getStatoBadgeClass(int $stato): string
	{
		$classes = [
			self::STATO_BOZZA      => 'bg-secondary',
			self::STATO_IN_ATTESA  => 'bg-warning text-dark',
			self::STATO_PUBBLICATO => 'bg-success',
			self::STATO_ARCHIVIATO => 'bg-info',
			self::STATO_CESTINATO  => 'bg-danger',
		];

		return $classes[$stato] ?? 'bg-secondary';
	}

	public static function isLocked(object $item): bool
	{
		return (int) $item->stato_atto >= self::STATO_PUBBLICATO;
	}

	/**
	 * Restituisce gli atti con visibilita homepage.
	 * Usato dai moduli Joomla per mostrare atti nelle sezioni "Comunicazioni" o "In Evidenza".
	 *
	 * @param string $sezione  'comunicazioni' o 'evidenza'
	 * @param int    $limit    Numero massimo di risultati
	 *
	 * @return array Lista di oggetti atto
	 */
	public static function getAttiHomepage(string $sezione, int $limit = 5): array
	{
		$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

		$query = $db->getQuery(true)
			->select(['a.id', 'a.titolo', 'a.alias', 'a.oggetto', 'a.numero_protocollo',
				'a.tipo_atto', 'a.data_atto', 'a.publish_up', 'a.catid'])
			->from($db->quoteName('#__alboonline_atti', 'a'))
			->where($db->quoteName('a.visibilita_home') . ' = ' . $db->quote($sezione))
			->where($db->quoteName('a.stato_atto') . ' = ' . self::STATO_PUBBLICATO)
			->where($db->quoteName('a.state') . ' = 1')
			->order($db->quoteName('a.publish_up') . ' DESC');
		$db->setQuery($query, 0, $limit);

		return $db->loadObjectList() ?: [];
	}

	public static function logAction(int $attoId, string $azione, ?int $statoPrima = null, ?int $statoDopo = null, ?string $note = null): void
	{
		$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$user = Factory::getApplication()->getIdentity();
		$app = Factory::getApplication();

		$record = (object) [
			'atto_id'     => $attoId,
			'azione'      => $azione,
			'stato_prima' => $statoPrima,
			'stato_dopo'  => $statoDopo,
			'user_id'     => $user->id,
			'user_name'   => $user->name,
			'ip_address'  => $app->input->server->getString('REMOTE_ADDR', ''),
			'note'        => $note,
			'created'     => Factory::getDate()->toSql(),
		];

		$db->insertObject('#__alboonline_log', $record);
	}
}
