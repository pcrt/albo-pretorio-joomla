CREATE TABLE IF NOT EXISTS `#__alboonline_atti` (
    `id`                      INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `asset_id`                INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `catid`                   INT(10) UNSIGNED NOT NULL DEFAULT 0
                              COMMENT 'FK a #__categories',
    `titolo`                  VARCHAR(512) NOT NULL DEFAULT '',
    `alias`                   VARCHAR(400) NOT NULL DEFAULT '',
    `oggetto`                 VARCHAR(1024) NOT NULL DEFAULT ''
                              COMMENT 'Oggetto dell atto',
    `descrizione`             TEXT NULL,
    `numero_protocollo`       VARCHAR(20) NULL DEFAULT NULL
                              COMMENT 'Es: 2025/001',
    `anno_protocollo`         SMALLINT(4) UNSIGNED NULL DEFAULT NULL,
    `progressivo_protocollo`  INT(11) UNSIGNED NULL DEFAULT NULL,
    `tipo_atto`               VARCHAR(100) NOT NULL DEFAULT 'generico'
                              COMMENT 'determina|delibera|decreto|bando|avviso|generico',
    `ente_emanante`           VARCHAR(255) NOT NULL DEFAULT '',
    `responsabile`            VARCHAR(255) NOT NULL DEFAULT '',
    `data_atto`               DATE NULL DEFAULT NULL,
    `stato_atto`              TINYINT(2) NOT NULL DEFAULT 0
                              COMMENT '0=Bozza, 1=InAttesa, 2=Pubblicato, 3=Archiviato, -2=Cestinato',
    `state`                   TINYINT(1) NOT NULL DEFAULT 0
                              COMMENT 'Mappa Joomla: 0=Unpub, 1=Pub, 2=Arch, -2=Trash',
    `publish_up`              DATETIME NULL DEFAULT NULL,
    `publish_down`            DATETIME NULL DEFAULT NULL,
    `buffer_expires_at`       DATETIME NULL DEFAULT NULL
                              COMMENT 'publish_up + durata buffer',
    `locked_at`               DATETIME NULL DEFAULT NULL,
    `rettifica_di`            INT(11) UNSIGNED NULL DEFAULT NULL
                              COMMENT 'FK: questo atto rettifica l atto con questo ID',
    `rettificato_da`          INT(11) UNSIGNED NULL DEFAULT NULL
                              COMMENT 'FK: questo atto e stato rettificato',
    `codice_correlazione`     VARCHAR(255) NULL DEFAULT NULL
                              COMMENT 'Stringa libera per raggruppare atti correlati',
    `diritto_oblio`           TINYINT(1) NOT NULL DEFAULT 0
                              COMMENT '0=No, 1=Si',
    `visibilita_home`         VARCHAR(20) NOT NULL DEFAULT 'nessuna'
                              COMMENT 'nessuna|comunicazioni|evidenza',
    `robots`                  VARCHAR(50) NOT NULL DEFAULT ''
                              COMMENT 'Override robots per singolo atto',
    `segnatura_protocollo`    VARCHAR(100) NULL DEFAULT NULL
                              COMMENT 'Segnatura di protocollo dell ente (protocollo informatico, art.55 DPR 445/2000) — DISTINTA dal repertorio d albo (numero_protocollo)',
    `segnatura_data`          DATE NULL DEFAULT NULL
                              COMMENT 'Data della segnatura di protocollo dell ente',
    `note_rettifica`          TEXT NULL,
    `ordering`                INT(11) NOT NULL DEFAULT 0,
    `checked_out`             INT(11) UNSIGNED NULL DEFAULT NULL,
    `checked_out_time`        DATETIME NULL DEFAULT NULL,
    `created`                 DATETIME NOT NULL,
    `created_by`              INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `modified`                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified_by`             INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `version`                 INT(10) UNSIGNED NOT NULL DEFAULT 1,
    `hits`                    INT(10) UNSIGNED NOT NULL DEFAULT 0,
    `access`                  INT(10) UNSIGNED NOT NULL DEFAULT 1,
    `language`                CHAR(7) NOT NULL DEFAULT '*',
    `params`                  TEXT NULL,
    `metadata`                TEXT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_protocollo` (`anno_protocollo`, `progressivo_protocollo`),
    KEY `idx_catid` (`catid`),
    KEY `idx_stato_atto` (`stato_atto`),
    KEY `idx_state` (`state`),
    KEY `idx_publish_dates` (`publish_up`, `publish_down`),
    KEY `idx_buffer_expires` (`buffer_expires_at`),
    KEY `idx_rettifica_di` (`rettifica_di`),
    KEY `idx_codice_correlazione` (`codice_correlazione`),
    KEY `idx_visibilita_home` (`visibilita_home`),
    KEY `idx_checked_out` (`checked_out`),
    KEY `idx_created_by` (`created_by`),
    KEY `idx_access` (`access`),
    KEY `idx_language` (`language`),
    KEY `idx_alias` (`alias`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__alboonline_allegati` (
    `id`             INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `atto_id`        INT(11) UNSIGNED NOT NULL,
    `titolo`         VARCHAR(255) NOT NULL DEFAULT '',
    `filename`       VARCHAR(512) NOT NULL DEFAULT '',
    `original_name`  VARCHAR(512) NOT NULL DEFAULT '',
    `hash`           VARCHAR(64) NULL DEFAULT NULL,
    `size`           INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `mime`           VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
    `ordering`       INT(11) NOT NULL DEFAULT 0,
    `created`        DATETIME NOT NULL,
    `created_by`     INT(11) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_atto_id` (`atto_id`),
    KEY `idx_ordering` (`atto_id`, `ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__alboonline_log` (
    `id`            INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `atto_id`       INT(11) UNSIGNED NOT NULL,
    `azione`        VARCHAR(50) NOT NULL
                    COMMENT 'created|published|locked|archived|rectified|downloaded|modified',
    `stato_prima`   TINYINT(2) NULL DEFAULT NULL,
    `stato_dopo`    TINYINT(2) NULL DEFAULT NULL,
    `user_id`       INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `user_name`     VARCHAR(255) NOT NULL DEFAULT '',
    `ip_address`    VARCHAR(45) NULL DEFAULT NULL,
    `note`          TEXT NULL,
    `created`       DATETIME NOT NULL,

    PRIMARY KEY (`id`),
    KEY `idx_atto_id` (`atto_id`),
    KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__alboonline_contatori` (
    `anno`          SMALLINT(4) UNSIGNED NOT NULL,
    `ultimo_numero` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`anno`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Referti di pubblicazione congelati (M18).
-- Ogni riga è un documento SIGILLATO ed EMESSO a un preciso istante: i suoi
-- campi sono uno SNAPSHOT dei dati dell'atto al momento dell'emissione (NON si
-- rileggono mai dall'atto, che può cambiare). Il PDF su disco è immutabile e il
-- suo SHA-256 è in `pdf_hash`. Un periodo di pubblicazione reale = una riga;
-- un atto riattivato apre un nuovo `publish_up` => nuova riga (non sovrascrive).
CREATE TABLE IF NOT EXISTS `#__alboonline_referti` (
    `id`                   INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `atto_id`              INT(11) UNSIGNED NOT NULL,
    `tipo_documento`       VARCHAR(20) NOT NULL DEFAULT 'defissione'
                           COMMENT 'defissione|avvio — predisposto per il doppio documento',
    `numero_repertorio`    VARCHAR(20) NOT NULL DEFAULT ''
                           COMMENT 'Snapshot del numero_protocollo dell atto',
    `tipo_atto`            VARCHAR(100) NOT NULL DEFAULT '',
    `oggetto`              VARCHAR(1024) NOT NULL DEFAULT '',
    `ente_emanante`        VARCHAR(255) NOT NULL DEFAULT '',
    `responsabile`         VARCHAR(255) NOT NULL DEFAULT ''
                           COMMENT 'Responsabile della pubblicazione al momento dell emissione',
    `data_atto`            DATE NULL DEFAULT NULL,
    `publish_up`           DATETIME NULL DEFAULT NULL
                           COMMENT 'Inizio effettivo del periodo (UTC), congelato',
    `publish_down`         DATETIME NULL DEFAULT NULL
                           COMMENT 'Fine effettiva del periodo (UTC), congelata',
    `rettifica_di`         INT(11) UNSIGNED NULL DEFAULT NULL
                           COMMENT 'M10: snapshot del legame di rettifica (atto rettificato)',
    `rettifica_repertorio` VARCHAR(20) NULL DEFAULT NULL
                           COMMENT 'M10: repertorio dell atto rettificato',
    `note_rettifica`       TEXT NULL
                           COMMENT 'M11: motivazione della rettifica, congelata',
    `segnatura_protocollo` VARCHAR(100) NULL DEFAULT NULL
                           COMMENT 'S3-T4: snapshot della segnatura di protocollo dell ente',
    `segnatura_data`       DATE NULL DEFAULT NULL,
    `pdf_filename`         VARCHAR(512) NOT NULL DEFAULT ''
                           COMMENT 'Nome file del PDF congelato in media/com_alboonline/referti',
    `pdf_hash`             VARCHAR(64) NOT NULL DEFAULT ''
                           COMMENT 'SHA-256 del PDF emesso (integrità tecnica)',
    `pdf_size`             INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `stato`                VARCHAR(20) NOT NULL DEFAULT 'emesso'
                           COMMENT 'emesso|firmato',
    `emesso_at`            DATETIME NOT NULL
                           COMMENT 'Istante di emissione (UTC), congelato — stampato sul documento',
    `emesso_by`            INT(11) UNSIGNED NOT NULL DEFAULT 0,
    `firmato_filename`     VARCHAR(512) NULL DEFAULT NULL
                           COMMENT 'Nome file della versione firmata ricaricata (append-only)',
    `firmato_hash`         VARCHAR(64) NULL DEFAULT NULL,
    `firmato_mime`         VARCHAR(100) NULL DEFAULT NULL
                           COMMENT 'application/pdf (PAdES) | application/pkcs7-mime (CAdES .p7m)',
    `firmato_at`           DATETIME NULL DEFAULT NULL,
    `firmato_by`           INT(11) UNSIGNED NULL DEFAULT NULL,
    `note`                 TEXT NULL,
    `created`              DATETIME NOT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_atto_periodo` (`atto_id`, `tipo_documento`, `publish_up`),
    KEY `idx_atto_id` (`atto_id`),
    KEY `idx_stato` (`stato`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
