<?php
/**
 * @package    Com_Alboonline
 * @copyright  2025
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;
use Pcrt\Component\Alboonline\Administrator\Helper\AlboonlineHelper;

/**
 * Gestione dei referti gia' emessi (M18, fase 5).
 *
 * Aggancia la versione FIRMATA di un referto senza mai toccare l'emesso: il PDF
 * sigillato alla defissione resta immutabile (invariante append-only). La firma
 * avviene FUORI dal componente (Dike/ArubaSign/smart card sulla postazione del
 * funzionario); qui si ricarica il file firmato (PAdES .pdf o CAdES .p7m).
 *
 * La VERIFICA della firma (firmatario, validita', marca temporale, confronto
 * hash del documento incapsulato per il CAdES) e' rimandata a S3-T1: per ora si
 * conserva il file + la sua impronta e si registra l'azione.
 */
class RefertoModel extends BaseDatabaseModel
{
    private const REFERTI_DIR = '/media/com_alboonline/referti';
    private const MAX_SIZE     = 20 * 1024 * 1024; // 20 MB

    /**
     * Aggancia il file firmato a un referto: stato emesso -> firmato.
     *
     * @param   int    $refertoId  ID del referto
     * @param   array  $file       Voce di $_FILES (name, tmp_name, size, error)
     * @param   int    $userId     Utente che ricarica
     *
     * @return  bool  true in caso di successo; false con setError() altrimenti.
     */
    public function attachSigned(int $refertoId, array $file, int $userId): bool
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'atto_id', 'numero_repertorio', 'pdf_filename', 'firmato_filename']))
            ->from($db->quoteName('#__alboonline_referti'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $refertoId, ParameterType::INTEGER);
        $db->setQuery($query);
        $referto = $db->loadObject();

        if (!$referto) {
            $this->setError(Text::_('COM_ALBOONLINE_REFERTO_NON_TROVATO'));

            return false;
        }

        $stored = $this->validateAndStore($file);

        if ($stored === false) {
            return false;
        }

        // Rimuove un eventuale file firmato precedente (sostituzione): l'EMESSO
        // resta comunque intatto, l'invariante append-only riguarda il sigillo.
        if (!empty($referto->firmato_filename) && $referto->firmato_filename !== $stored['filename']) {
            $old = JPATH_ROOT . self::REFERTI_DIR . '/' . $referto->firmato_filename;

            if (is_file($old)) {
                @unlink($old);
            }
        }

        $now = Factory::getDate()->toSql();

        $update = (object) [
            'id'               => $refertoId,
            'stato'            => 'firmato',
            'firmato_filename' => $stored['filename'],
            'firmato_hash'     => $stored['hash'],
            'firmato_mime'     => $stored['mime'],
            'firmato_at'       => $now,
            'firmato_by'       => $userId,
        ];

        $db->updateObject('#__alboonline_referti', $update, 'id');

        AlboonlineHelper::logAction(
            (int) $referto->atto_id,
            'referto_firmato',
            null,
            null,
            'Ricaricata versione firmata del referto (rep. ' . $referto->numero_repertorio . ')'
        );

        return true;
    }

    /**
     * Valida il file caricato e lo scrive in media/com_alboonline/referti.
     *
     * @return  array|false  ['filename','hash','mime'] oppure false con setError().
     */
    private function validateAndStore(array $file)
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->setError(Text::_('COM_ALBOONLINE_ERROR_FILE_UPLOAD'));

            return false;
        }

        if (($file['size'] ?? 0) <= 0 || $file['size'] > self::MAX_SIZE) {
            $this->setError(Text::_('COM_ALBOONLINE_ERROR_FILE_TOO_LARGE'));

            return false;
        }

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

        if (!\in_array($ext, ['pdf', 'p7m'], true)) {
            $this->setError(Text::_('COM_ALBOONLINE_ERROR_FILE_TYPE_NOT_ALLOWED'));

            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // PDF (PAdES): controllo stretto sui magic byte. Il .p7m (CAdES) e' un
        // contenitore di firma: finfo e' inaffidabile, quindi ci fidiamo
        // dell'estensione e conserviamo il mime rilevato (verifica vera in S3-T1).
        if ($ext === 'pdf') {
            $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 5);

            if (substr($head, 0, 5) !== '%PDF-' || $mime !== 'application/pdf') {
                $this->setError(Text::_('COM_ALBOONLINE_ERROR_FILE_TYPE_NOT_ALLOWED'));

                return false;
            }
        }

        $hash       = hash_file('sha256', $file['tmp_name']);
        $storedName = $hash . '-firmato.' . $ext;
        $destPath   = JPATH_ROOT . self::REFERTI_DIR . '/' . $storedName;

        if (!File::upload($file['tmp_name'], $destPath)) {
            $this->setError(Text::_('COM_ALBOONLINE_ERROR_FILE_MOVE'));

            return false;
        }

        return [
            'filename' => $storedName,
            'hash'     => $hash,
            'mime'     => $mime ?: 'application/octet-stream',
        ];
    }
}
