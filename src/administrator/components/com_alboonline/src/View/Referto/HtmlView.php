<?php
/**
 * @package    Com_Alboonline
 * @author     Eddy Prosperi <eddy.prosperi@protocollicreativi.it>
 * @copyright  2025 Eddy Prosperi
 * @license    GNU General Public License versione 2 o successiva; vedi LICENSE.txt
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Pcrt\Component\Alboonline\Administrator\View\Referto;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Pcrt\Component\Alboonline\Administrator\Helper\FirmaVerifier;

/**
 * Elenco dei referti di pubblicazione EMESSI per un atto (M18).
 *
 * A differenza della versione precedente, questa vista NON genera piu' il referto
 * al volo: mostra i documenti gia' congelati e sigillati (uno per periodo di
 * pubblicazione) e ne consente il download del PDF immutabile. Il referto si
 * emette automaticamente alla defissione (vedi RefertoGenerator + scheduler).
 */
class HtmlView extends BaseHtmlView
{
    protected $atto;
    protected $referti = [];
    protected $canDownload = false;
    protected $canUpload = false;

    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $id  = (int) $app->getInput()->getInt('id', 0);
        $db  = Factory::getContainer()->get(DatabaseInterface::class);

        // Atto di riferimento (solo per l'intestazione).
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'titolo', 'oggetto', 'numero_protocollo', 'stato_atto']))
            ->from($db->quoteName('#__alboonline_atti'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);
        $db->setQuery($query);
        $this->atto = $db->loadObject();

        if (!$this->atto) {
            throw new \Exception(Text::_('JGLOBAL_ITEM_NOT_FOUND'), 404);
        }

        // Referti emessi per questo atto (piu' recente in cima).
        $query = $db->getQuery(true)
            ->select($db->quoteName([
                'id', 'tipo_documento', 'numero_repertorio', 'publish_up', 'publish_down',
                'pdf_hash', 'pdf_size', 'stato', 'emesso_at', 'emesso_by',
                'firmato_filename', 'firmato_at', 'firmato_by',
            ]))
            ->from($db->quoteName('#__alboonline_referti'))
            ->where($db->quoteName('atto_id') . ' = :id')
            ->order($db->quoteName('emesso_at') . ' DESC')
            ->bind(':id', $id, ParameterType::INTEGER);
        $db->setQuery($query);
        $this->referti = $db->loadObjectList() ?: [];

        // Verifica INTERNA (al volo) dei referti firmati: integrità + firmatario +
        // corrispondenza col referto emesso (l'eContent del .p7m deve avere lo stesso
        // hash del PDF emesso => "hanno firmato PROPRIO questo referto"). Vedi S3-T1.
        $verifier = new FirmaVerifier();
        $baseDir  = JPATH_ROOT . '/media/com_alboonline/referti/';

        foreach ($this->referti as $r) {
            $r->firma = null;
            $r->firma_corrisponde = null;

            if ($r->stato === 'firmato' && !empty($r->firmato_filename)) {
                $path = $baseDir . $r->firmato_filename;

                if (is_file($path)) {
                    $r->firma = $verifier->verify($path);

                    // Corrispondenza col referto emesso: solo per CAdES, dove il
                    // contenuto incapsulato è il PDF emesso (per PAdES la firma
                    // modifica il PDF, quindi il confronto whole-file non vale).
                    if (!empty($r->firma->contenuto_hash) && !empty($r->pdf_hash)) {
                        $r->firma_corrisponde = hash_equals($r->pdf_hash, $r->firma->contenuto_hash);
                    }
                }
            }
        }

        $identity = $app->getIdentity();
        $this->canDownload = $identity->authorise('alboonline.download.archived', 'com_alboonline');
        $this->canUpload   = $identity->authorise('alboonline.publish', 'com_alboonline');

        parent::display($tpl);
    }
}
