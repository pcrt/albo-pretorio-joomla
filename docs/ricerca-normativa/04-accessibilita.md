# Ricerca normativa — Accessibilità digitale PA

**Ambito:** obblighi di accessibilità per un componente web usato dalle PA. Stato: luglio 2026.

## Obblighi normativi

Per un albo usato da una PA vale il **regime "settore pubblico"**:
- **Legge 9 gennaio 2004 n. 4 (Stanca)**, novellata dal **D.Lgs 106/2018** (recepimento **Direttiva UE 2016/2102**): siti, app e contenuti digitali devono essere **percepibili, utilizzabili, comprensibili, robusti**.
- **Standard tecnico obbligatorio**: norma armonizzata **UNI CEI EN 301 549 (v. 3.2.1)**, che incorpora le **WCAG 2.1 livello AA** (criteri A e AA). https://www.agid.gov.it/it/design-servizi/accessibilita/obiettivi-accessibilita
- **Stato WCAG 2.2 (2026)**: le **Linee guida AgID adottate il 4 marzo 2026 (Det. 38/2026, G.U. 24/3/2026)** continuano a richiedere **WCAG 2.1 AA come soglia minima vincolante**; **WCAG 2.2 è solo raccomandata** (diverrà obbligatoria quando la UE aggiornerà la EN 301 549). https://accessibilitadigitale.com/linee-guida-agid-accessibilita-servizi/
- **European Accessibility Act (Dir. UE 2019/882, D.Lgs 82/2022)**, in vigore dal **28 giugno 2025**: riguarda gli **operatori economici privati**, non direttamente le PA; rileva se il fornitore immette il servizio sul mercato (regime transitorio prodotti in uso fino al 28/6/2030).

**Adempimenti annuali della PA:**
- **Dichiarazione di accessibilità** (form.agid.gov.it), aggiornamento **annuale**, con meccanismo di feedback.
- **Obiettivi di accessibilità** entro il **31 marzo** di ogni anno (a cura del RTD).
- **Meccanismo di feedback/notifica** in ogni pagina; possibilità di reclamo ad AgID.

## Checklist WCAG 2.1 AA operativa (frontend albo)

**Struttura & semantica**: `<html lang="it">`; un solo `<h1>`, gerarchia heading senza salti (1.3.1, 2.4.6); landmark HTML5; `<title>` univoco per atto (2.4.2).
**Tabelle elenco atti**: `<table>` con `<thead>`, `<th scope>`, `<caption>` (1.3.1); ordinamento/filtri da tastiera con `aria-sort`; paginazione etichettata; risultati in `aria-live="polite"`.
**Tastiera & focus**: tutto operabile da tastiera senza trappole (2.1.1, 2.1.2); **focus visibile** (2.4.7); ordine logico (2.4.3); skip-link (2.4.1).
**Contrasto & colore**: testo ≥ **4.5:1**, testo grande ≥ 3:1 (1.4.3); UI e focus ≥ **3:1** (1.4.11); info non veicolata dal solo colore (1.4.1).
**Zoom & responsive**: zoom **200%** senza perdite (1.4.4); reflow a **320px** senza scroll orizzontale (1.4.10); niente `user-scalable=no`; spaziatura testo (1.4.12).
**Form**: `<label for>` o `aria-label` (1.3.1, 3.3.2); errori testuali con suggerimenti (3.3.1, 3.3.3); `autocomplete` (1.3.5).
**Immagini/ARIA**: `alt` significativo, `alt=""` decorative (1.1.1); ARIA solo dove serve; nome/ruolo/valore corretti (4.1.2).
**Documenti**: PDF **accessibili** (taggati, lingua, testo selezionabile, alt).

## Zone grigie / rischi

- **PDF di terzi** (uffici/professionisti): scansioni non taggate = principale fonte di non conformità → validare in upload, bloccare scansioni-immagine, avviso "richiedi formato accessibile", generare HTML nativo.
- **Editor WYSIWYG**: heading fuori gerarchia, tabelle di layout, `alt` mancanti, colori a basso contrasto → editor che imponga markup semantico.
- **Widget/overlay di accessibilità** di terze parti: non sostituiscono la conformità del codice.

## Sanzioni

- **Settore pubblico**: AgID monitora e **diffida**; rilievo su responsabilità dirigenziale/disciplinare; sanzione fino al **5% dei fondi stanziati** per il prodotto non conforme (art. 9 L. 4/2004).
- **EAA (operatori economici)**: sanzioni indicative **5.000–40.000 €** (fino al 5% del fatturato per grandi imprese); piattaforma reclami consumatori dall'11/3/2026.

## Fonti
- AgID Obiettivi di accessibilità — https://www.agid.gov.it/it/design-servizi/accessibilita/obiettivi-accessibilita
- Linee guida accessibilità (docs.italia) — https://docs.italia.it/AgID/documenti-in-consultazione/lg-accessibilita-docs/it/stabile/introduzione.html
- Aggiornamento L. 4/2004 — https://webaccessibile.org/normative/aggiornata-la-legge-4-2004-legge-stanca/
- Linee guida AgID 2026 (WCAG 2.1 vs 2.2) — https://accessibilitadigitale.com/linee-guida-agid-accessibilita-servizi/
- EAA / D.Lgs 82/2022 — https://www.studiolegalestefanelli.it/it/approfondimenti/european-accessibility-act-dlgs-82-2022-obblighi-2025
- form.agid.gov.it (dichiarazione + obiettivi) — https://form.agid.gov.it
