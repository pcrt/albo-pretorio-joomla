# Ricerca normativa — Documento informatico (AgID / eIDAS)

**Ambito:** requisiti tecnici del documento informatico pubblicato all'albo.

## Quadro di riferimento

- **Linee Guida AgID** "sulla formazione, gestione e conservazione dei documenti informatici" — in vigore dal **1/1/2022**, adottate ai sensi del CAD.
  PDF: https://www.agid.gov.it/sites/agid/files/2024-05/linee_guida_sul_documento_informatico.pdf — navigabile: https://docs.italia.it/AgID/documenti-in-consultazione/lg-documenti-informatici-docs/it/bozza/
- Allegati: **All. 2 "Formati di file e riversamento"**, **All. 4 "Standard e specifiche tecniche"**, **All. 5 "I Metadati"**. https://docs.italia.it/AgID/documenti-in-consultazione/lg-documenti-informatici-docs/it/bozza/allegati.html

## Requisiti che il software DEVE implementare

**a) Immodificabilità e integrità** — garantite con firma/sigillo qualificato o memorizzazione in sistema documentale sicuro o versamento in conservazione. L'integrità si assicura con **impronta crittografica hash** (SHA-256 raccomandato): calcolare, conservare e riverificare l'hash del file pubblicato.
https://docs.italia.it/AgID/documenti-in-consultazione/lg-documenti-informatici-docs/it/bozza/formazione-dei-documenti-informatici.html

**b) Formati (All. 2)** — per documenti destinati a pubblicazione/conservazione il profilo idoneo al lungo termine è **PDF/A** (A-1/A-2/A-3); PDF ordinario ammesso ma meno robusto. Altri idonei: TIFF, JPG, XML, TXT, ODF, OOXML. Validare MIME/profilo (idealmente PDF/A) in upload.

**c) Verifica firma elettronica** — verificare **PAdES** (`.pdf`) e **CAdES** (`.p7m`): validità del certificato qualificato, catena CA (TSL/EUTL eIDAS), stato di revoca (CRL/OCSP), **marca temporale** (RFC 3161). Riferimento eIDAS (Reg. UE 910/2014). PAdES e CAdES hanno **identica validità legale**; PAdES è visualizzabile con normali reader, CAdES richiede software dedicato.
https://www.agid.gov.it/sites/default/files/repository_files/linee_guida/firme_multiple_1.pdf

**d) Metadati minimi (All. 5)** — identificativo univoco e persistente, modalità di formazione, tipologia documentale, data, oggetto, soggetti (mittente/destinatario/autore/assegnatario), **impronta hash + algoritmo**, formato, versione, riservatezza, riferimento alla firma, classificazione/fascicolazione. Per gli atti amministrativi si aggiungono i dati di protocollo.
https://www.agid.gov.it/sites/agid/files/2024-06/allegato_5_metadati.pdf

**e) Segnatura di protocollo** — art. 55 DPR 445/2000 (TUDA) + regole tecniche protocollo informatico (DPCM 3/12/2013): identificazione univoca del documento amministrativo tramite segnatura accoppiata al documento.

## Best practice allegati firmati (.p7m / PAdES)

- Accettare `.pdf` (PAdES) e `.p7m` (CAdES); **estrarre e mostrare l'esito della verifica firma** (firmatario, CF, validità certificato, esito marca temporale) alla pubblicazione, con report.
- Per la visualizzazione al cittadino privilegiare PAdES o generare un rendering del contenuto della busta `.p7m`, mantenendo il file firmato originale immodificato.
- Calcolare/memorizzare **SHA-256** e esporlo come metadato riverificabile.
- Alla scadenza, generare il **pacchetto di versamento** verso conservazione secondo **UNI 11386 – SInCRO** con rapporto di versamento (impronte, riferimento temporale UTC, firma del responsabile). Verifica integrità/leggibilità almeno ogni 5 anni.
https://docs.italia.it/AgID/documenti-in-consultazione/lg-documenti-informatici-docs/it/bozza/conservazione.html

## Accessibilità dei PDF

Dal 1/1/2013 i documenti pubblicati sui siti PA devono essere **accessibili** (L. 4/2004, WCAG 2.1 / EN 301 549). PDF **taggati** (testo selezionabile, non scansioni-immagine); per documenti non accessibili fornire sommario/descrizione alternativa. Segnalare in upload i PDF solo-immagine.

## Zone grigie / rischi

- PDF/A: **raccomandato** per la conservazione, ma non imposto il rifiuto di un PDF non-A → validare e avvisare, non bloccare.
- CAdES vs PAdES: entrambi validi; i `.p7m` non sono leggibili dal cittadino senza software → best practice (non obbligo) fornire rendering.
- SHA-256: l'algoritmo non è imposto testualmente ("hash crittograficamente sicuro"); evitare MD5/SHA-1.
- Verifica firma in tempo reale: non esplicitamente imposta dalle LG alla pubblicazione, ma fortemente raccomandata.

> **Impatto sul progetto:** decisione #3 (conservazione fuori scope): il pacchetto SInCRO/UNI 11386 **non** è implementato dal componente; si predispone solo export/gancio. Decisione #4: verifica firma **interna** (firmatario/certificato/marca temporale) con validazione avanzata catena/revoca a gancio opzionale.
