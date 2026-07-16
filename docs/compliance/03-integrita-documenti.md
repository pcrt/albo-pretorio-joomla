# Integrità dei documenti — impronta SHA-256

**Deliverable S3-T5.** Documenta come il componente garantisce e rende **verificabile** l'integrità dei documenti pubblicati, tramite l'impronta crittografica **SHA-256**. Non introduce codice nuovo: consolida, espone e spiega una funzione già presente.

> **In una riga:** ogni allegato pubblicato ha un'impronta SHA-256 calcolata al momento della blindatura, conservata e **ri-verificata a ogni download**; l'impronta è mostrata come metadato, così chiunque può controllare che il file scaricato non sia stato alterato.

---

## 1. Cos'è l'impronta e cosa garantisce

L'**impronta** (hash) è una "firma digitale del contenuto": una stringa di 64 caratteri calcolata dai byte del file con l'algoritmo **SHA-256**. Due proprietà la rendono utile:

- **Deterministica**: lo stesso file produce sempre la stessa impronta.
- **Sensibile**: se cambia anche un solo byte del file, l'impronta cambia completamente.

Quindi confrontare l'impronta di un file con quella registrata dice, con certezza pratica, se il file **è ancora identico** all'originale o se è stato alterato. È una garanzia di **integrità**.

---

## 2. Quando e come viene calcolata

- **Alla blindatura.** Quando un atto viene bloccato (scaduto il buffer di sicurezza), lo scheduler calcola l'impronta SHA-256 di ogni allegato (`hash_file('sha256', ...)`) e la salva nel database. Da quel momento il documento è "sigillato": l'impronta è il suo riferimento.
- **Sul referto.** Anche il referto di pubblicazione, all'emissione, riceve la propria impronta SHA-256 (colonna `pdf_hash`).
- **Algoritmo.** Si usa **SHA-256**, un algoritmo crittografico robusto. Si evitano deliberatamente MD5 e SHA-1, considerati non più sicuri.

---

## 3. Dove è esposta e come si ri-verifica

L'impronta non resta nascosta nel database: è un **metadato consultabile**.

- **Frontend (cittadino):** sotto ogni allegato scaricabile compare "Impronta di integrità (SHA-256)" con il valore completo. Il cittadino può scaricare il file, ricalcolarne l'impronta con un qualsiasi strumento (`sha256sum` a riga di comando, o un'app) e confrontarla: se coincide, il file è autentico e non alterato.
- **Backend (operatore):** l'impronta è mostrata nella scheda dell'atto (allegati) e nell'elenco dei referti.
- **Ri-verifica automatica al download:** quando un documento blindato viene scaricato, il sistema **ricalcola** l'impronta del file su disco e la confronta con quella registrata; se non coincidono, il download viene bloccato con un errore di integrità. L'alterazione non passa inosservata.

---

## 4. Il limite onesto (cosa NON garantisce)

L'impronta garantisce l'**integrità tecnica** (il file non è cambiato), ma **da sola non conferisce immodificabilità probatoria a norma**. Come chiarito dalla ricerca (`docs/ricerca-normativa/08-verifica-firma-digitale.md`), il valore probatorio pieno richiede **una** delle operazioni AgID: la **firma digitale** del documento, oppure la conservazione a norma, oppure un sistema documentale conforme.

Detto altrimenti:

- **impronta** = "questo file non è stato alterato dopo la sua sigillatura" (integrità);
- **firma digitale** (S3-T1) = "questo documento è stato sottoscritto da X ed è giuridicamente valido".

Le due cose **collaborano** ma non si sostituiscono. Il componente offre l'impronta a tutti gli allegati (sempre) e la verifica della firma dove il documento è firmato; la conservazione a norma resta fuori scope (solo ganci).

---

## 5. Riferimenti nel codice

- `src/plugins/task/alboonline/src/Extension/AlboonlineTask.php` — `hashAllegati()`: calcolo SHA-256 alla blindatura.
- `src/administrator/components/com_alboonline/src/Helper/RefertoGenerator.php` — `pdf_hash` del referto all'emissione.
- `src/components/com_alboonline/src/Controller/DownloadController.php` — ri-verifica dell'impronta al download (blocco su mismatch).
- `src/components/com_alboonline/tmpl/atto/default.php` — esposizione dell'impronta al cittadino.
- `docs/ricerca-normativa/08-verifica-firma-digitale.md` — perché integrità ≠ validità qualificata a norma.
