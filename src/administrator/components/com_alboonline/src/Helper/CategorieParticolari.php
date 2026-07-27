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

/**
 * Rilevatore EURISTICO di possibili "categorie particolari" di dati nel testo di
 * un atto (S4-T2). È una MISURA DI SUPPORTO, non un obbligo: avvisa il redattore,
 * non blocca né decide al posto suo (la valutazione resta dell'ente).
 *
 * L'OBBLIGO di non diffondere questi dati viene dalla norma: art. 9 GDPR (categorie
 * particolari), art. 2-octies Codice Privacy (dati giudiziari), art. 26 c.4
 * D.Lgs. 33/2013 (cautela per disagio economico-sociale e minori), Linee Guida
 * Garante 2014. Questo strumento aiuta soltanto a NON dimenticarsene.
 *
 * Necessariamente approssimato: può dare falsi positivi (parole usate in contesti
 * innocui) e falsi negativi (dati espressi senza le parole-spia). Per questo
 * l'esito è un semplice avviso.
 */
class CategorieParticolari
{
    /**
     * Termini-spia per categoria. Espressi come alternative regex (già in minuscolo),
     * confinati ai bordi di parola in scan(). Volutamente moderati per limitare i
     * falsi positivi (es. niente "reddito"/"contributo" da soli).
     */
    private const TERMINI = [
        'salute' => [
            'salute', 'malatti\w+', 'patologi\w+', 'diagnosi', 'invalidit\w+', 'invalid[oi]',
            'disabil\w+', 'handicap', 'legge 104', 'l\. ?104', 'terapi\w+', 'ricover\w+',
            'cartella clinica', 'esenzione ticket', 'psichiatr\w+', 'tossicodipend\w+',
            'hiv', 'gravidanza', 'certificato medico', 'diversamente abil\w+',
        ],
        'giudiziari' => [
            'casellario', 'carichi pendenti', 'condann\w+', 'penale', 'reat[oi]',
            'procedimento penale', 'indagat[oi]', 'imputat[oi]', 'precedenti penali',
            'misura cautelare', 'sanzione penale',
        ],
        'minori' => [
            'minorenne', 'minor[ei] (età|eta)', 'tutela del minore', 'affidamento',
            'adozione', 'minori a rischio',
        ],
        'disagio' => [
            'isee', 'indigen\w+', 'sfratto', 'morosit\w+', 'buoni spesa', 'disagio economico',
            'disagio sociale', 'assistenza economica', 'sussidio',
        ],
    ];

    /**
     * Esamina un testo e ritorna le categorie rilevate con i termini trovati.
     *
     * @param   string  $testo  Testo da esaminare (oggetto + titolo + descrizione)
     *
     * @return  array<string,array<int,string>>  es. ['salute' => ['invalidità', 'l. 104']]
     */
    public static function scan(string $testo): array
    {
        $testo = mb_strtolower(trim($testo));

        if ($testo === '') {
            return [];
        }

        $rilevate = [];

        foreach (self::TERMINI as $categoria => $termini) {
            $trovati = [];

            foreach ($termini as $t) {
                if (preg_match('/\b(' . $t . ')\b/u', $testo, $m)) {
                    $trovati[] = $m[1];
                }
            }

            if ($trovati) {
                $rilevate[$categoria] = array_values(array_unique($trovati));
            }
        }

        return $rilevate;
    }
}
