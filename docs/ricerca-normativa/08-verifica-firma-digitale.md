# Ricerca normativa e tecnica — Verifica della firma digitale (PAdES / CAdES) per S3-T1

**Data:** 2026-07-10 · **Metodo:** deep-research con verifica avversariale (108 claim estratte → 25 verificate a 3 voti → **25 confermate, 0 refutate**). 23 fonti, 5 angoli.
**Scopo:** tracciare la **linea netta** tra ciò che il componente può verificare *internamente e in modo affidabile* (offline, in PHP) e ciò che richiede un *gancio esterno* (rete/liste di fiducia), con le **diciture oneste** da mostrare all'operatore. Base per progettare S3-T1.

> **Esito di fondo.** (1) La normativa **non impone** una verifica della firma *in tempo reale* alla pubblicazione: richiede la *presenza* di una firma qualificata/digitale (o alternative). Quindi la verifica è **buona pratica utile**, non un obbligo di legge. (2) Offline si può accertare in modo solido **l'integrità tecnica** della firma, **il firmatario**, la **finestra di validità** del certificato e la **presenza/coerenza della marca temporale**. (3) **Non** si può stabilire offline se il certificato è **qualificato**, se la **catena** è fidata, né lo **stato di revoca**: questi richiedono le Trusted List europee e OCSP/CRL → gancio esterno. (4) Mai spacciare "integrità tecnica" per "firma qualificata valida a norma".

---

## 1. Cosa richiede la normativa (e cosa NO)

- **Nessun obbligo di verifica firma runtime alla pubblicazione.** Le Linee Guida AgID sul documento informatico (ed. 2024-05, vigenti) chiedono che integrità e immodificabilità siano garantite da *una o più* operazioni — firma elettronica qualificata / firma digitale / sigillo qualificato / FEA, **oppure** memorizzazione in sistema documentale sicuro, PEC, versamento in conservazione. Per la pubblicazione l'obbligo è di **conformità di processo all'originale**, non uno step di validazione della firma. *(claim verificata 2-1: è un'inferenza negativa da fonte primaria + art. 32 L. 69/2009; resta il dovere generale di conformità, che una PA prudente può interpretare come opportunità di verifica.)*
- **Conseguenza per il progetto:** la verifica firma è una **funzione di supporto** (aiuta l'operatore a non pubblicare un file corrotto o firmato da chi non dovrebbe), **non** un cancello legale. Non deve bloccare la pubblicazione per default.

Fonti: Linee Guida AgID documento informatico (2024-05); L. 69/2009 art. 32.

---

## 2. PAdES vs CAdES — cosa si estrae e come si verifica

| | **PAdES** (`.pdf`) | **CAdES** (`.p7m`) |
|---|---|---|
| Struttura | firma = oggetto **PKCS#7 DER** nella entry `/Contents` del signature dictionary | busta **PKCS#7/CMS** che incapsula documento + firma + catena |
| Integrità | hash del **ByteRange** (tutto il file *incluso* il signature dictionary ma *escluso* il valore `/Contents`) confrontato col digest nella firma (ISO 32000-1 §12.8.1) | `message-digest` (attributo firmato obbligatorio) = hash dell'**eContent** (documento incapsulato) |
| Documento originale | è il PDF stesso (la firma è "dentro") | si estrae dall'**eContent**; il suo hash è il `message-digest` → **confrontabile con l'hash di un referto emesso** |
| Leggibilità cittadino | sì, con normali reader | no, richiede software dedicato |

Attributi firmati obbligatori del CMS (RFC 5126 §5.7, oggi ETSI EN 319 122): **content-type**, **message-digest**, **signing-certificate reference**. La marca temporale (CAdES-T / PAdES-T) è un attributo **non firmato** e si verifica **separatamente** dalla firma del firmatario.

Fonti: ETSI EN 319 142-2 (PAdES); ETSI EN 319 122 / RFC 5126 (CAdES); RFC 5652 (CMS); ISO 32000-1 §12.8.

---

## 3. TIER INTERNO — verificabile OFFLINE in PHP in modo affidabile

Tutto questo si fa **senza rete**, con OpenSSL/phpseclib:

1. **Integrità crittografica della firma**
   - CAdES/`.p7m`: `openssl_pkcs7_verify()` (usare `PKCS7_NOVERIFY` per isolare il *solo* controllo di integrità/digest dalla verifica della catena). Ritorna `true` solo se firma valida e messaggio non manomesso; nessun I/O di rete.
   - PAdES/`.pdf`: ricostruire l'hash sul **ByteRange** e confrontarlo col digest dentro il PKCS#7 in `/Contents`.
2. **Dati del firmatario** — `openssl_x509_parse()` espone il `subject`: **CN** e **codice fiscale** nel campo `serialNumber` (OID **2.5.4.5**), con prefisso **`TINIT-`** (es. `TINIT-...`) secondo ETSI EN 319 412-1 / AgID.
3. **Finestra di validità del certificato** — `validFrom`/`validTo` (+ `..._time_t`) oppure phpseclib `validateDate()`. *Caveat:* la validità andrebbe idealmente valutata **al signing time** indicato dalla marca temporale, non all'ora corrente.
4. **Marca temporale RFC 3161** — presenza + verifica: ricalcolo del digest sul valore di firma (stesso algoritmo del token) e confronto col `messageImprint` del token.

Fonti: manuale PHP `openssl_pkcs7_verify` / `openssl_x509_parse`; phpseclib X.509; ETSI EN 319 412-1; ISO 32000-1; RFC 3161.

---

## 4. TIER ESTERNO — NON affidabile offline, serve un gancio

Questi controlli **non si possono fare dentro il componente in modo autoritativo**:

- **Certificato QUALIFICATO?** — determinabile **solo** consultando le **EU Trusted Lists (EUTL)**, che hanno *valore legale costitutivo* (ETSI TS 119 615). I **QcStatements** nel certificato (es. QcCompliance, OID `0.4.0.1862.1.1`, estensione `qcStatements` OID `1.3.6.1.5.5.7.1.3`, **non critica**) sono **autodichiarazioni dell'emittente**, e la Trusted List può *overrule* il tipo dichiarato. → leggibili offline come **claim dell'emittente**, mai come stato qualificato *verificato*.
- **Catena verso una CA fidata** — richiede trust anchor (RFC 5280). Offline è possibile *solo* contro ancore fornite (es. estratte dalle EUTL); phpseclib di default fa **auto-fetch AIA** degli intermedi (rete/SSRF) → da **disabilitare**.
- **Stato di revoca (OCSP/CRL)** — richiede dati online. **Senza rete lo stato è INDETERMINATO** (né valido né revocato). Quindi senza controllo revoca **non si può dire "firma valida a norma"**.

Fonti: ETSI TS 119 615; ETSI EN 319 412-5; RFC 5280; RFC 6960; DSS cookbook.

---

## 5. Il gancio esterno: EU DSS (auto-ospitato)

- **EU DSS (Digital Signature Services)** — implementazione di riferimento della Commissione Europea per eIDAS. Core **LGPL-2.1** (**compatibile GPL-2.0-or-later**). Copre PAdES (EN 319 142) e CAdES (EN 319 122), valida a livelli *Basic / Long-Term / Archival*, integra le EU Trusted Lists e la determinazione *qualified* via ETSI TS 119 615.
- **Ma è una libreria Java** con web service REST/SOAP — nessun binding PHP nativo → il gancio è una **chiamata a un servizio DSS**, non linking.
- **Il demo pubblico EC è "for testing only"** e invia i file all'infrastruttura EC (implicazioni **GDPR/data-residency** per una PA). → il gancio corretto è un'**istanza DSS auto-ospitata** esposta via REST, **opzionale** e disattivabile; mai una dipendenza da SaaS.

Fonti: doc EC DSS; repo `esig/dss`.

---

## 6. Diciture oneste per l'operatore (load-bearing)

La verifica interna consente di affermare **al massimo**:

> ✅ *"Firma tecnicamente **integra** (il documento non è stato modificato dopo la firma). Firmatario: CN=…, CF=…. Certificato **nella finestra di validità** (dal … al …). Marca temporale presente e coerente."*

**Mai** affermare, senza il gancio esterno:

> ❌ *"Firma qualificata valida a norma"* · ❌ *"Certificato qualificato"* · ❌ *"Certificato non revocato"*

In assenza dei controlli avanzati lo stato corretto è **"da validare / esito avanzato non disponibile"**, mai un'asserzione di conformità legale piena. Questa separazione — *integrità tecnica* (interno) vs *validità qualificata a norma* (esterno) — è la regola d'oro della UI di S3-T1.

Fonti: ETSI TS 119 615; ETSI EN 319 412-5; DSS cookbook.

---

## 7. Domande aperte (da sciogliere in fase di design/collaudo)

1. Le `openssl_pkcs7_*` di PHP 8.3 coprono tutte le varianti reali di `.p7m` italiani (CAdES-BES vs CAdES-T, SHA-256/384, estrazione dell'eContent e del suo hash) o servono fallback su `openssl` CLI / parsing ASN.1 con phpseclib?
2. Per il ByteRange PAdES esiste una libreria PHP GPL-compatibile matura (gestione incremental update / firme multiple) o va implementato ad hoc?
3. Requisiti operativi reali per auto-ospitare DSS come microservizio REST (Java runtime, manutenzione trust-list/LOTL, failover quando è irraggiungibile)?
4. Esiste un validatore/endpoint **AgID** ufficiale usabile programmaticamente come gancio, con condizioni/GDPR accettabili, o DSS auto-ospitato resta l'unica opzione priva di SaaS?

---

## 8. Traduzione diretta in progettazione di S3-T1

- **Fase interna (sempre attiva, offline):** integrità (PAdES ByteRange / CAdES `openssl_pkcs7_verify`+eContent), firmatario (CN/CF), finestra validità, presenza+coerenza marca temporale. Esito = *"integrità tecnica"*, con diciture oneste.
- **Fase esterna (opzionale, disattivabile):** gancio a DSS auto-ospitato per qualified/catena/revoca. Se assente/irraggiungibile → stato *"validazione avanzata non disponibile"*, senza degradare il risultato interno.
- **Aggancio a M18:** per il referto firmato in `.p7m`, confrontare l'hash dell'**eContent** con il `pdf_hash` del referto emesso → prova che hanno firmato *quel* referto (con PAdES la firma modifica il PDF, quindi non vale il confronto whole-file).

## Caveat metodologici

Alcune fonti ETSI (EN 319 142-2, TS 119 615, EN 319 412-5) hanno restituito HTTP 403 al fetch diretto: le citazioni sono confermate da testo surfacciato e fonti indipendenti (iText, DSS, OID registry), non dal PDF ETSI originale. RFC 5126 è operativamente superata da ETSI EN 319 122 (contenuto definitorio invariato). Le Trusted List e TS 119 615 evolvono (v1.3.1 gen-2026): verificare la versione vigente all'implementazione.
