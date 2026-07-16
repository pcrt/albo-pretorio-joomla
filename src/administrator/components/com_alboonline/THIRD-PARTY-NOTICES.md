# Componenti di terzi inclusi nel pacchetto

Albo Pretorio Online **spedisce** dentro il proprio pacchetto alcune librerie scritte da altri.
Non sono un dettaglio tecnico: chi riceve il pacchetto riceve anche loro, e ne eredita le
condizioni. Questo documento dice **cosa c'è dentro e a quali condizioni**.

## Il punto che conta, detto subito

Il **codice di Albo Pretorio Online** è rilasciato sotto **GPL-2.0-or-later** (GNU GPL versione 2
*o successiva*), la stessa licenza di Joomla.

Il pacchetto però include **mPDF**, che è sotto **GPL-2.0-only** (GNU GPL versione 2, **senza**
la clausola «o successiva»).

Conseguenza pratica, e va detta chiaramente:

> **Il PACCHETTO nel suo insieme può essere ridistribuito solo alle condizioni della GNU GPL
> versione 2.** Non si può ridistribuire l'insieme sotto GPL v3, perché mPDF non lo consente.

Non c'è alcuna incompatibilità e non c'è niente da risolvere: le due licenze convivono, e la
versione 2 è il terreno comune. Ma chi prende questo software e lo ridistribuisce deve sapere
che **la GPL v2 è il tetto**, non un punto di partenza da cui salire.

Chi volesse riusare **solo il nostro codice**, senza mPDF, resta libero di applicare la
GPL-2.0-**or-later** e quindi anche la v3: la limitazione viene da mPDF, non da noi.

## Cosa c'è nel pacchetto

Tutte le librerie stanno in `administrator/components/com_alboonline/vendor/`.

| Libreria | A cosa serve qui | Licenza |
|---|---|---|
| **mpdf/mpdf** | genera il **referto di pubblicazione** in PDF. Joomla non include un generatore di PDF: senza questa libreria il referto non esiste | **GPL-2.0-only** ⚠️ *(è la libreria che fissa il tetto alla v2)* |
| setasign/fpdi | usata da mPDF per importare pagine PDF esistenti | MIT |
| mpdf/psr-http-message-shim | compatibilità PSR-7 per mPDF | MIT |
| mpdf/psr-log-aware-trait | compatibilità PSR-3 per mPDF | MIT |
| psr/http-message | interfacce standard PHP-FIG | MIT |
| psr/log | interfacce standard PHP-FIG | MIT |
| paragonie/random_compat | numeri casuali sicuri | MIT |
| myclabs/deep-copy | copia profonda di oggetti | MIT |

Le licenze **MIT** sono permissive e non pongono vincoli alla ridistribuzione, oltre a mantenere
l'avviso di copyright.

## I caratteri tipografici

Il pacchetto include i font **DejaVu** (dentro mPDF, in `vendor/mpdf/mpdf/ttfonts/`), necessari
perché il referto sia leggibile e contenga correttamente i caratteri accentati italiani.

Sono coperti dalla **licenza Bitstream Vera** — permissiva, ma con un obbligo preciso: **l'avviso
di copyright deve accompagnare ogni copia**. L'avviso viaggia col pacchetto nel file
`vendor/mpdf/mpdf/ttfonts/DejaVuinfo.txt` e **non va rimosso**.

Nota: mPDF nella sua distribuzione originale porta molti più font (circa 88 MB). Qui sono stati
ridotti ai soli DejaVu (circa 8,8 MB) per non gonfiare il pacchetto. È una rimozione di file, non
una modifica del codice.

## Nota onesta

Questo documento serve a mettere in chiaro cosa si sta ridistribuendo. **Non è una consulenza
legale.** Un ente che debba pronunciarsi formalmente sulle licenze del software che adotta
faccia valutare il pacchetto a chi ha titolo per farlo: qui ci sono i fatti su cui basare quella
valutazione, non la valutazione stessa.
