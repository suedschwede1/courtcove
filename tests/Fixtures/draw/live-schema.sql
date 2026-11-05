CREATE TABLE IF NOT EXISTS bs_draws (
  did           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tid           INT UNSIGNED DEFAULT NULL,
  slug          VARCHAR(32)  NOT NULL COMMENT 'oeffentliche URL-Kennung',
  title         VARCHAR(160) NOT NULL,
  status        ENUM('committed','executed') NOT NULL DEFAULT 'committed',
  payload       MEDIUMTEXT   NOT NULL,
  commitment    CHAR(64)     NOT NULL,
  beacon_source VARCHAR(255) NOT NULL COMMENT 'z.B. drand:round:5123456',
  server_seed   CHAR(64)     DEFAULT NULL,
  beacon_value  VARCHAR(255) DEFAULT NULL,
  final_seed    CHAR(64)     DEFAULT NULL,
  result        MEDIUMTEXT   DEFAULT NULL,
  created_uid   INT UNSIGNED NOT NULL,
  created       DATETIME     NOT NULL,
  executed      DATETIME     DEFAULT NULL,
  PRIMARY KEY (did),
  UNIQUE KEY slug (slug),
  KEY tid (tid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bs_draws
  MODIFY COLUMN status ENUM('committed','picking','closed','executed')
      NOT NULL DEFAULT 'committed',
  ADD COLUMN mode ENUM('direct','token') NOT NULL DEFAULT 'token'
      COMMENT 'direct = Server teilt zu, token = Teilnehmer waehlen' AFTER status,
  ADD COLUMN pick_deadline DATETIME DEFAULT NULL
      COMMENT 'Ende der Wahlphase - danach Fallback fuer Nichtwaehler' AFTER beacon_source,
  ADD COLUMN closed DATETIME DEFAULT NULL AFTER pick_deadline;

CREATE TABLE IF NOT EXISTS bs_draw_tokens (
  dtid         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  did          INT UNSIGNED NOT NULL,
  position     SMALLINT UNSIGNED NOT NULL,
  token        VARCHAR(16)  NOT NULL,
  slot         VARCHAR(8)   DEFAULT NULL COMMENT 'erst mit der Ausfuehrung gefuellt',
  claimed_tpid INT UNSIGNED DEFAULT NULL,
  claimed_uid  INT UNSIGNED DEFAULT NULL,
  claimed      DATETIME     DEFAULT NULL,
  assigned_by  ENUM('pick','fallback') DEFAULT NULL
               COMMENT 'fallback = nach Ablauf der Deadline zugeteilt',
  PRIMARY KEY (dtid),
  UNIQUE KEY did_token (did, token),
  UNIQUE KEY did_position (did, position),
  UNIQUE KEY did_claimed (did, claimed_tpid),
  KEY did_status (did, claimed_tpid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bs_draw_eligible (
  deid INT UNSIGNED NOT NULL AUTO_INCREMENT,
  did  INT UNSIGNED NOT NULL,
  tpid INT UNSIGNED NOT NULL,
  PRIMARY KEY (deid),
  UNIQUE KEY did_tpid (did, tpid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE bs_draws
  ADD COLUMN applied_at DATETIME DEFAULT NULL AFTER executed,
  ADD COLUMN applied_uid INT UNSIGNED DEFAULT NULL AFTER applied_at;

ALTER TABLE bs_draws
  ADD COLUMN discarded DATETIME DEFAULT NULL
      COMMENT 'gesetzt statt geloescht, wenn eine echte Ziehung verworfen wird' AFTER executed,
  ADD COLUMN discarded_reason VARCHAR(160) DEFAULT NULL AFTER discarded;
